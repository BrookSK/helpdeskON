<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use PlanningCard;
use Database;

/**
 * Teste de integração do model PlanningCard contra o banco helpdesk_on_test.
 *
 * Valida o ciclo básico de um card e a transição pelo fluxo de homologação
 * real do projeto (em_homologacao -> aprovado_producao -> completed).
 *
 * Estratégia de dados: cria um usuário de teste no setUp (created_by é NOT NULL
 * com FK para users) e o remove no tearDown. Como a FK created_by tem
 * ON DELETE CASCADE, apagar o usuário remove automaticamente os cards criados.
 */
final class PlanningCardTest extends TestCase
{
    private PlanningCard $cards;
    private Database $db;
    private int $userId;

    protected function setUp(): void
    {
        // Trava de segurança: só roda contra o banco de teste.
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste (helpdesk_on_test) não configurado.');
        }

        $this->db = Database::getInstance();
        $this->cards = new PlanningCard();

        // Usuário dono dos cards de teste (email único por execução)
        $this->userId = (int) $this->db->insert('users', [
            'name'     => 'Usuario Teste',
            'email'    => 'teste_' . uniqid() . '@example.test',
            'password' => password_hash('x', PASSWORD_BCRYPT),
            'role'     => 'attendant',
        ]);
    }

    protected function tearDown(): void
    {
        // Remove o usuário; cards vão junto via ON DELETE CASCADE.
        try {
            $this->db->delete('users', 'id = ?', [$this->userId]);
        } catch (\Throwable $e) {
            // ignora falha de limpeza
        }
    }

    private function novoCard(array $overrides = []): int
    {
        return (int) $this->cards->create(array_merge([
            'title'      => 'Card de teste',
            'status'     => 'open',
            'priority'   => 'medium',
            'created_by' => $this->userId,
            'position'   => 0,
        ], $overrides));
    }

    public function testCriarCardGravaComStatusInicial(): void
    {
        $id = $this->novoCard(['title' => 'Card criado no teste']);

        $this->assertGreaterThan(0, $id);

        $card = $this->cards->findById($id);
        $this->assertNotEmpty($card);
        $this->assertSame('Card criado no teste', $card['title']);
        $this->assertSame('open', $card['status']);
    }

    public function testFluxoDeHomologacaoAteConcluido(): void
    {
        $id = $this->novoCard(['status' => 'in_progress', 'priority' => 'high']);

        // Enviar para homologação
        $this->cards->updateStatus($id, 'em_homologacao');
        $this->assertSame('em_homologacao', $this->cards->findById($id)['status']);

        // Aprovar para produção
        $this->cards->updateStatus($id, 'aprovado_producao');
        $this->assertSame('aprovado_producao', $this->cards->findById($id)['status']);

        // Concluir
        $this->cards->updateStatus($id, 'completed');
        $this->assertSame('completed', $this->cards->findById($id)['status']);
    }

    public function testAtualizarCamposDoCard(): void
    {
        $id = $this->novoCard();

        $this->cards->update($id, ['title' => 'Título atualizado', 'priority' => 'urgent']);

        $card = $this->cards->findById($id);
        $this->assertSame('Título atualizado', $card['title']);
        $this->assertSame('urgent', $card['priority']);
    }

    public function testDeletarCardRemoveDoBanco(): void
    {
        $id = $this->novoCard();
        $this->assertNotEmpty($this->cards->findById($id));

        $this->cards->delete($id);

        $this->assertEmpty($this->cards->findById($id) ?: []);
    }
}
