<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use EmailHtmlRules;
use MessageTemplate;

/**
 * HTML dos e-mails de prospecção (EmailHtmlRules) e templates (MessageTemplate):
 * formatação do editor chega no e-mail, texto puro mantém as quebras de linha,
 * layout do sistema e limpeza de HTML perigoso.
 */
final class EmailHtmlRulesTest extends TestCase
{
    public function testTextoPuroViraParagrafosEscapados(): void
    {
        $h = EmailHtmlRules::prepareBody("Olá Ana,\nTudo bem?\n\nAbraço <equipe> & cia");
        $this->assertStringContainsString('<p style="margin:0 0 12px 0">Olá Ana,<br>' . "\n" . 'Tudo bem?</p>', $h);
        $this->assertStringContainsString('Abraço &lt;equipe&gt; &amp; cia', $h);
        $this->assertSame('', EmailHtmlRules::textToHtml("  \n "));
    }

    public function testFormatacaoDoEditorViraEstiloInline(): void
    {
        $quill = '<h2>Proposta</h2><p class="ql-align-center"><strong>Negrito</strong> e <em>itálico</em> e <u>sublinhado</u></p>'
               . '<p class="ql-indent-2">Recuado</p><ul><li>Item</li></ul><p><br></p><p><a href="https://on.com.br" target="_blank">site</a></p>';
        $h = EmailHtmlRules::prepareBody($quill);
        $this->assertStringContainsString('<strong>Negrito</strong>', $h);
        $this->assertStringContainsString('<em>itálico</em>', $h);
        $this->assertStringContainsString('<u>sublinhado</u>', $h);
        $this->assertStringContainsString('text-align:center', $h);
        $this->assertStringContainsString('padding-left:6em', $h);
        $this->assertStringNotContainsString('class="ql-', $h, 'classes do editor não sobram');
        $this->assertStringContainsString('<h2 style="margin:0 0 12px 0;font-size:20px', $h);
        $this->assertStringContainsString('<ul style="margin:0 0 12px 0;padding-left:24px">', $h);
        $this->assertStringContainsString('<a href="https://on.com.br" target="_blank" style="color:#00997D">', $h);
        $this->assertStringContainsString('&nbsp;</p>', $h, 'linha vazia vira respiro');
    }

    public function testPrepararDuasVezesNaoDuplicaEstilo(): void
    {
        $once = EmailHtmlRules::prepareBody('<p class="ql-align-right">Oi</p><p>Tchau</p>');
        $this->assertSame($once, EmailHtmlRules::prepareBody($once));
        $this->assertSame(1, substr_count($once, 'text-align:right'));
    }

    public function testRemoveScriptEventosEJavascript(): void
    {
        $h = EmailHtmlRules::sanitize('<p onclick="x()">Oi</p><script>alert(1)</script><a href="javascript:alert(1)">x</a><iframe src="//e"></iframe><img src=x onerror=alert(1)>');
        $this->assertStringNotContainsString('script', strtolower($h));
        $this->assertStringNotContainsString('onclick', $h);
        $this->assertStringNotContainsString('onerror', $h);
        $this->assertStringNotContainsString('iframe', $h);
        $this->assertStringContainsString('href="#"', $h);
        $this->assertStringContainsString('<p>Oi</p>', $h);
    }

    public function testLayoutDoSistemaComLogoEIdempotente(): void
    {
        $l = EmailHtmlRules::wrapLayout('<p>Corpo</p>', ['logo_url' => 'https://exemplo.com/logo.png']);
        $this->assertStringStartsWith('<!DOCTYPE html>', $l);
        $this->assertStringContainsString(EmailHtmlRules::LAYOUT_MARKER, $l);
        $this->assertStringContainsString('<img src="https://exemplo.com/logo.png"', $l);
        $this->assertStringContainsString('<p>Corpo</p>', $l);
        $this->assertStringContainsString('background:#f5f7fa', $l);
        $this->assertStringContainsString('max-width:600px', $l);
        $this->assertSame($l, EmailHtmlRules::wrapLayout($l), 'não embrulha duas vezes');
        $this->assertSame($l, EmailHtmlRules::prepareBody($l), 'e-mail pronto não é reprocessado');

        $sem = EmailHtmlRules::wrapLayout('<p>x</p>');
        $this->assertStringContainsString('>ON</span>', $sem, 'sem logo: marca ON SOLUTIONS em texto');
        $this->assertStringContainsString('ON Solutions Brasil © ' . date('Y'), $sem);
    }

    public function testIsHtml(): void
    {
        $this->assertTrue(EmailHtmlRules::isHtml('<p>oi</p>'));
        $this->assertTrue(EmailHtmlRules::isHtml('Oi<br>tchau'));
        $this->assertFalse(EmailHtmlRules::isHtml('Olá {{primeiro_nome}}, 2 < 3'));
        $this->assertFalse(EmailHtmlRules::isHtml(null));
    }

    // ---- MessageTemplate ----

    public function testRenderHtmlEscapaOsDadosDoLead(): void
    {
        $c = ['contact_name' => 'Ana <b>& Cia', 'remetente_nome' => 'Lucas', 'company_name' => 'Acme', 'job_title' => 'CEO'];
        $h = MessageTemplate::renderHtml('<p>Olá <strong>{{primeiro_nome}}</strong>, {{cargo}} da {{empresa}} — {{remetente_nome}}</p>', $c);
        $this->assertSame('<p>Olá <strong>Ana</strong>, CEO da Acme — Lucas</p>', $h);
        $h = MessageTemplate::renderHtml('<p>{{nome}}</p>', $c);
        $this->assertSame('<p>Ana &lt;b&gt;&amp; Cia</p>', $h);
        $this->assertSame('Ana <b>& Cia', MessageTemplate::render('{{nome}}', $c), 'texto puro (assunto/WhatsApp) sem escape');
    }

    public function testNormalizeInputDoTemplate(): void
    {
        $e = MessageTemplate::normalizeInput(['channel' => 'email', 'name' => ' Apresentação ', 'subject' => 'Oi {{primeiro_nome}}', 'body' => '<p><strong>Oi</strong></p><script>x</script>']);
        $this->assertSame('Apresentação', $e['name']);
        $this->assertSame('<p><strong>Oi</strong></p>', $e['body']);
        $this->assertSame('Oi {{primeiro_nome}}', $e['subject']);

        $w = MessageTemplate::normalizeInput(['channel' => 'whatsapp', 'name' => 'Zap', 'subject' => 'ignorado', 'body' => '<p>Linha 1</p><p>Linha 2</p>']);
        $this->assertSame("Linha 1\nLinha 2", $w['body'], 'WhatsApp vira texto puro');
        $this->assertNull($w['subject']);

        $this->assertArrayHasKey('error', MessageTemplate::normalizeInput(['channel' => 'email', 'name' => 'X', 'body' => '<p><br></p>']));
        $this->assertArrayHasKey('error', MessageTemplate::normalizeInput(['channel' => 'email', 'name' => '', 'body' => 'Oi']));
        $this->assertSame('email', MessageTemplate::normalizeInput(['channel' => 'fax', 'name' => 'X', 'body' => 'Oi'])['channel']);
    }
}
