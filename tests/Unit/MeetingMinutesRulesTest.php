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
}
