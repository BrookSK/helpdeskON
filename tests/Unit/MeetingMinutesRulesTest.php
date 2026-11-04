<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use MeetingMinutesRules;

/**
 * Testes unitários das regras puras da MINUTA/ATA automática (Fase 2).
 * Cobre: prontidão para gerar, normalização de status, montagem do prompt
 * (system/user/messages) e sanitização do conteúdo editado.
 */
final class MeetingMinutesRulesTest extends TestCase
{
    public function testNormalizeStatus(): void
    {
        $this->assertSame('none', MeetingMinutesRules::normalizeStatus('none'));
        $this->assertSame('processing', MeetingMinutesRules::normalizeStatus('processing'));
        $this->assertSame('done', MeetingMinutesRules::normalizeStatus('done'));
        $this->assertSame('error', MeetingMinutesRules::normalizeStatus('error'));
        // Desconhecido vira 'none'.
        $this->assertSame('none', MeetingMinutesRules::normalizeStatus('qualquer'));
        $this->assertSame('none', MeetingMinutesRules::normalizeStatus(''));
        $this->assertSame('none', MeetingMinutesRules::normalizeStatus(null));
    }

    public function testCanGenerateExigeTranscricaoComConteudo(): void
    {
        $this->assertTrue(MeetingMinutesRules::canGenerate('[00:01] Bom dia, pessoal.'));
        $this->assertTrue(MeetingMinutesRules::canGenerate('texto simples'));
        // Vazio, só espaços ou só pontuação não geram ata.
        $this->assertFalse(MeetingMinutesRules::canGenerate(''));
        $this->assertFalse(MeetingMinutesRules::canGenerate('   '));
        $this->assertFalse(MeetingMinutesRules::canGenerate("...  ---  [ ]"));
        $this->assertFalse(MeetingMinutesRules::canGenerate(null));
        // Contraprova: dígitos SÃO conteúdo (uma marca de tempo com texto gera).
        $this->assertTrue(MeetingMinutesRules::canGenerate('[00:00]'));
    }

    public function testSystemPromptPedeAtaEstruturadaEmPortugues(): void
    {
        $p = MeetingMinutesRules::systemPrompt();
        $this->assertStringContainsStringIgnoringCase('ata', $p);
        $this->assertStringContainsStringIgnoringCase('Decisões', $p);
        $this->assertStringContainsStringIgnoringCase('Próximos passos', $p);
        $this->assertStringContainsStringIgnoringCase('Valores', $p);
    }

    public function testUserPromptInclamaTituloDataETranscricao(): void
    {
        $u = MeetingMinutesRules::userPrompt('[00:00] olá', 'Reunião com Cliente X', '03/10/2026');
        $this->assertStringContainsString('Reunião com Cliente X', $u);
        $this->assertStringContainsString('03/10/2026', $u);
        $this->assertStringContainsString('[00:00] olá', $u);
    }

    public function testUserPromptSemTituloNaoQuebra(): void
    {
        $u = MeetingMinutesRules::userPrompt('conteudo', null, null);
        $this->assertStringContainsString('conteudo', $u);
        $this->assertStringContainsStringIgnoringCase('ATA', $u);
    }

    public function testUserPromptTruncaTranscricaoNoLimite(): void
    {
        $gigante = str_repeat('a', MeetingMinutesRules::TRANSCRIPT_LIMIT + 5000);
        $u = MeetingMinutesRules::userPrompt($gigante);
        // O corpo não pode conter a transcrição inteira (foi truncada).
        $this->assertLessThan(strlen($gigante) + 1000, strlen($u));
    }

    public function testBuildMessagesTemSystemEUser(): void
    {
        $msgs = MeetingMinutesRules::buildMessages('[00:00] olá', 'Assunto', '03/10/2026');
        $this->assertCount(2, $msgs);
        $this->assertSame('system', $msgs[0]['role']);
        $this->assertSame('user', $msgs[1]['role']);
        $this->assertStringContainsString('Assunto', $msgs[1]['content']);
    }

    public function testSanitizeContentNormalizaQuebrasEApara(): void
    {
        $in = "Linha 1\r\nLinha 2\r  \n";
        $out = MeetingMinutesRules::sanitizeContent($in);
        $this->assertStringNotContainsString("\r", $out);
        $this->assertStringContainsString("Linha 1\nLinha 2", $out);
        // rtrim remove o branco/linha final.
        $this->assertSame('', MeetingMinutesRules::sanitizeContent("   \n  "));
        $this->assertSame('', MeetingMinutesRules::sanitizeContent(null));
    }

    public function testSanitizeContentLimitaTamanho(): void
    {
        $gigante = str_repeat('x', 10);
        $out = MeetingMinutesRules::sanitizeContent($gigante, 4);
        $this->assertSame(4, mb_strlen($out));
    }

    // ---- textFromStructured (unificação: JSON estruturado -> texto, sem IA) ----

    public function testTextFromStructuredMontaSecoes(): void
    {
        $json = json_encode([
            'resumo' => 'Reunião sobre o projeto X.',
            'topicos' => ['Escopo definido', 'Prazo discutido'],
            'topicos_nao_resolvidos' => ['Forma de pagamento'],
            'decisoes' => ['Aprovado o MVP'],
            'proximos_passos' => ['Enviar proposta até sexta'],
        ]);
        $txt = MeetingMinutesRules::textFromStructured($json);
        $this->assertStringContainsString('Reunião sobre o projeto X.', $txt);
        $this->assertStringContainsString('Escopo definido', $txt);
        $this->assertStringContainsString('Forma de pagamento', $txt);
        $this->assertStringContainsString('Aprovado o MVP', $txt);
        $this->assertStringContainsString('Enviar proposta até sexta', $txt);
        // Deve conter os títulos das seções.
        $this->assertStringContainsString('## Resumo', $txt);
        $this->assertStringContainsString('## Próximos passos', $txt);
    }

    public function testTextFromStructuredAceitaArrayOuString(): void
    {
        $arr = ['resumo' => 'Oi', 'topicos' => [], 'decisoes' => [], 'proximos_passos' => [], 'topicos_nao_resolvidos' => []];
        $this->assertStringContainsString('Oi', MeetingMinutesRules::textFromStructured($arr));
        $this->assertStringContainsString('Oi', MeetingMinutesRules::textFromStructured(json_encode($arr)));
    }

    public function testTextFromStructuredSecoesVaziasTemFallback(): void
    {
        $txt = MeetingMinutesRules::textFromStructured(['resumo' => 'Só resumo.']);
        $this->assertStringContainsString('Nenhuma decisão registrada.', $txt);
        $this->assertStringContainsString('Nenhum próximo passo identificado.', $txt);
    }

    public function testTextFromStructuredVazioRetornaVazio(): void
    {
        $this->assertSame('', MeetingMinutesRules::textFromStructured(null));
        $this->assertSame('', MeetingMinutesRules::textFromStructured('nao-e-json'));
        $this->assertSame('', MeetingMinutesRules::textFromStructured([]));
        // Tudo vazio -> sem texto aproveitável.
        $this->assertSame('', MeetingMinutesRules::textFromStructured([
            'resumo' => '', 'topicos' => [], 'topicos_nao_resolvidos' => [], 'decisoes' => [], 'proximos_passos' => [],
        ]));
    }

    public function testNormalizeStructuredCompletoMantemCamposComerciais(): void
    {
        $d = MeetingMinutesRules::normalizeStructured(json_encode([
            'resumo' => ' Reunião de descoberta ', 'topicos' => ['a', ' ', 'b'],
            'necessidades' => ['CRM integrado'], 'escopo' => ['App'], 'prazos' => ['Dezembro'],
            'orcamento' => ['R$ 10 mil'], 'decisores' => ['Lucas'],
        ]));
        $this->assertSame('Reunião de descoberta', $d['resumo']);
        $this->assertSame(['a', 'b'], $d['topicos']);
        $this->assertSame(['R$ 10 mil'], $d['orcamento']);
        $this->assertSame(['Lucas'], $d['decisores']);
    }

    public function testNormalizeStructuredParcialDeixaCamposVazios(): void
    {
        $d = MeetingMinutesRules::normalizeStructured(['resumo' => 'x', 'orcamento' => '', 'escopo' => 'Site novo', 'prazos' => [['obj']]]);
        $this->assertSame([], $d['orcamento']);
        $this->assertSame(['Site novo'], $d['escopo']);
        $this->assertSame([], $d['prazos']);
        $this->assertSame([], $d['decisores']);
        $this->assertSame([], $d['proximos_passos']);
    }

    public function testNormalizeStructuredInvalidoDevolveNull(): void
    {
        $this->assertNull(MeetingMinutesRules::normalizeStructured('não é json'));
        $this->assertNull(MeetingMinutesRules::normalizeStructured(null));
    }

    public function testTextoMostraSecaoComercialSoQuandoPreenchida(): void
    {
        $t = MeetingMinutesRules::textFromStructured(['resumo' => 'r', 'necessidades' => ['CRM'], 'orcamento' => []]);
        $this->assertStringContainsString("## Necessidades do cliente\n- CRM", $t);
        $this->assertStringNotContainsString('Orçamento citado', $t);
    }

    public function testDivisaoDaTranscricaoSemCortarLinhas(): void
    {
        $this->assertSame(['curta'], MeetingMinutesRules::splitTranscript('curta', 100));
        $linhas = [];
        for ($i = 0; $i < 30; $i++) $linhas[] = sprintf('[%02d:00] linha %02d', $i, $i);
        $texto = implode("\n", $linhas);
        $partes = MeetingMinutesRules::splitTranscript($texto, 100);
        $this->assertGreaterThan(1, count($partes));
        foreach ($partes as $p) $this->assertLessThanOrEqual(100, mb_strlen($p));
        $this->assertSame($texto, implode("\n", $partes));
    }
}
