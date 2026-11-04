<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ProposalRules;
use CrmRules;

/**
 * Regras puras de versão e aceite da proposta (change 02, tarefa 2.1) e da
 * coluna de ganho do CRM.
 */
final class ProposalRevisionRulesTest extends TestCase
{
    public function testVersaoNovaSoDepoisQueOClienteRecebeu(): void
    {
        foreach (['sent', 'awaiting', 'rejected'] as $st) {
            $this->assertTrue(ProposalRules::requiresNewRevision($st), $st);
        }
        foreach (['draft', 'ready', 'accepted', 'cancelled', 'qualquer', null] as $st) {
            $this->assertFalse(ProposalRules::requiresNewRevision($st), (string)$st);
        }
    }

    public function testRotuloDaVersao(): void
    {
        $this->assertSame('R01', ProposalRules::revisionLabel(1));
        $this->assertSame('R02', ProposalRules::revisionLabel('2'));
        $this->assertSame('R12', ProposalRules::revisionLabel(12));
        $this->assertSame('R01', ProposalRules::revisionLabel(0));
        $this->assertSame('R01', ProposalRules::revisionLabel(null));
    }

    public function testValidade(): void
    {
        $this->assertFalse(ProposalRules::isExpired(null, '2026-10-25'));
        $this->assertFalse(ProposalRules::isExpired('', '2026-10-25'));
        $this->assertFalse(ProposalRules::isExpired('2026-10-25', '2026-10-25'), 'vale o dia inteiro');
        $this->assertTrue(ProposalRules::isExpired('2026-10-24', '2026-10-25'));
        $this->assertTrue(ProposalRules::isExpired('24/10/2026', '2026-10-25'));
    }

    public function testNomeDoAceite(): void
    {
        $this->assertNull(ProposalRules::sanitizeAcceptName('   '));
        $this->assertSame('Maria Souza', ProposalRules::sanitizeAcceptName("  Maria \n  Souza "));
        $this->assertSame(200, mb_strlen(ProposalRules::sanitizeAcceptName(str_repeat('á', 250))));
    }

    public function testColunaDeGanho(): void
    {
        $cols = [
            ['id' => 3, 'name' => 'Perdido', 'position' => 3],
            ['id' => 2, 'name' => 'Ganho', 'position' => 9],
            ['id' => 1, 'name' => 'Fechado', 'position' => 4],
        ];
        $this->assertSame(1, CrmRules::findWonColumn($cols)['id']);
        $this->assertNull(CrmRules::findWonColumn([['id' => 1, 'name' => 'Em conversa', 'position' => 1]]));
        $this->assertNull(CrmRules::findWonColumn([]));
    }
}
