<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecordingShareRules;

/** Estado público da transcrição: só o que o link público já pode mostrar. */
final class RecordingShareRulesTest extends TestCase
{
    private const SUMMARY = '{"resumo":"Cliente quer CRM","topicos":["CRM"]}';

    public function testNaFilaNaoDevolveTranscricaoNemMinuta(): void
    {
        $out = RecordingShareRules::publicStatus([
            'transcribe_status' => 'queued', 'transcript_json' => '[{"start":0,"text":"x"}]', 'summary' => self::SUMMARY,
        ]);
        $this->assertSame('queued', $out['status']);
        $this->assertNull($out['transcript_json']);
        $this->assertNull($out['minutes_structured']);
        $this->assertNull($out['error_message']);
    }

    public function testProntaDevolveTranscricaoEEscondeMinutaNaoEnviada(): void
    {
        $out = RecordingShareRules::publicStatus([
            'transcribe_status' => 'done', 'transcript_json' => '[{"start":1,"end":2,"text":"Olá"}]',
            'summary' => self::SUMMARY, 'minutes_sent_at' => null,
        ]);
        $this->assertSame('done', $out['status']);
        $this->assertSame([['start' => 1, 'end' => 2, 'text' => 'Olá']], $out['transcript_json']);
        $this->assertNull($out['minutes_structured'], 'rascunho da minuta não sai pelo link público');
    }

    public function testMinutaEnviadaApareceNoLinkPublico(): void
    {
        $out = RecordingShareRules::publicStatus([
            'transcribe_status' => 'done', 'transcript_json' => '[]',
            'summary' => self::SUMMARY, 'minutes_sent_at' => '2026-11-12 10:00:00',
        ]);
        $this->assertSame('Cliente quer CRM', $out['minutes_structured']['resumo']);
    }

    public function testErroNaoExpoeDetalheInterno(): void
    {
        $out = RecordingShareRules::publicStatus([
            'transcribe_status' => 'error', 'transcribe_error' => 'Segmento 2: chave sk-xyz inválida', 'summary' => 'detalhe interno',
        ]);
        $this->assertSame('error', $out['status']);
        $this->assertSame(RecordingShareRules::ERROR_MESSAGE, $out['error_message']);
    }
}
