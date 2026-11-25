<?php

use PHPUnit\Framework\TestCase;

/**
 * Template de WhatsApp: a formatação é feita pelos marcadores do próprio WhatsApp
 * no texto. O que o editor manda tem que ser gravado e renderizado sem perder os
 * marcadores, a quebra de linha nem os emojis.
 */
class WhatsappTemplateInputTest extends TestCase
{
    public function testMarcadoresListasEEmojisSaoMantidos(): void
    {
        $body = "Oi {{primeiro_nome}}, *proposta* _especial_ ~antiga~ ```COD-1```\n• Prazo\n1. Passo\n> Citação 🟢";
        $r = MessageTemplate::normalizeInput(['channel' => 'whatsapp', 'name' => 'Zap', 'body' => $body]);
        $this->assertSame($body, $r['body']);
        $this->assertSame('whatsapp', $r['channel']);
        $this->assertNull($r['subject']);
    }

    public function testQuebrasDoFormularioViramLf(): void
    {
        $r = MessageTemplate::normalizeInput(['channel' => 'whatsapp', 'name' => 'Zap', 'body' => "Linha 1\r\n• Item\r\n\r\nFim\r"]);
        $this->assertSame("Linha 1\n• Item\n\nFim", $r['body']);
        $r = MessageTemplate::normalizeInput(['channel' => 'linkedin', 'name' => 'In', 'body' => "A\r\nB"]);
        $this->assertSame("A\nB", $r['body']);
    }

    public function testCitacaoComMaiorNaoEhTratadaComoHtml(): void
    {
        $r = MessageTemplate::normalizeInput(['channel' => 'whatsapp', 'name' => 'Zap', 'body' => "> Posso te ligar?\n2 < 3 e *ok*"]);
        $this->assertSame("> Posso te ligar?\n2 < 3 e *ok*", $r['body']);
    }

    public function testSomenteMarcadoresSemTextoContaComoConteudo(): void
    {
        // "**" não é vazio para o WhatsApp (aparece literalmente); só espaço é vazio.
        $this->assertArrayHasKey('error', MessageTemplate::normalizeInput(['channel' => 'whatsapp', 'name' => 'Zap', 'body' => " \r\n "]));
    }

    public function testRenderTrocaVariavelDentroDoNegrito(): void
    {
        $out = MessageTemplate::render('*{{primeiro_nome}}*, _{{empresa}}_', MessageTemplate::sampleContact());
        $this->assertStringStartsWith('*Maria*, _', $out);
        $this->assertStringNotContainsString('{{', $out);
    }
}
