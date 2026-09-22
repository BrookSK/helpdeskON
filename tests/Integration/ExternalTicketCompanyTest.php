<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use PlanningCard;
use Ticket;
use Database;

/**
 * Integração do vínculo de empresa na demanda externa (contra helpdesk_on_test).
 *
 * Regra: no fluxo de solicitação externa o "cliente" do ticket é o atendente
 * dono do PIN. A empresa que aparece na listagem "Todas as Demandas" vem do
 * planning_card (co.name via planning_cards.company_id). Portanto, para a
 * empresa selecionada refletir na coluna, createFromTicket() deve gravar o
 * company_id da empresa ESCOLHIDA quando ela existe no cadastro; para "Outra"
 * (sem override), mantém o comportamento antigo (empresa do usuário cliente).
 */
final class ExternalTicketCompanyTest extends TestCase
{
    private Database $db;
    private PlanningCard $cards;
    private Ticket $tickets;
    private int $ownerId;
    private int $companyOwnerId;   // empresa do atendente (fallback antigo)
    private int $companySelectedId; // empresa escolhida pelo solicitante

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste (helpdesk_on_test) não configurado.');
        }

        $this->db = Database::getInstance();
        $this->cards = new PlanningCard();
        $this->tickets = new Ticket();

        $this->companyOwnerId = (int) $this->db->insert('companies', ['name' => 'Empresa do Atendente ' . uniqid()]);
        $this->companySelectedId = (int) $this->db->insert('companies', ['name' => 'Empresa Selecionada ' . uniqid()]);

        // Atendente dono do PIN, vinculado à "empresa do atendente".
        $this->ownerId = (int) $this->db->insert('users', [
            'name'       => 'Atendente PIN',
            'email'      => 'pin_' . uniqid() . '@example.test',
            'password'   => password_hash('x', PASSWORD_BCRYPT),
            'role'       => 'attendant',
            'company_id' => $this->companyOwnerId,
        ]);
    }

    protected function tearDown(): void
    {
        try {
            $this->db->delete('planning_cards', 'created_by = ?', [$this->ownerId]);
            $this->db->delete('tickets', 'client_id = ?', [$this->ownerId]);
            $this->db->delete('users', 'id = ?', [$this->ownerId]);
            $this->db->delete('companies', 'id = ?', [$this->companyOwnerId]);
            $this->db->delete('companies', 'id = ?', [$this->companySelectedId]);
        } catch (\Throwable $e) {
            // ignora limpeza
        }
    }

    private function criarTicket(): array
    {
        $id = (int) $this->tickets->create([
            'client_id'            => $this->ownerId,
            'attendant_id'         => $this->ownerId,
            'client_ticket_number' => 1,
            'title'                => 'Demanda externa',
            'description'          => 'Solicitado por (externo): Cliente',
            'priority'             => 'medium',
            'status'               => 'open',
        ]);
        return $this->tickets->findById($id);
    }

    public function testComEmpresaSelecionadaVinculaAquelaEmpresaNoCard(): void
    {
        $ticket = $this->criarTicket();

        // Override = empresa escolhida pelo solicitante (existe no cadastro).
        $cardId = (int) $this->cards->createFromTicket($ticket, $this->companySelectedId);

        $card = $this->cards->findById($cardId);
        $this->assertSame($this->companySelectedId, (int) $card['company_id']);
    }

    public function testSemOverrideMantemEmpresaDoCliente(): void
    {
        $ticket = $this->criarTicket();

        // Sem override (ex.: "Outra" ou nenhuma empresa): comportamento antigo,
        // usa a empresa do usuário cliente (aqui, o atendente).
        $cardId = (int) $this->cards->createFromTicket($ticket);

        $card = $this->cards->findById($cardId);
        $this->assertSame($this->companyOwnerId, (int) $card['company_id']);
    }
}
