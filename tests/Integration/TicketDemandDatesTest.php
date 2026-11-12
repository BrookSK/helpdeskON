<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Ticket;
use Database;
use DemandDateRules;

/**
 * Datas da demanda (data_inicio, data_termino, data_prevista_entrega):
 *  - criada pelo cliente: grava sem datas (NULL), mesmo que o POST traga datas;
 *  - criada pela equipe: grava as três datas informadas;
 *  - edição pela equipe (saveDates): grava, altera e limpa as datas.
 * Reproduz a montagem de dados de TicketsController::store()/saveDates().
 */
final class TicketDemandDatesTest extends TestCase
{
    private Database $db;
    private Ticket $ticket;
    private int $clientId;
    private int $adminId;
    /** @var int[] */
    private array $ticketIds = [];

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste (helpdesk_on_test) não configurado.');
        }
        $this->db = Database::getInstance();
        $this->ticket = new Ticket();
        $u = uniqid();
        $this->clientId = $this->novoUsuario("Cli {$u}", "cli_dd_{$u}@example.test", 'client');
        $this->adminId  = $this->novoUsuario("Adm {$u}", "adm_dd_{$u}@example.test", 'super_admin');
    }

    protected function tearDown(): void
    {
        foreach ($this->ticketIds as $id) {
            try { $this->db->delete('tickets', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
        foreach ([$this->clientId, $this->adminId] as $id) {
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

    /** Monta e grava a demanda como o store() faz. */
    private function criar(string $role, int $clientId, array $post): int
    {
        $dates = DemandDateRules::forCreate($role, $post);
        $this->assertTrue($dates['ok'], (string)$dates['error']);
        $id = (int) $this->ticket->create([
            'client_id' => $clientId,
            'title' => 'Demanda datas',
            'description' => 'Conteúdo',
            'category' => 'desenvolvimento',
            'priority' => 'medium',
            'status' => 'open',
            'client_ticket_number' => 1,
        ] + $dates['data']);
        $this->ticketIds[] = $id;
        return $id;
    }

    private function datas(int $id): array
    {
        return $this->db->fetch(
            "SELECT data_inicio, data_termino, data_prevista_entrega FROM tickets WHERE id = ?",
            [$id]
        );
    }

    public function testClienteCriaDemandaSemDatasMesmoForjandoPost(): void
    {
        $id = $this->criar('client', $this->clientId, [
            'data_inicio' => '2026-11-10',
            'data_termino' => '2026-11-20',
            'data_prevista_entrega' => '2026-11-25',
        ]);
        $this->assertSame(
            ['data_inicio' => null, 'data_termino' => null, 'data_prevista_entrega' => null],
            $this->datas($id)
        );
    }

    public function testEquipeCriaDemandaComAsTresDatas(): void
    {
        $id = $this->criar('super_admin', $this->clientId, [
            'data_inicio' => '2026-11-10',
            'data_termino' => '2026-11-20',
            'data_prevista_entrega' => '2026-11-25',
        ]);
        $this->assertSame(
            ['data_inicio' => '2026-11-10', 'data_termino' => '2026-11-20', 'data_prevista_entrega' => '2026-11-25'],
            $this->datas($id)
        );
    }

    public function testEquipeDefineAlteraELimpaDatasDepois(): void
    {
        $id = $this->criar('client', $this->clientId, []);

        // Equipe define as datas.
        $p = DemandDateRules::parse([
            'data_inicio' => '2026-12-01', 'data_termino' => '2026-12-10', 'data_prevista_entrega' => '2026-12-15',
        ], false);
        $this->assertTrue($p['ok']);
        $this->ticket->update($id, $p['data']);
        $this->assertSame(
            ['data_inicio' => '2026-12-01', 'data_termino' => '2026-12-10', 'data_prevista_entrega' => '2026-12-15'],
            $this->datas($id)
        );

        // Altera uma e limpa outra.
        $p = DemandDateRules::parse([
            'data_inicio' => '2026-12-01', 'data_termino' => '', 'data_prevista_entrega' => '2026-12-20',
        ], false);
        $this->ticket->update($id, $p['data']);
        $this->assertSame(
            ['data_inicio' => '2026-12-01', 'data_termino' => null, 'data_prevista_entrega' => '2026-12-20'],
            $this->datas($id)
        );

        // Data inválida não é gravada (o controller para antes do update).
        $p = DemandDateRules::parse(['data_inicio' => '2026-02-30'], false);
        $this->assertFalse($p['ok']);
        $this->assertSame('2026-12-01', $this->datas($id)['data_inicio']);
    }
}
