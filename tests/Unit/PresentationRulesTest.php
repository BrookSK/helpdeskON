<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use PresentationRules;

/**
 * Testes unitários das regras puras da Apresentação comercial: modelo padrão,
 * sanitização do conteúdo do editor, status e variáveis.
 */
final class PresentationRulesTest extends TestCase
{
    public function testModeloPadraoTemSecoesValidas(): void
    {
        $sections = PresentationRules::defaultSections();
        $this->assertNotEmpty($sections);
        $this->assertSame('hero', $sections[0]['type']);
        foreach ($sections as $s) {
            $this->assertTrue(PresentationRules::isValidType($s['type']));
        }
        // O modelo sobrevive à própria sanitização sem perder seções.
        $this->assertCount(count($sections), PresentationRules::sanitizeSections($sections));
    }

    public function testSanitizeDescartaTipoInvalidoEItensVazios(): void
    {
        $out = PresentationRules::sanitizeSections([
            ['type' => 'hack', 'title' => 'x'],
            'lixo',
            ['type' => 'list', 'title' => '  Lista  ', 'items' => [['title' => 'A'], ['title' => '', 'text' => ''], 'x']],
        ]);
        $this->assertCount(1, $out);
        $this->assertSame('Lista', $out[0]['title']);
        $this->assertSame([['title' => 'A', 'text' => '']], $out[0]['items']);
        $this->assertTrue($out[0]['visible']);
    }

    public function testSanitizeRespeitaVisibilidadeELimites(): void
    {
        $out = PresentationRules::sanitizeSections([
            ['type' => 'text', 'title' => str_repeat('a', 500), 'visible' => false],
        ]);
        $this->assertFalse($out[0]['visible']);
        $this->assertSame(200, mb_strlen($out[0]['title']));

        $many = array_fill(0, PresentationRules::MAX_SECTIONS + 10, ['type' => 'text', 'title' => 't']);
        $this->assertCount(PresentationRules::MAX_SECTIONS, PresentationRules::sanitizeSections($many));
        $this->assertSame([], PresentationRules::sanitizeSections('nao-e-array'));
    }

    public function testModeloPadraoCobreODocumentoDoGamma(): void
    {
        $titles = array_column(PresentationRules::defaultSections(), 'title');
        foreach (['1. Quem somos', '4. Perfil de cliente ideal', '5. Sinais de oportunidade', '20. Objeções e respostas',
                  '42. Checklist do Closer após a reunião', 'Planos de Hospedagem Cloud & VPS', 'Contato e Handoff'] as $t) {
            $this->assertContains($t, $titles);
        }
    }

    public function testSanitizeMantemDestaque(): void
    {
        $out = PresentationRules::sanitizeSections([['type' => 'table', 'title' => 'T', 'note' => '  Atenção  ']]);
        $this->assertSame('Atenção', $out[0]['note']);
    }

    public function testDecodeCaiNoModeloQuandoInvalido(): void
    {
        $this->assertSame(PresentationRules::defaultSections(), PresentationRules::decode(null));
        $this->assertSame(PresentationRules::defaultSections(), PresentationRules::decode('{quebrado'));

        // Conteúdo salvo antes do versionamento (modelo resumido antigo) volta ao padrão do Gamma.
        $legacy = json_encode(['sections' => [['type' => 'text', 'title' => 'Antigo']]]);
        $this->assertSame(PresentationRules::defaultSections(), PresentationRules::decode($legacy));

        $json = PresentationRules::encode([['type' => 'text', 'title' => 'Olá']]);
        $this->assertSame('Olá', PresentationRules::decode($json)[0]['title']);
    }

    public function testVisibleSections(): void
    {
        $visible = PresentationRules::visibleSections([
            ['type' => 'text', 'visible' => true],
            ['type' => 'text', 'visible' => false],
        ]);
        $this->assertCount(1, $visible);
    }

    public function testStatusEPublicacao(): void
    {
        $this->assertSame('published', PresentationRules::normalizeStatus('published'));
        $this->assertSame('draft', PresentationRules::normalizeStatus('qualquer'));
        $this->assertTrue(PresentationRules::isPublished(['status' => 'published']));
        $this->assertFalse(PresentationRules::isPublished(['status' => 'draft']));
        $this->assertFalse(PresentationRules::isPublished(null));
    }

    public function testApplyVars(): void
    {
        $this->assertSame(
            'Proposta para ACME por ON',
            PresentationRules::applyVars('Proposta para {{cliente}} por {{empresa}}', ['cliente' => 'ACME', 'empresa' => 'ON'])
        );
    }

    public function testMensagensAoClienteContemLink(): void
    {
        $link = 'https://exemplo.test/presentation/show/abc';
        $this->assertStringContainsString($link, PresentationRules::clientWhatsapp('Ana', $link, 'ON'));
        $this->assertStringContainsString($link, PresentationRules::clientEmailBody('Ana', $link));
    }
}
