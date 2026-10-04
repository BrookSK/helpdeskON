<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ProjectRules;

/**
 * Testes unitários das regras puras de Projetos/Garantia (Fase 9).
 * Cobre: garantia só p/ 'zero', cálculo do fim, vigência, aviso de 15 dias e a
 * regra de bloqueio de chamados pós-garantia sem suporte.
 */
final class ProjectRulesTest extends TestCase
{
    public function testHasWarrantySoParaZero(): void
    {
        $this->assertTrue(ProjectRules::hasWarranty('zero'));
        $this->assertFalse(ProjectRules::hasWarranty('manutencao'));
        $this->assertFalse(ProjectRules::hasWarranty('suporte'));
        $this->assertFalse(ProjectRules::hasWarranty('outro'));
    }

    public function testWarrantyEndDate(): void
    {
        // 90 dias a partir da entrega.
        $end = ProjectRules::warrantyEndDate('zero', '2026-01-01 00:00:00', 90);
        $this->assertSame('2026-04-01 00:00:00', $end);
        // Tipo sem garantia -> null.
        $this->assertNull(ProjectRules::warrantyEndDate('manutencao', '2026-01-01', 90));
        // Entrega inválida -> null.
        $this->assertNull(ProjectRules::warrantyEndDate('zero', null, 90));
        // warranty_days <= 0 cai no padrão 90.
        $end2 = ProjectRules::warrantyEndDate('zero', '2026-01-01 00:00:00', 0);
        $this->assertSame('2026-04-01 00:00:00', $end2);
    }

    public function testWarrantyActiveEDaysLeft(): void
    {
        $this->assertTrue(ProjectRules::warrantyActive('2026-04-01', '2026-03-20'));
        $this->assertFalse(ProjectRules::warrantyActive('2026-04-01', '2026-04-02'));
        $this->assertFalse(ProjectRules::warrantyActive(null, '2026-01-01'));
        $this->assertSame(10, ProjectRules::warrantyDaysLeft('2026-04-11 00:00:00', '2026-04-01 00:00:00'));
        $this->assertNull(ProjectRules::warrantyDaysLeft(null));
    }

    public function testShouldWarnWarrantyEnding(): void
    {
        // 10 dias restantes -> avisa (<=15 e >=0).
        $this->assertTrue(ProjectRules::shouldWarnWarrantyEnding('2026-04-11 00:00:00', '2026-04-01 00:00:00'));
        // 20 dias -> não avisa ainda.
        $this->assertFalse(ProjectRules::shouldWarnWarrantyEnding('2026-04-21 00:00:00', '2026-04-01 00:00:00'));
        // Já vencida -> não avisa.
        $this->assertFalse(ProjectRules::shouldWarnWarrantyEnding('2026-03-20 00:00:00', '2026-04-01 00:00:00'));
    }

    public function testCanOpenTicketComSuporte(): void
    {
        // Suporte ativo sempre libera, mesmo sem garantia.
        $p = ['contract_type' => 'outro', 'support_contract' => 1, 'status' => 'delivered', 'warranty_ends_at' => null];
        $this->assertTrue(ProjectRules::canOpenTicket($p));
        $this->assertNull(ProjectRules::blockReason($p));
    }

    public function testCanOpenTicketGarantiaVigenteEVencida(): void
    {
        $vigente = ['contract_type' => 'zero', 'support_contract' => 0, 'status' => 'warranty', 'warranty_ends_at' => '2026-04-01 00:00:00'];
        $this->assertTrue(ProjectRules::canOpenTicket($vigente, '2026-03-20'));
        $vencida = ['contract_type' => 'zero', 'support_contract' => 0, 'status' => 'warranty', 'warranty_ends_at' => '2026-04-01 00:00:00'];
        $this->assertFalse(ProjectRules::canOpenTicket($vencida, '2026-04-10'));
        $this->assertStringContainsString('Garantia encerrada', ProjectRules::blockReason($vencida, '2026-04-10'));
    }

    public function testCanOpenTicketSemGarantiaDependeDoStatus(): void
    {
        // Sem garantia e sem suporte: antes de entregar permite, depois bloqueia.
        $emAndamento = ['contract_type' => 'outro', 'support_contract' => 0, 'status' => 'in_progress', 'warranty_ends_at' => null];
        $this->assertTrue(ProjectRules::canOpenTicket($emAndamento));
        $entregue = ['contract_type' => 'outro', 'support_contract' => 0, 'status' => 'delivered', 'warranty_ends_at' => null];
        $this->assertFalse(ProjectRules::canOpenTicket($entregue));
        $this->assertStringContainsString('sem garantia', ProjectRules::blockReason($entregue));
    }

    // ===== Fluxo de Entrega: publicação, documentação e aceite =====

    public function testCanPublishSomenteEmAndamentoOuPlanejamento(): void
    {
        $this->assertTrue(ProjectRules::canPublish(['status' => 'planning']));
        $this->assertTrue(ProjectRules::canPublish(['status' => 'in_progress']));
        // Já entregue / em garantia / encerrado / cancelado não re-publica.
        $this->assertFalse(ProjectRules::canPublish(['status' => 'delivered']));
        $this->assertFalse(ProjectRules::canPublish(['status' => 'warranty']));
        $this->assertFalse(ProjectRules::canPublish(['status' => 'closed']));
        $this->assertFalse(ProjectRules::canPublish(['status' => 'cancelled']));
        $this->assertFalse(ProjectRules::canPublish([])); // sem status
    }

    public function testDocumentationDelivered(): void
    {
        $this->assertFalse(ProjectRules::documentationDelivered([]));
        $this->assertFalse(ProjectRules::documentationDelivered(['documentation_delivered_at' => null]));
        $this->assertFalse(ProjectRules::documentationDelivered(['documentation_delivered_at' => '']));
        $this->assertTrue(ProjectRules::documentationDelivered(['documentation_delivered_at' => '2026-10-03 10:00:00']));
    }

    public function testClientAccepted(): void
    {
        $this->assertFalse(ProjectRules::clientAccepted([]));
        $this->assertFalse(ProjectRules::clientAccepted(['client_accepted_at' => null]));
        $this->assertFalse(ProjectRules::clientAccepted(['client_accepted_at' => '']));
        $this->assertTrue(ProjectRules::clientAccepted(['client_accepted_at' => '2026-10-03 10:00:00']));
    }
}
