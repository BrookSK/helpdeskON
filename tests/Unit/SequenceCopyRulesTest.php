<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use SequenceCopyRules;

/** Duplicar sequência: nome da cópia e campos copiados. */
final class SequenceCopyRulesTest extends TestCase
{
    public function testPrimeiraCopiaGanhaSufixo(): void
    {
        $this->assertSame('Follow-up Clínicas (cópia)', SequenceCopyRules::copyName('Follow-up Clínicas', ['Follow-up Clínicas']));
    }

    public function testNumeraQuandoJaExisteCopia(): void
    {
        $existing = ['Prospecção', 'Prospecção (cópia)', 'prospecção (CÓPIA 2)'];
        $this->assertSame('Prospecção (cópia 3)', SequenceCopyRules::copyName('Prospecção', $existing));
    }

    public function testDuplicarUmaCopiaNaoEmpilhaSufixo(): void
    {
        $existing = ['Base', 'Base (cópia)'];
        $this->assertSame('Base (cópia 2)', SequenceCopyRules::copyName('Base (cópia)', $existing));
        $this->assertSame('Base (cópia 2)', SequenceCopyRules::copyName('Base (cópia 7)', array_merge($existing, ['Base (cópia 7)'])));
    }

    public function testNomeVazioOuLongo(): void
    {
        $this->assertSame('Sequência (cópia)', SequenceCopyRules::copyName('   ', []));
        $long = str_repeat('á', 200);
        $copy = SequenceCopyRules::copyName($long, []);
        $this->assertSame(SequenceCopyRules::NAME_MAX, mb_strlen($copy), 'cabe na coluna name (150)');
        $this->assertStringEndsWith(' (cópia)', $copy);
    }

    public function testCopiaLevaTudoMenosIdentidade(): void
    {
        $orig = [
            'id' => 9, 'name' => 'A', 'description' => 'desc', 'channel_type' => 'mixed', 'email_account_id' => 3,
            'whatsapp_instance_id' => 4, 'graph' => '{"start":"n1","nodes":[]}', 'is_active' => 1, 'daily_limit' => 40,
            'window_start' => '09:00:00', 'window_end' => '17:00:00', 'send_weekends' => 0, 'created_by' => 2,
            'created_at' => '2026-01-01 10:00:00', 'updated_at' => '2026-01-02 10:00:00',
        ];
        $row = SequenceCopyRules::copyRow($orig, 'A (cópia)', 7);
        foreach (['id', 'created_at', 'updated_at'] as $c) $this->assertArrayNotHasKey($c, $row);
        $this->assertSame('A (cópia)', $row['name']);
        $this->assertSame(7, $row['created_by']);
        foreach (['description', 'channel_type', 'email_account_id', 'whatsapp_instance_id', 'graph', 'is_active', 'daily_limit', 'window_start', 'window_end', 'send_weekends'] as $c) {
            $this->assertSame($orig[$c], $row[$c], $c);
        }
        $this->assertSame(2, SequenceCopyRules::copyRow($orig, 'x', null)['created_by'], 'sem usuário mantém o autor');
    }
}
