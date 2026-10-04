<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Ticket;
use Database;

/**
 * Teste de integração dos fluxos de Nova Demanda (escopo/homologação) e Suporte,
 * contra helpdesk_on_test. Cobre a camada de persistência:
 *  - campos de escopo técnico, aprovação/recusa de escopo e previsão;
 *  - campos de suporte (gravidade, prazos, terceiros);
 *  - relacionamento ticket<->ticket (Suporte -> Incidente -> Correção) e sua
 *    leitura bidirecional via Ticket::getRelations.
 *
 * Estratégia de dados: cria um usuário "cliente" no setUp e remove no tearDown
 * (FK ON DELETE CASCADE em tickets.client_id -> users.id remove os tickets, e
 * ON DELETE CASCADE em ticket_relations remove as relações).
 */
final class TicketScopeSupportTest extends TestCase
{
    private Database $db;
    private Ticket $tickets;
    private int $userId;

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste (helpdesk_on_test) não configurado.');
        }

        $this->db = Database::getInstance();
        $this->tickets = new Ticket();

        // Skip amigável se as migrations 142/143/144 não tiverem sido aplicadas.
        try {
            $this->db->query("SELECT escopo_incluido, support_severity, previsao_publicacao FROM tickets LIMIT 1");
            $this->db->query("SELECT id FROM ticket_relations LIMIT 1");
        } catch (\Throwable $e) {
            $this->markTestSkipped('Migrations 142/143/144 não aplicadas no banco de teste.');
        }

        $this->userId = (int) $this->db->insert('users', [
            'name' => 'Cliente Teste ' . uniqid(),
            'email' => 'cli+' . uniqid() . '@example.com',
            'password' => password_hash('x', PASSWORD_DEFAULT),
            'role' => 'client',
            'is_active' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        try {
            // Remove tickets do usuário (ticket_relations cai por cascade).
            $this->db->delete('tickets', 'client_id = ?', [$this->userId]);
            $this->db->delete('users', 'id = ?', [$this->userId]);
        } catch (\Throwable $e) {
            // ignora falha de limpeza
        }
    }

    private function novoTicket(array $extra = []): int
    {
        $data = array_merge([
            'client_id' => $this->userId,
            'title' => 'Demanda ' . uniqid(),
            'description' => 'desc',
            'priority' => 'medium',
            'status' => 'open',
            'client_ticket_number' => 1,
        ], $extra);
        return (int) $this->tickets->create($data);
    }

    public function testCamposDeEscopoPersistem(): void
    {
        $id = $this->novoTicket();
        $this->tickets->update($id, [
            'escopo_incluido' => 'Nova tela de login',
            'escopo_excluido' => 'Integração com SSO',
            'escopo_execucao' => 'Refatorar o LoginController',
            'estimativa_dias' => 5,
            'scope_submitted_at' => date('Y-m-d H:i:s'),
        ]);
        $t = $this->tickets->findById($id);
        $this->assertSame('Nova tela de login', $t['escopo_incluido']);
        $this->assertSame('Integração com SSO', $t['escopo_excluido']);
        $this->assertSame(5, (int) $t['estimativa_dias']);
        $this->assertNotNull($t['scope_submitted_at']);
    }

    public function testNovoStatusAguardandoAprovacaoEscopoEhAceito(): void
    {
        $id = $this->novoTicket();
        // O ENUM precisa aceitar o novo status (migration 142).
        $this->tickets->updateStatus($id, 'aguardando_aprovacao_escopo');
        $t = $this->tickets->findById($id);
        $this->assertSame('aguardando_aprovacao_escopo', $t['status']);
    }

    public function testRecusaDeEscopoGuardaMotivo(): void
    {
        $id = $this->novoTicket(['status' => 'aguardando_aprovacao_escopo']);
        $this->tickets->update($id, ['scope_rejected_reason' => 'Mudar o layout']);
        $this->tickets->updateStatus($id, 'in_progress');
        $t = $this->tickets->findById($id);
        $this->assertSame('in_progress', $t['status']);
        $this->assertSame('Mudar o layout', $t['scope_rejected_reason']);
    }

    public function testCamposDeSuportePersistem(): void
    {
        $id = $this->novoTicket(['category' => 'suporte']);
        $this->tickets->update($id, [
            'support_severity' => 'critico',
            'support_analysis_due_at' => '2026-01-10 10:30:00',
            'support_workaround' => 'Reiniciar o serviço',
            'is_third_party' => 1,
            'third_party_name' => 'API Pagamentos X',
            'third_party_notes' => 'Aguardando retorno do fornecedor',
        ]);
        $t = $this->tickets->findById($id);
        $this->assertSame('critico', $t['support_severity']);
        $this->assertSame(1, (int) $t['is_third_party']);
        $this->assertSame('API Pagamentos X', $t['third_party_name']);
    }

    public function testPrevisaoDePublicacaoPersiste(): void
    {
        $id = $this->novoTicket();
        $this->tickets->update($id, ['previsao_publicacao' => '2026-02-15']);
        $t = $this->tickets->findById($id);
        $this->assertSame('2026-02-15', $t['previsao_publicacao']);
    }

    public function testRelacionamentoEntreDemandasBidirecional(): void
    {
        $suporte = $this->novoTicket(['category' => 'suporte', 'title' => 'Chamado de Suporte']);
        $correcao = $this->novoTicket(['category' => 'desenvolvimento', 'title' => 'Correção definitiva']);

        // Suporte -> Correção (aresta direcionada).
        $this->db->query(
            "INSERT INTO ticket_relations (source_ticket_id, target_ticket_id, relation_type, created_by)
             VALUES (?, ?, 'correcao', ?)",
            [$suporte, $correcao, $this->userId]
        );

        // Do lado do suporte: relação de saída (outgoing) apontando para a correção.
        $relsSuporte = $this->tickets->getRelations($suporte);
        $this->assertCount(1, $relsSuporte);
        $this->assertSame('outgoing', $relsSuporte[0]['direction']);
        $this->assertSame('correcao', $relsSuporte[0]['relation_type']);
        $this->assertSame($correcao, (int) $relsSuporte[0]['other_id']);

        // Do lado da correção: relação de entrada (incoming) vinda do suporte.
        $relsCorrecao = $this->tickets->getRelations($correcao);
        $this->assertCount(1, $relsCorrecao);
        $this->assertSame('incoming', $relsCorrecao[0]['direction']);
        $this->assertSame($suporte, (int) $relsCorrecao[0]['other_id']);
    }

    public function testHomologacaoEmAndamentoListaDemandaComJanelaAberta(): void
    {
        $id = $this->novoTicket();
        $this->tickets->updateStatus($id, 'em_homologacao');
        $this->tickets->update($id, ['homolog_started_at' => date('Y-m-d H:i:s')]);

        $rows = $this->tickets->getHomologacaoEmAndamento();
        $ids = array_map(fn($r) => (int) $r['id'], $rows);
        $this->assertContains($id, $ids);
    }
}
