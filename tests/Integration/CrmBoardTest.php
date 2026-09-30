<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use CrmBoard;
use Database;

/**
 * Testes de integração do CRM (CrmBoard) contra o banco helpdesk_on_test.
 * Cobre criação/movimentação de cards, atividades, follow-up (retomada),
 * estatísticas de dashboard e — o ponto crítico — o cálculo de comissões
 * com subqueries agregadas (regressão do SUM(DISTINCT) que colapsava
 * valores iguais).
 */
final class CrmBoardTest extends TestCase
{
    private Database $db;
    private CrmBoard $model;
    private int $boardId;
    private int $colNovoId;
    private int $colFechadoId;
    private int $colPerdidoId;
    private int $colSemInteresseId;
    private int $colSemRespostaId;
    private int $comercialA;
    private int $comercialB;
    /** @var int[] */
    private array $cardIds = [];
    /** Recursos extras das listas de perdidos (limpos no tearDown). */
    private ?int $instanceId = null;
    /** @var int[] */
    private array $contactIds = [];

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste (helpdesk_on_test) não configurado.');
        }
        $this->db = Database::getInstance();
        $this->model = new CrmBoard();

        $u = uniqid();
        // Comerciais com percentuais definidos
        $this->comercialA = (int) $this->db->insert('users', [
            'name' => "Comercial A {$u}", 'email' => "coma_{$u}@example.test",
            'password' => password_hash('x', PASSWORD_BCRYPT), 'role' => 'comercial',
            'commission_closing_percent' => 10.00,
            'commission_prospection_percent' => 5.00,
        ]);
        $this->comercialB = (int) $this->db->insert('users', [
            'name' => "Comercial B {$u}", 'email' => "comb_{$u}@example.test",
            'password' => password_hash('x', PASSWORD_BCRYPT), 'role' => 'comercial',
            'commission_closing_percent' => 20.00,
            'commission_prospection_percent' => 0.00,
        ]);

        $this->boardId = (int) $this->model->create([
            'name' => "Board {$u}", 'created_by' => $this->comercialA,
        ]);
        $this->colNovoId = (int) $this->model->createColumn([
            'board_id' => $this->boardId, 'name' => 'Novo',
        ]);
        $this->colFechadoId = (int) $this->model->createColumn([
            'board_id' => $this->boardId, 'name' => 'Fechado',
        ]);
        $this->colPerdidoId = (int) $this->model->createColumn([
            'board_id' => $this->boardId, 'name' => 'Perdido',
        ]);
        $this->colSemInteresseId = (int) $this->model->createColumn([
            'board_id' => $this->boardId, 'name' => 'Sem Interesse',
        ]);
        $this->colSemRespostaId = (int) $this->model->createColumn([
            'board_id' => $this->boardId, 'name' => 'Sem Resposta',
        ]);
    }

    protected function tearDown(): void
    {
        // Remove dados das listas de perdidos primeiro (dependem dos contatos/cards).
        foreach ($this->contactIds as $cid) {
            try { $this->db->delete('prospecting_lead_outcome', 'contact_id = ?', [$cid]); } catch (\Throwable $e) {}
            try { $this->db->delete('whatsapp_messages', 'contact_id = ?', [$cid]); } catch (\Throwable $e) {}
            try { $this->db->delete('email_messages', 'contact_id = ?', [$cid]); } catch (\Throwable $e) {}
        }
        // Remove cards, atividades, colunas, board e usuários criados.
        foreach ($this->cardIds as $id) {
            try { $this->db->delete('crm_card_activities', 'card_id = ?', [$id]); } catch (\Throwable $e) {}
        }
        try { $this->model->delete($this->boardId); } catch (\Throwable $e) {}
        // Contatos e instância (após o board, pois crm_cards.contact_id é SET NULL).
        foreach ($this->contactIds as $cid) {
            try { $this->db->delete('whatsapp_contacts', 'id = ?', [$cid]); } catch (\Throwable $e) {}
        }
        if ($this->instanceId) {
            try { $this->db->delete('whatsapp_instances', 'id = ?', [$this->instanceId]); } catch (\Throwable $e) {}
        }
        foreach ([$this->comercialA, $this->comercialB] as $id) {
            try { $this->db->delete('users', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
    }

    /** Cria uma instância de WhatsApp reutilizável (necessária para contatos). */
    private function ensureInstance(): int
    {
        if ($this->instanceId) return $this->instanceId;
        $u = uniqid();
        $this->instanceId = (int) $this->db->insert('whatsapp_instances', [
            'instance_name' => "inst_{$u}",
            'api_url' => 'http://localhost',
            'api_key' => 'x',
        ]);
        return $this->instanceId;
    }

    /**
     * Cria um contato (lead) individual, opcionalmente descadastrado (opt-out).
     */
    private function novoContato(array $overrides = []): int
    {
        $inst = $this->ensureInstance();
        $u = uniqid();
        $id = (int) $this->db->insert('whatsapp_contacts', array_merge([
            'instance_id' => $inst,
            'remote_jid' => "{$u}@s.whatsapp.net",
            'phone' => '5511' . substr((string) mt_rand(100000000, 999999999), 0, 9),
            'contact_name' => "Lead {$u}",
            'assigned_to' => $this->comercialA,
            'unsubscribed' => 0,
        ], $overrides));
        $this->contactIds[] = $id;
        return $id;
    }



    private function novoCard(array $overrides = []): int
    {
        $id = (int) $this->model->createCard(array_merge([
            'column_id' => $this->colNovoId,
            'title' => 'Lead teste',
            'value' => 1000.00,
            'created_by' => $this->comercialA,
        ], $overrides));
        $this->cardIds[] = $id;
        return $id;
    }

    // ================= Cards =================

    public function testCriarCardRegistraAtividadeEPosicao(): void
    {
        $id = $this->novoCard(['title' => 'Cliente X']);
        $card = $this->model->findCard($id);
        $this->assertSame('Cliente X', $card['title']);
        $this->assertSame('open', $card['lead_outcome']);

        $acts = $this->model->getActivities($id);
        $tipos = array_column($acts, 'activity_type');
        $this->assertContains('create', $tipos);
    }

    public function testMoverCardTrocaColunaERegistraMove(): void
    {
        $id = $this->novoCard();
        $this->model->moveCard($id, $this->colFechadoId);
        $card = $this->model->findCard($id);
        $this->assertSame($this->colFechadoId, (int) $card['column_id']);

        $acts = $this->model->getActivities($id);
        $this->assertContains('move', array_column($acts, 'activity_type'));
    }

    // ================= Follow-up (retomada) =================

    public function testProcessFollowUpsMoveCardVencidoParaPrimeiraColuna(): void
    {
        // Card na coluna Fechado, com follow_up vencido, ainda em aberto.
        $id = $this->novoCard(['column_id' => $this->colFechadoId]);
        $this->model->updateCard($id, [
            'follow_up_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'lead_outcome' => 'open',
        ]);

        $moved = $this->model->processFollowUps();
        $this->assertGreaterThanOrEqual(1, $moved);

        $card = $this->model->findCard($id);
        // Voltou para a primeira coluna (Novo) e marcado em recuperação
        $this->assertSame($this->colNovoId, (int) $card['column_id']);
        $this->assertSame(1, (int) $card['in_recovery']);
        $this->assertNull($card['follow_up_at']);
    }

    public function testProcessFollowUpsIgnoraCardFuturoOuFechado(): void
    {
        $futuro = $this->novoCard(['column_id' => $this->colFechadoId]);
        $this->model->updateCard($futuro, [
            'follow_up_at' => date('Y-m-d H:i:s', strtotime('+2 days')),
            'lead_outcome' => 'open',
        ]);
        $convertido = $this->novoCard(['column_id' => $this->colFechadoId]);
        $this->model->updateCard($convertido, [
            'follow_up_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'lead_outcome' => 'converted',
        ]);

        $this->model->processFollowUps();

        // O futuro continua na coluna Fechado; o convertido não é retomado.
        $this->assertSame($this->colFechadoId, (int) $this->model->findCard($futuro)['column_id']);
        $this->assertSame($this->colFechadoId, (int) $this->model->findCard($convertido)['column_id']);
    }

    // ================= Dashboard =================

    public function testDashboardStatsContaAberturasConversoesEPerdas(): void
    {
        $base = $this->model->getDashboardStats();

        $this->novoCard(['value' => 100.00]); // open
        $conv = $this->novoCard(['value' => 200.00]);
        $this->model->updateCard($conv, ['lead_outcome' => 'converted', 'outcome_at' => date('Y-m-d H:i:s')]);
        $lost = $this->novoCard(['value' => 50.00]);
        $this->model->updateCard($lost, ['lead_outcome' => 'lost', 'outcome_at' => date('Y-m-d H:i:s')]);

        $stats = $this->model->getDashboardStats();
        $this->assertSame((int)$base['converted'] + 1, $stats['converted']);
        $this->assertSame((int)$base['lost'] + 1, $stats['lost']);
        $this->assertSame((int)$base['open'] + 1, $stats['open']);
        $this->assertEqualsWithDelta((float)$base['converted_value'] + 200.0, $stats['converted_value'], 0.001);
    }

    // ================= Comissões (regressão SUM DISTINCT) =================

    public function testComissaoSomaDoisCardsDeMesmoValorSemColapsar(): void
    {
        // Comercial A fecha DOIS negócios de EXATAMENTE R$ 1.000 no mesmo mês.
        // Com SUM(DISTINCT value) o total virava 1.000 (colapsava). Agora deve
        // somar 2.000 e a comissão (10%) ser 200.
        $month = date('Y-m');
        $when = date('Y-m-d H:i:s');
        foreach ([1, 2] as $_) {
            $id = $this->novoCard(['column_id' => $this->colFechadoId, 'value' => 1000.00]);
            $this->model->updateCard($id, [
                'lead_outcome' => 'converted',
                'outcome_at' => $when,
                'converted_by' => $this->comercialA,
                'prospected_by' => $this->comercialA,
            ]);
        }

        $rows = $this->model->getCommissions($month, $this->comercialA);
        $this->assertCount(1, $rows);
        $r = $rows[0];
        $this->assertSame(2, (int) $r['closed_count']);
        $this->assertEqualsWithDelta(2000.0, (float) $r['closed_value'], 0.001);
        // 10% de 2.000 = 200
        $this->assertEqualsWithDelta(200.0, (float) $r['closing_commission'], 0.001);
        $this->assertEqualsWithDelta(200.0, (float) $r['commission_value'], 0.001);
    }

    public function testComissaoProspeccaoQuandoOutroFecha(): void
    {
        // A prospecta (5%), B fecha. A recebe prospecção, B recebe fechamento (20%).
        $month = date('Y-m');
        $when = date('Y-m-d H:i:s');
        $id = $this->novoCard(['column_id' => $this->colFechadoId, 'value' => 3000.00]);
        $this->model->updateCard($id, [
            'lead_outcome' => 'converted',
            'outcome_at' => $when,
            'prospected_by' => $this->comercialA,
            'converted_by' => $this->comercialB,
        ]);

        $rowsA = $this->model->getCommissions($month, $this->comercialA);
        $rowsB = $this->model->getCommissions($month, $this->comercialB);

        // A: 5% de 3.000 = 150 (prospecção); nenhum fechamento
        $this->assertEqualsWithDelta(150.0, (float) $rowsA[0]['prospection_commission'], 0.001);
        $this->assertSame(0, (int) $rowsA[0]['closed_count']);
        $this->assertSame(1, (int) $rowsA[0]['prospected_count']);

        // B: 20% de 3.000 = 600 (fechamento)
        $this->assertEqualsWithDelta(600.0, (float) $rowsB[0]['closing_commission'], 0.001);
        $this->assertSame(1, (int) $rowsB[0]['closed_count']);
    }

    public function testComissaoNaoContaProspeccaoSeMesmaPessoaFechou(): void
    {
        // A prospecta E fecha: não deve contar como prospected_count (evita dupla contagem).
        $month = date('Y-m');
        $id = $this->novoCard(['column_id' => $this->colFechadoId, 'value' => 1000.00]);
        $this->model->updateCard($id, [
            'lead_outcome' => 'converted',
            'outcome_at' => date('Y-m-d H:i:s'),
            'prospected_by' => $this->comercialA,
            'converted_by' => $this->comercialA,
        ]);

        $rows = $this->model->getCommissions($month, $this->comercialA);
        $this->assertSame(1, (int) $rows[0]['closed_count']);
        $this->assertSame(0, (int) $rows[0]['prospected_count']);
    }

    // ================= markOutcomeByContact (Sem Interesse / Sem Resposta) =================

    public function testMarcarNaoInteresseMoveCardParaSemInteresseEPerde(): void
    {
        // Lead com card na coluna "Novo" (posição 0).
        $contato = $this->novoContato();
        $cardId = $this->novoCard(['column_id' => $this->colNovoId, 'contact_id' => $contato]);

        $ok = $this->model->markOutcomeByContact($contato, 'Sem Interesse', 'lost');
        $this->assertTrue($ok);

        $card = $this->model->findCard($cardId);
        $this->assertSame($this->colSemInteresseId, (int) $card['column_id'], 'Card deve ir para "Sem Interesse".');
        $this->assertSame('lost', $card['lead_outcome'], 'Desfecho deve ser perdido.');
        $this->assertNotNull($card['outcome_at']);
    }

    public function testMarcarSemRespostaMoveCardParaSemRespostaEPerde(): void
    {
        $contato = $this->novoContato();
        $cardId = $this->novoCard(['column_id' => $this->colNovoId, 'contact_id' => $contato]);

        $ok = $this->model->markOutcomeByContact($contato, 'Sem Resposta', 'lost');
        $this->assertTrue($ok);

        $card = $this->model->findCard($cardId);
        $this->assertSame($this->colSemRespostaId, (int) $card['column_id']);
        $this->assertSame('lost', $card['lead_outcome']);
    }

    public function testMarcarOutcomeSemCardRetornaFalse(): void
    {
        // Contato sem nenhum card no board: nada a mover.
        $contato = $this->novoContato();
        $this->assertFalse($this->model->markOutcomeByContact($contato, 'Sem Interesse', 'lost'));
    }

    public function testMarcarOutcomeEhIdempotente(): void
    {
        $contato = $this->novoContato();
        $cardId = $this->novoCard(['column_id' => $this->colNovoId, 'contact_id' => $contato]);

        $this->model->markOutcomeByContact($contato, 'Sem Interesse', 'lost');
        // Segunda chamada não deve quebrar nem mudar a coluna/desfecho.
        $this->model->markOutcomeByContact($contato, 'Sem Interesse', 'lost');

        $card = $this->model->findCard($cardId);
        $this->assertSame($this->colSemInteresseId, (int) $card['column_id']);
        $this->assertSame('lost', $card['lead_outcome']);
    }

    public function testMarcarOutcomeNaoRegrideCardJaAvancado(): void
    {
        // Card já em "Fechado" (posição à direita de "Novo"). Ao marcar "Sem
        // Resposta" (posição anterior), a anti-regressão NÃO deve puxar o card
        // para trás — mas o desfecho ainda é carimbado.
        $contato = $this->novoContato();
        $cardId = $this->novoCard(['column_id' => $this->colFechadoId, 'contact_id' => $contato]);

        // "Sem Resposta" foi criada depois de "Fechado", então tem posição maior;
        // para exercitar a anti-regressão, movemos o alvo para uma coluna anterior.
        $this->model->markOutcomeByContact($contato, 'Novo', 'lost');

        $card = $this->model->findCard($cardId);
        // Não regrediu para "Novo" (posição menor que "Fechado").
        $this->assertSame($this->colFechadoId, (int) $card['column_id'], 'Não deve regredir de "Fechado" para "Novo".');
        // Mas o desfecho foi marcado mesmo sem mover.
        $this->assertSame('lost', $card['lead_outcome']);
    }

    public function testArrastarManualParaSemInteresseMarcaPerdido(): void
    {
        // Simula o efeito do CrmController::moveCard: como isLostColumn() agora
        // reconhece "Sem Interesse", mover para lá marca o desfecho.
        $this->assertTrue(\CrmRules::isLostColumn('Sem Interesse'));
        $this->assertTrue(\CrmRules::isLostColumn('Sem Resposta'));
    }
}
