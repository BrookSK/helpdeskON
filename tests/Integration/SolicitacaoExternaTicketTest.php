<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Ticket;
use Database;

/**
 * Teste de integração da criação de demanda via acesso externo (demanda #239),
 * contra o banco helpdesk_on_test.
 *
 * Foca no comportamento que o SolicitacaoexternaController::store() implementa
 * no banco: numeração sequencial por "cliente" (o usuário dono do PIN) e a
 * gravação do ticket. Não exercita a camada de sessão/HTTP nem a notificação
 * de WhatsApp (canal complementar, coberto por unit no lado da montagem).
 *
 * Estratégia de dados: cria o usuário dono do PIN no setUp e o remove no
 * tearDown. A FK tickets.client_id -> users.id é ON DELETE CASCADE, então os
 * tickets criados somem junto com o usuário.
 */
final class SolicitacaoExternaTicketTest extends TestCase
{
    private Database $db;
    private Ticket $tickets;
    private int $ownerId;

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste (helpdesk_on_test) não configurado.');
        }

        $this->db = Database::getInstance();
        $this->tickets = new Ticket();

        // Usuário da equipe dono do PIN (em nome de quem a demanda é aberta).
        $this->ownerId = (int) $this->db->insert('users', [
            'name'         => 'Atendente PIN',
            'email'        => 'pin_' . uniqid() . '@example.test',
            'password'     => password_hash('x', PASSWORD_BCRYPT),
            'role'         => 'attendant',
            'external_pin' => substr((string) mt_rand(1000, 9999), 0, 4),
        ]);
    }

    protected function tearDown(): void
    {
        try {
            // Remove tickets do owner e depois o owner (cascade cobre, mas
            // deixamos explícito para não depender só da FK).
            $this->db->delete('tickets', 'client_id = ?', [$this->ownerId]);
            $this->db->delete('users', 'id = ?', [$this->ownerId]);
        } catch (\Throwable $e) {
            // ignora falha de limpeza
        }
    }

    /**
     * Replica a numeração que o store() faz: MAX(client_ticket_number)+1 por
     * client_id. Mantida no teste para validar o cálculo de forma isolada.
     */
    private function proximoNumero(int $clientId): int
    {
        $row = $this->db->fetch(
            "SELECT MAX(client_ticket_number) AS last_num FROM tickets WHERE client_id = ?",
            [$clientId]
        );
        return ($row['last_num'] ?? 0) + 1;
    }

    private function criarDemanda(array $overrides = []): int
    {
        $number = $this->proximoNumero($this->ownerId);
        return (int) $this->tickets->create(array_merge([
            'client_id'            => $this->ownerId,
            'attendant_id'         => $this->ownerId,
            'client_ticket_number' => $number,
            'title'                => 'Demanda externa',
            'description'          => 'Solicitado por (externo): Cliente Teste',
            'priority'             => 'medium',
            'status'               => 'open',
        ], $overrides));
    }

    public function testPrimeiraDemandaRecebeNumeroUm(): void
    {
        $id = $this->criarDemanda();
        $this->assertGreaterThan(0, $id);

        $ticket = $this->tickets->findById($id);
        $this->assertSame(1, (int) $ticket['client_ticket_number']);
        $this->assertSame('open', $ticket['status']);
        $this->assertSame($this->ownerId, (int) $ticket['client_id']);
    }

    public function testNumeracaoEhSequencialPorCliente(): void
    {
        $id1 = $this->criarDemanda(['title' => 'Primeira']);
        $id2 = $this->criarDemanda(['title' => 'Segunda']);
        $id3 = $this->criarDemanda(['title' => 'Terceira']);

        $this->assertSame(1, (int) $this->tickets->findById($id1)['client_ticket_number']);
        $this->assertSame(2, (int) $this->tickets->findById($id2)['client_ticket_number']);
        $this->assertSame(3, (int) $this->tickets->findById($id3)['client_ticket_number']);
    }

    public function testDadosDoSolicitanteVaoNaDescricao(): void
    {
        // O store() antepõe "Solicitado por (externo): <nome>" à descrição.
        $id = $this->criarDemanda([
            'description' => "Solicitado por (externo): Maria\nEmpresa vinculada: ACME\n\nPreciso de ajuda.",
        ]);

        $ticket = $this->tickets->findById($id);
        $this->assertStringContainsString('Solicitado por (externo): Maria', $ticket['description']);
        $this->assertStringContainsString('Empresa vinculada: ACME', $ticket['description']);
    }

    public function testDemandaExternaExibeNomeDoSolicitanteComoCliente(): void
    {
        // Com external_requester_name preenchido, o client_name retornado deve
        // ser o nome do solicitante externo (não o nome do dono do PIN).
        $id = $this->criarDemanda([
            'is_external'             => 1,
            'external_requester_name' => 'Maria Solicitante',
        ]);

        $ticket = $this->tickets->findById($id);
        $this->assertSame('Maria Solicitante', $ticket['client_name']);
        // O dono real da conta (dono do PIN) continua acessível em separado.
        $this->assertSame('Atendente PIN', $ticket['account_owner_name']);
        $this->assertSame(1, (int) $ticket['is_external']);
    }

    public function testDemandaComumMantemNomeDoUsuarioComoCliente(): void
    {
        // Sem external_requester_name, o client_name volta a ser o nome do
        // usuário vinculado (comportamento anterior preservado).
        $id = $this->criarDemanda();

        $ticket = $this->tickets->findById($id);
        $this->assertSame('Atendente PIN', $ticket['client_name']);
        $this->assertSame(0, (int) $ticket['is_external']);
    }

    public function testExternalRequesterNameVazioNaoSobrescreveCliente(): void
    {
        // String vazia deve ser tratada como "sem solicitante externo" (NULLIF),
        // então o client_name cai no fallback para o nome do usuário.
        $id = $this->criarDemanda([
            'is_external'             => 1,
            'external_requester_name' => '',
        ]);

        $ticket = $this->tickets->findById($id);
        $this->assertSame('Atendente PIN', $ticket['client_name']);
    }

    public function testDemandaExternaApareceNaListagemComNomeDoSolicitante(): void
    {
        // getAll() também deve refletir o nome do solicitante externo.
        $id = $this->criarDemanda([
            'is_external'             => 1,
            'external_requester_name' => 'Joao Externo',
        ]);

        $all = $this->tickets->getAll();
        $found = null;
        foreach ($all as $t) {
            if ((int) $t['id'] === $id) { $found = $t; break; }
        }
        $this->assertNotNull($found, 'Demanda criada não encontrada em getAll().');
        $this->assertSame('Joao Externo', $found['client_name']);
    }
}
