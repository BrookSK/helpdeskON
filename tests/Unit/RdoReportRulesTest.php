<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RdoReportRules;

/**
 * Testes unitários das regras puras da EXPORTAÇÃO do relatório consolidado de
 * RDO em PDF. Sem banco/HTTP/rede. Cobrem: controle de acesso (só super_admin),
 * validação/normalização de período (válido, invertido, formato inválido, data
 * impossível, teto de intervalo), rótulos, montagem do prompt da IA usando
 * SOMENTE os campos reais (anti-invenção) e classificação de anexo-imagem.
 */
final class RdoReportRulesTest extends TestCase
{
    // ===== Acesso =====

    public function testSoSuperAdminPodeExportar(): void
    {
        $this->assertTrue(RdoReportRules::canExport('super_admin'));
    }

    public function testDemaisPapeisNaoPodemExportar(): void
    {
        foreach (['developer', 'attendant', 'analyst', 'comercial', 'marketing', 'whatsapp_agent', 'client'] as $role) {
            $this->assertFalse(RdoReportRules::canExport($role), "papel {$role} não deveria exportar");
        }
        $this->assertFalse(RdoReportRules::canExport(null));
        $this->assertFalse(RdoReportRules::canExport(''));
    }

    // ===== parseDate =====

    public function testParseDateAceitaDataValida(): void
    {
        $this->assertSame('2026-10-08', RdoReportRules::parseDate('2026-10-08'));
        $this->assertSame('2026-10-08', RdoReportRules::parseDate('  2026-10-08  '));
    }

    public function testParseDateRejeitaFormatoEDataImpossivel(): void
    {
        $this->assertNull(RdoReportRules::parseDate('08/10/2026'));
        $this->assertNull(RdoReportRules::parseDate('2026-13-01')); // mês inválido
        $this->assertNull(RdoReportRules::parseDate('2026-02-31')); // dia impossível
        $this->assertNull(RdoReportRules::parseDate(''));
        $this->assertNull(RdoReportRules::parseDate(null));
        $this->assertNull(RdoReportRules::parseDate(20261008));
    }

    // ===== normalizePeriod =====

    public function testNormalizePeriodValido(): void
    {
        $r = RdoReportRules::normalizePeriod('2026-10-08', '2026-10-23');
        $this->assertTrue($r['ok']);
        $this->assertSame('2026-10-08', $r['from']);
        $this->assertSame('2026-10-23', $r['to']);
    }

    public function testNormalizePeriodAceitaMesmoDia(): void
    {
        $r = RdoReportRules::normalizePeriod('2026-10-08', '2026-10-08');
        $this->assertTrue($r['ok']);
    }

    public function testNormalizePeriodRejeitaInicioDepoisDoFim(): void
    {
        $r = RdoReportRules::normalizePeriod('2026-10-23', '2026-10-08');
        $this->assertFalse($r['ok']);
        $this->assertNotEmpty($r['error']);
    }

    public function testNormalizePeriodRejeitaDatasInvalidas(): void
    {
        $this->assertFalse(RdoReportRules::normalizePeriod('', '2026-10-08')['ok']);
        $this->assertFalse(RdoReportRules::normalizePeriod('2026-10-08', 'xx')['ok']);
        $this->assertFalse(RdoReportRules::normalizePeriod(null, null)['ok']);
    }

    public function testNormalizePeriodRejeitaIntervaloAcimaDoTeto(): void
    {
        // 2024 é bissexto; de 2024-01-01 a 2025-01-02 = 368 dias (> 366).
        $r = RdoReportRules::normalizePeriod('2024-01-01', '2025-01-02');
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('período', mb_strtolower($r['error']));
    }

    public function testNormalizePeriodAceitaExatamenteOTeto(): void
    {
        // 2025 não é bissexto: 2025-01-01 a 2025-12-31 = 365 dias (<= 366).
        $r = RdoReportRules::normalizePeriod('2025-01-01', '2025-12-31');
        $this->assertTrue($r['ok']);
    }

    // ===== rangeDays / rótulos =====

    public function testRangeDaysInclusivo(): void
    {
        $this->assertSame(1, RdoReportRules::rangeDays('2026-10-08', '2026-10-08'));
        $this->assertSame(16, RdoReportRules::rangeDays('2026-10-08', '2026-10-23'));
    }

    public function testPeriodLabelEDateBr(): void
    {
        $this->assertSame('08/10/2026', RdoReportRules::dateBr('2026-10-08'));
        $this->assertSame('08/10/2026 a 23/10/2026', RdoReportRules::periodLabel('2026-10-08', '2026-10-23'));
    }

    public function testFileName(): void
    {
        $this->assertSame('relatorio-diario-2026-10-08-a-2026-10-23.pdf', RdoReportRules::fileName('2026-10-08', '2026-10-23'));
    }

    // ===== Prompt da IA (somente dados reais) =====

    public function testReportsAsTextIncluiApenasCamposPreenchidos(): void
    {
        $reports = [[
            'report_date'   => '2026-10-08',
            'company_name'  => 'Fidelize AI',
            'title'         => 'Dia de pintura',
            'activities'    => 'Pintura do pavimento superior',
            'occurrences'   => '',          // vazio: NÃO deve aparecer
            'pending_tasks' => null,        // nulo: NÃO deve aparecer
            'next_day_plan' => 'Continuar amanhã',
            'collaborators' => [
                ['collaborator_name' => 'Edilawson'],
                ['collaborator_name' => ''],   // vazio ignorado
            ],
        ]];
        $text = RdoReportRules::reportsAsText($reports);

        $this->assertStringContainsString('RDO de 08/10/2026 — Fidelize AI', $text);
        $this->assertStringContainsString('Título: Dia de pintura', $text);
        $this->assertStringContainsString('Atividades: Pintura do pavimento superior', $text);
        $this->assertStringContainsString('Plano para o próximo dia: Continuar amanhã', $text);
        $this->assertStringContainsString('Colaboradores/Prestadores: Edilawson', $text);
        // Campos vazios não entram:
        $this->assertStringNotContainsString('Ocorrências:', $text);
        $this->assertStringNotContainsString('Pendências:', $text);
    }

    public function testBuildSummaryMessagesTemSystemAntiInvencaoEUserComDados(): void
    {
        $reports = [[
            'report_date' => '2026-10-08',
            'activities'  => 'Instalação de porcelanato',
        ]];
        $msgs = RdoReportRules::buildSummaryMessages($reports, '2026-10-08', '2026-10-08');

        $this->assertCount(2, $msgs);
        $this->assertSame('system', $msgs[0]['role']);
        $this->assertSame('user', $msgs[1]['role']);
        // A instrução anti-invenção deve estar presente no system.
        $this->assertStringContainsString('EXCLUSIVAMENTE', $msgs[0]['content']);
        $this->assertStringContainsString('Não invente', $msgs[0]['content']);
        // O conteúdo real deve estar no user, com período e total.
        $this->assertStringContainsString('08/10/2026 a 08/10/2026', $msgs[1]['content']);
        $this->assertStringContainsString('Total de RDOs no período: 1', $msgs[1]['content']);
        $this->assertStringContainsString('Instalação de porcelanato', $msgs[1]['content']);
    }

    public function testBuildSummaryMessagesComListaVazia(): void
    {
        $msgs = RdoReportRules::buildSummaryMessages([], '2026-10-08', '2026-10-23');
        $this->assertCount(2, $msgs);
        $this->assertStringContainsString('Total de RDOs no período: 0', $msgs[1]['content']);
    }

    // ===== Anexo-imagem =====

    public function testIsImageAttachmentPorMimeType(): void
    {
        $this->assertTrue(RdoReportRules::isImageAttachment(['file_type' => 'image/jpeg']));
        $this->assertTrue(RdoReportRules::isImageAttachment(['file_type' => 'IMAGE/PNG']));
        $this->assertFalse(RdoReportRules::isImageAttachment(['file_type' => 'application/pdf', 'file_name' => 'doc.pdf']));
        $this->assertFalse(RdoReportRules::isImageAttachment(['file_type' => 'audio/webm', 'file_name' => 'a.webm']));
    }

    public function testIsImageAttachmentFallbackPorExtensao(): void
    {
        // file_type vazio/genérico: decide pela extensão do nome/caminho.
        $this->assertTrue(RdoReportRules::isImageAttachment(['file_type' => '', 'file_name' => 'foto.JPG']));
        $this->assertTrue(RdoReportRules::isImageAttachment(['file_type' => 'application/octet-stream', 'file_path' => 'uploads/rdo/x.webp']));
        $this->assertFalse(RdoReportRules::isImageAttachment(['file_type' => '', 'file_name' => 'relatorio.docx']));
    }
}
