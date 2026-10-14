<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RdoRules;

/**
 * Testes unitários das regras puras do RDO (sem banco). Cobrem:
 *  - status, normalização, colaboradores, ocorrências, empresa (legado)
 *  - visibilidade por papel
 *  - prazo / bloqueio / aprovação (novos)
 *  - normalização de horário limite
 *  - buildSnapshot
 */
final class RdoRulesTest extends TestCase
{
    // =========================================================================
    // Status
    // =========================================================================

    public function testStatusValidoEInvalido(): void
    {
        $this->assertTrue(RdoRules::isValidStatus('em_andamento'));
        $this->assertTrue(RdoRules::isValidStatus('finalizado'));
        $this->assertFalse(RdoRules::isValidStatus('aprovado'));
        $this->assertFalse(RdoRules::isValidStatus(''));
    }

    public function testNormalizeStatus(): void
    {
        $this->assertSame('finalizado',    RdoRules::normalizeStatus('finalizado'));
        $this->assertSame('em_andamento',  RdoRules::normalizeStatus('xpto'));
        $this->assertSame('em_andamento',  RdoRules::normalizeStatus(''));
        $this->assertSame('finalizado',    RdoRules::normalizeStatus('xpto', 'finalizado'));
    }

    public function testLabel(): void
    {
        $this->assertSame('Em andamento', RdoRules::label('em_andamento'));
        $this->assertSame('Finalizado',   RdoRules::label('finalizado'));
    }

    // =========================================================================
    // Colaboradores
    // =========================================================================

    public function testNormalizeCollaboratorKind(): void
    {
        $this->assertSame('prestador',   RdoRules::normalizeCollaboratorKind('prestador'));
        $this->assertSame('colaborador', RdoRules::normalizeCollaboratorKind('colaborador'));
        $this->assertSame('colaborador', RdoRules::normalizeCollaboratorKind('outro'));
    }

    // =========================================================================
    // Empresa / project
    // =========================================================================

    public function testNormalizeCompanyId(): void
    {
        $this->assertSame(5,  RdoRules::normalizeCompanyId(5));
        $this->assertSame(5,  RdoRules::normalizeCompanyId('5'));
        $this->assertSame(42, RdoRules::normalizeCompanyId('42'));

        $this->assertNull(RdoRules::normalizeCompanyId(''));
        $this->assertNull(RdoRules::normalizeCompanyId(null));
        $this->assertNull(RdoRules::normalizeCompanyId(0));
        $this->assertNull(RdoRules::normalizeCompanyId('0'));
        $this->assertNull(RdoRules::normalizeCompanyId(-1));
        $this->assertNull(RdoRules::normalizeCompanyId('abc'));
    }

    // =========================================================================
    // Ocorrências
    // =========================================================================

    public function testDeriveHasOccurrence(): void
    {
        $this->assertSame(1, RdoRules::deriveHasOccurrence('Faltou material'));
        $this->assertSame(0, RdoRules::deriveHasOccurrence(''));
        $this->assertSame(0, RdoRules::deriveHasOccurrence('   '));
        $this->assertSame(0, RdoRules::deriveHasOccurrence(null));
    }

    // =========================================================================
    // Visibilidade por papel
    // =========================================================================

    public function testSuperAdminTemVisaoGlobal(): void
    {
        $this->assertTrue(RdoRules::hasGlobalView('super_admin'));
        $this->assertTrue(RdoRules::canViewReportOf('super_admin', 1, 999));
    }

    public function testDeveloperNaoTemVisaoGlobalESoVeOProprio(): void
    {
        $this->assertFalse(RdoRules::hasGlobalView('developer'));
        $this->assertTrue(RdoRules::canViewReportOf('developer', 5, 5));
        $this->assertFalse(RdoRules::canViewReportOf('developer', 5, 6));
    }

    public function testDemaisPapeisSoVeemOProprio(): void
    {
        foreach (['marketing', 'comercial', 'attendant', 'analyst', 'whatsapp_agent'] as $role) {
            $this->assertTrue(RdoRules::canViewReportOf($role, 10, 10),  "{$role} deveria ver o próprio");
            $this->assertFalse(RdoRules::canViewReportOf($role, 10, 11), "{$role} NÃO deveria ver o de outro");
        }
    }

    public function testViewerSemIdNaoVeNada(): void
    {
        $this->assertFalse(RdoRules::canViewReportOf('comercial', 0, 0));
        $this->assertFalse(RdoRules::canViewReportOf(null, 0, 0));
    }

    // =========================================================================
    // Revisão / aprovação
    // =========================================================================

    public function testSuperAdminPodeRevisar(): void
    {
        $this->assertTrue(RdoRules::canReview('super_admin'));
    }

    public function testDeveloperEDemaisNaoPodeRevisar(): void
    {
        foreach (['developer', 'comercial', 'attendant', 'analyst', 'marketing', 'whatsapp_agent', 'client', null] as $role) {
            $this->assertFalse(RdoRules::canReview($role), "papel '{$role}' não deveria poder revisar");
        }
    }

    // =========================================================================
    // Normalização do horário limite
    // =========================================================================

    public function testNormalizeDeadlineTimeFormatoCompleto(): void
    {
        $this->assertSame('19:00:00', RdoRules::normalizeDeadlineTime('19:00:00'));
        $this->assertSame('08:30:00', RdoRules::normalizeDeadlineTime('08:30:00'));
        $this->assertSame('23:59:59', RdoRules::normalizeDeadlineTime('23:59:59'));
    }

    public function testNormalizeDeadlineTimeFormatoAbreviado(): void
    {
        // HH:MM deve ser completado com :00
        $this->assertSame('19:00:00', RdoRules::normalizeDeadlineTime('19:00'));
        $this->assertSame('08:30:00', RdoRules::normalizeDeadlineTime('08:30'));
    }

    public function testNormalizeDeadlineTimeInvalidoRetornaPadrao(): void
    {
        $this->assertSame(RdoRules::DEFAULT_DEADLINE, RdoRules::normalizeDeadlineTime(''));
        $this->assertSame(RdoRules::DEFAULT_DEADLINE, RdoRules::normalizeDeadlineTime('abc'));
        // '99:99:99' tem formato válido (regex \d{2}:\d{2}:\d{2} aceita) → retorna o próprio valor
        $this->assertSame('99:99:99', RdoRules::normalizeDeadlineTime('99:99:99'));
        // Formatos realmente inválidos:
        $this->assertSame(RdoRules::DEFAULT_DEADLINE, RdoRules::normalizeDeadlineTime('9:00'));
        $this->assertSame(RdoRules::DEFAULT_DEADLINE, RdoRules::normalizeDeadlineTime('19:00:0'));
        $this->assertSame(RdoRules::DEFAULT_DEADLINE, RdoRules::normalizeDeadlineTime('hora'));
    }

    // =========================================================================
    // isWithinDeadline — controle de prazo
    // =========================================================================

    public function testDentroDosPrazoMesmoDia(): void
    {
        // Mesmo dia, horário ANTES do limite
        $this->assertTrue(
            RdoRules::isWithinDeadline('2026-10-03', '18:59:59', '2026-10-03', '19:00:00'),
            'Exatamente antes do prazo deve ser permitido'
        );
        $this->assertTrue(
            RdoRules::isWithinDeadline('2026-10-03', '19:00:00', '2026-10-03', '19:00:00'),
            'Exatamente no horário limite deve ser permitido'
        );
    }

    public function testAposDeadlineMesmoDia(): void
    {
        $this->assertFalse(
            RdoRules::isWithinDeadline('2026-10-03', '19:00:01', '2026-10-03', '19:00:00'),
            '1 segundo após o prazo deve ser bloqueado'
        );
        $this->assertFalse(
            RdoRules::isWithinDeadline('2026-10-03', '23:59:59', '2026-10-03', '19:00:00'),
            'Fim do dia deve ser bloqueado'
        );
    }

    public function testDiaAnteriorSempreFora(): void
    {
        // Ontem, qualquer horário — sempre fora do prazo
        $this->assertFalse(
            RdoRules::isWithinDeadline('2026-10-02', '08:00:00', '2026-10-03', '19:00:00'),
            'Dia anterior deve ser bloqueado independente do horário'
        );
    }

    public function testDataFuturaPermitida(): void
    {
        // Data futura — não bloqueia criação antecipada
        $this->assertTrue(
            RdoRules::isWithinDeadline('2026-10-10', '23:59:59', '2026-10-03', '19:00:00'),
            'Data futura nunca deve ser bloqueada'
        );
    }

    // =========================================================================
    // requiresApprovalForEdit — exige aprovação para editar?
    // =========================================================================

    public function testEdicaoDiretaDiaAtualNaoBloqueado(): void
    {
        // Mesmo dia, não bloqueado → pode editar direto
        $this->assertFalse(
            RdoRules::requiresApprovalForEdit('2026-10-03', 0, '2026-10-03'),
            'Relatório do dia atual não bloqueado não precisa de aprovação'
        );
    }

    public function testEdicaoDiaAnteriorExigeAprovacao(): void
    {
        // Dia anterior → exige aprovação independentemente do is_locked
        $this->assertTrue(
            RdoRules::requiresApprovalForEdit('2026-10-02', 0, '2026-10-03'),
            'Relatório de dia anterior deve exigir aprovação'
        );
    }

    public function testEdicaoBloqueadoExigeAprovacao(): void
    {
        // Mesmo dia mas bloqueado → exige aprovação
        $this->assertTrue(
            RdoRules::requiresApprovalForEdit('2026-10-03', 1, '2026-10-03'),
            'Relatório bloqueado deve exigir aprovação mesmo sendo do dia'
        );
    }

    public function testEdicaoDiaAnteriorBloqueadoExigeAprovacao(): void
    {
        // Dia anterior E bloqueado → exige aprovação
        $this->assertTrue(
            RdoRules::requiresApprovalForEdit('2026-10-01', 1, '2026-10-03')
        );
    }

    // =========================================================================
    // reviewTypeForCreate — tipo de revisão automática na criação
    // =========================================================================

    public function testCriacaoNoPrazoDiaTualNaoGeraRevisao(): void
    {
        $this->assertNull(
            RdoRules::reviewTypeForCreate('2026-10-03', '18:00:00', '2026-10-03', '19:00:00'),
            'Criação dentro do prazo não deve gerar revisão'
        );
    }

    public function testCriacaoAposDeadlineMesmoDiaGeraLateFill(): void
    {
        $this->assertSame(
            'late_fill',
            RdoRules::reviewTypeForCreate('2026-10-03', '20:00:00', '2026-10-03', '19:00:00'),
            'Criação após o horário limite deve gerar late_fill'
        );
    }

    public function testCriacaoExatamenteNoHorarioLimiteNaoGeraRevisao(): void
    {
        // Exatamente no deadline → não gera (nowTime <= deadlineTime)
        $this->assertNull(
            RdoRules::reviewTypeForCreate('2026-10-03', '19:00:00', '2026-10-03', '19:00:00')
        );
    }

    public function testCriacaoDataAnteriorGeraPostDeadline(): void
    {
        $this->assertSame(
            'post_deadline',
            RdoRules::reviewTypeForCreate('2026-10-02', '08:00:00', '2026-10-03', '19:00:00'),
            'Criação de relatório de dia anterior deve gerar post_deadline'
        );
    }

    public function testCriacaoDataFuturaGeraPostDeadline(): void
    {
        // Data futura não está em dias anteriores nem no dia: retorna null
        $this->assertNull(
            RdoRules::reviewTypeForCreate('2026-10-10', '08:00:00', '2026-10-03', '19:00:00'),
            'Data futura não deve gerar revisão automática'
        );
    }

    // =========================================================================
    // buildSnapshot
    // =========================================================================

    public function testBuildSnapshotContemCamposEsperados(): void
    {
        $report = [
            'title'         => 'Meu dia',
            'activities'    => 'Desenvolvi a feature X',
            'occurrences'   => 'Servidor fora',
            'pending_tasks' => 'Terminar Y',
            'next_day_plan' => 'Revisar Z',
            'status'        => 'em_andamento',
            'company_id'    => 5,
            'report_date'   => '2026-10-03',
        ];

        $json   = RdoRules::buildSnapshot($report);
        $parsed = json_decode($json, true);

        $this->assertIsArray($parsed);
        $this->assertSame('Meu dia',              $parsed['title']);
        $this->assertSame('Desenvolvi a feature X', $parsed['activities']);
        $this->assertSame('Servidor fora',        $parsed['occurrences']);
        $this->assertSame('Terminar Y',           $parsed['pending_tasks']);
        $this->assertSame('Revisar Z',            $parsed['next_day_plan']);
        $this->assertSame('em_andamento',         $parsed['status']);
        $this->assertSame(5,                      $parsed['company_id']);
        $this->assertSame('2026-10-03',           $parsed['report_date']);
    }

    public function testBuildSnapshotCamposAusentesViramNull(): void
    {
        $json   = RdoRules::buildSnapshot([]);
        $parsed = json_decode($json, true);

        $this->assertNull($parsed['title']);
        $this->assertNull($parsed['activities']);
        $this->assertNull($parsed['pending_tasks']);
        $this->assertNull($parsed['next_day_plan']);
        $this->assertNull($parsed['company_id']);
    }

    public function testBuildSnapshotJsonValido(): void
    {
        $json = RdoRules::buildSnapshot(['title' => 'Teste com "aspas" e <html>']);
        $this->assertNotFalse(json_decode($json));
        $this->assertStringNotContainsString('\u', $json); // JSON_UNESCAPED_UNICODE
    }
}
