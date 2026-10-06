<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Ticket;
use PlanningCard;
use TicketNotificationService;
use Database;

/**
 * Reproduz o fluxo de "Nova Demanda" criada por um usuário DEVELOPER sem
 * selecionar cliente — caso em que client_id passa a ser o id do próprio
 * developer (role != 'client'). Era o cenário que estourava "Ocorreu um erro
 * interno" em produção.
 *
 * Em vez de exercitar o controller (HTTP/sessão), reproduzimos fielmente a
 * sequência de escritas que TicketsController::store() faz contra o banco:
 *   1) INSERT em tickets (Ticket::create)
 *   2) PlanningCard::createFromTicket (deriva company_id do criador = NULL)
 *   3) TicketNotificationService::notifyNewTicket (notificações)
 *
 * O objetivo é garantir que criar demanda tendo um developer como client_id
 * não lança exceção e persiste corretamente.
 */
final class TicketCreateByDeveloperTest extends TestCase
{
    private Database $db;
    private Ticket $ticket;
    private int $developerId;
    private int $attendantId;
    private int $adminId;
    /** @var int[] */
    private array $ticketIds = [];
    /** @var int[] */
    private array $cardIds = [];

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste (helpdesk_on_test) não configurado.');
        }
        $this->db = Database::getInstance();
        $this->ticket = new Ticket();

        $u = uniqid();
        // Developer SEM company_id (equipe interna), como no sistema real.
        $this->developerId = $this->novoUsuario("Dev {$u}", "dev_{$u}@example.test", 'developer');
        $this->attendantId = $this->novoUsuario("Atend {$u}", "att_{$u}@example.test", 'attendant');
        $this->adminId     = $this->novoUsuario("Admin {$u}", "adm_{$u}@example.test", 'super_admin');
    }

    protected function tearDown(): void
    {
        foreach ($this->cardIds as $id) {
            try { $this->db->delete('planning_cards', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
        foreach ($this->ticketIds as $id) {
            try { $this->db->delete('notifications', 'ticket_id = ?', [$id]); } catch (\Throwable $e) {}
            try { $this->db->delete('ticket_attendants', 'ticket_id = ?', [$id]); } catch (\Throwable $e) {}
            try { $this->db->delete('tickets', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
        foreach ([$this->developerId, $this->attendantId, $this->adminId] as $id) {
            try { $this->db->delete('notifications', 'user_id = ?', [$id]); } catch (\Throwable $e) {}
            try { $this->db->delete('users', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
    }

    private function novoUsuario(string $name, string $email, string $role): int
    {
        return (int) $this->db->insert('users', [
            'name' => $name, 'email' => $email,
            'password' => password_hash('x', PASSWORD_BCRYPT), 'role' => $role,
            'is_active' => 1,
        ]);
    }

    /** Replica o cálculo do número sequencial feito no store(). */
    private function proximoNumero(int $clientId): int
    {
        $last = $this->db->fetch(
            "SELECT MAX(client_ticket_number) AS last_num FROM tickets WHERE client_id = ?",
            [$clientId]
        );
        return ((int)($last['last_num'] ?? 0)) + 1;
    }

    public function testDeveloperCriaDemandaSemClienteNaoQuebra(): void
    {
        // client_id = próprio developer (nenhum cliente selecionado).
        $ticketData = [
            'client_id' => $this->developerId,
            'title' => 'Demanda criada por developer',
            'description' => 'Conteúdo da demanda',
            'category' => 'desenvolvimento',
            'priority' => 'medium',
            'status' => 'open',
            'client_ticket_number' => $this->proximoNumero($this->developerId),
        ];

        $ticketId = (int) $this->ticket->create($ticketData);
        $this->ticketIds[] = $ticketId;
        $this->assertGreaterThan(0, $ticketId, 'Ticket deveria ser criado com client_id = developer.');

        // Card de planejamento (company_id derivado = NULL para developer sem empresa).
        $card = new PlanningCard();
        $created = $this->ticket->findById($ticketId);
        $cardId = (int) $card->createFromTicket($created);
        $this->cardIds[] = $cardId;
        $this->assertGreaterThan(0, $cardId, 'Card deveria ser criado a partir do ticket.');

        $row = $this->db->fetch("SELECT company_id, created_by FROM planning_cards WHERE id = ?", [$cardId]);
        $this->assertNull($row['company_id'], 'company_id deve ser NULL quando o criador não tem empresa.');
        $this->assertSame($this->developerId, (int)$row['created_by']);

        // Notificações (não deve lançar; cria linhas para admin/atendentes).
        (new TicketNotificationService())->notifyNewTicket($ticketId);

        $notif = $this->db->fetch(
            "SELECT COUNT(*) AS c FROM notifications WHERE ticket_id = ?",
            [$ticketId]
        );
        $this->assertGreaterThan(0, (int)$notif['c'], 'Deveria gerar ao menos uma notificação.');

        // Reproduz o passo que estourava o 500 em produção: após o store(), o
        // redirect para tickets/show chama getRelations(). Deve retornar uma
        // lista (vazia para uma demanda nova) sem lançar.
        $relations = $this->ticket->getRelations($ticketId);
        $this->assertIsArray($relations, 'getRelations deve retornar array.');
        $this->assertCount(0, $relations, 'Demanda nova não tem relações.');
    }

    /**
     * Garante a degradação graciosa de getRelations quando a tabela
     * ticket_relations não existe (migration 154 não aplicada no ambiente):
     * deve retornar [] em vez de propagar PDOException (que virava 500 ao
     * visualizar a demanda logo após criá-la).
     */
    public function testGetRelationsSemTabelaNaoQuebra(): void
    {
        $hasTable = $this->db->fetch(
            "SELECT COUNT(*) AS c FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ticket_relations'"
        );
        if ((int)($hasTable['c'] ?? 0) === 0) {
            // Ambiente já sem a tabela: basta provar que não lança.
            $this->assertSame([], $this->ticket->getRelations(999999));
            return;
        }

        // Ambiente COM a tabela: renomeia temporariamente para simular a ausência,
        // valida a degradação, e restaura ao final (sempre).
        try {
            $this->db->query("RENAME TABLE ticket_relations TO ticket_relations_bkp_test");
            $this->assertSame([], $this->ticket->getRelations(999999),
                'Sem a tabela, getRelations deve retornar [] (degradação graciosa).');
        } finally {
            try { $this->db->query("RENAME TABLE ticket_relations_bkp_test TO ticket_relations"); } catch (\Throwable $e) {}
        }
    }

    public function testDeveloperCriaDemandaParaClienteSelecionado(): void
    {
        // Cliente real selecionado pelo developer.
        $u = uniqid();
        $clientId = $this->novoUsuario("Cli {$u}", "cli_{$u}@example.test", 'client');

        try {
            $ticketData = [
                'client_id' => $clientId,
                'title' => 'Demanda para cliente',
                'description' => 'Conteúdo',
                'category' => 'suporte',
                'priority' => 'high',
                'status' => 'open',
                'attendant_id' => $this->attendantId,
                'technical_responsible_id' => $this->developerId,
                'client_ticket_number' => $this->proximoNumero($clientId),
            ];

            $ticketId = (int) $this->ticket->create($ticketData);
            $this->ticketIds[] = $ticketId;
            $this->assertGreaterThan(0, $ticketId);

            $stored = $this->ticket->findById($ticketId);
            $this->assertSame($this->developerId, (int)$stored['technical_responsible_id']);
            $this->assertSame($this->attendantId, (int)$stored['attendant_id']);
        } finally {
            try { $this->db->delete('users', 'id = ?', [$clientId]); } catch (\Throwable $e) {}
        }
    }
}
