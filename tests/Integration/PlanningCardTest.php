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
    private int $companyId;

    protected function setUp(): void
    {
        // Trava de segurança: só roda contra o banco de teste.
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste (helpdesk_on_test) não configurado.');
        }

        $this->db = Database::getInstance();
        $this->cards = new PlanningCard();

        // Empresa do cliente (usada nos testes de cronograma)
        $this->companyId = (int) $this->db->insert('companies', [
            'name' => 'Empresa Teste ' . uniqid(),
        ]);

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
        // Remove os cards vinculados à empresa antes de apagá-la (evita FK).
        try {
            $this->db->delete('planning_cards', 'company_id = ?', [$this->companyId]);
            $this->db->delete('companies', 'id = ?', [$this->companyId]);
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

    public function testCronogramaDoClienteGravaERecuperaDatas(): void
    {
        $id = $this->novoCard([
            'title'             => 'Demanda com cronograma',
            'company_id'        => $this->companyId,
            'client_start_date' => '2026-01-10',
            'client_end_date'   => '2026-01-20',
        ]);

        // As datas são persistidas e recuperadas pelo findById (SELECT pc.*).
        $card = $this->cards->findById($id);
        $this->assertStringStartsWith('2026-01-10', (string) $card['client_start_date']);
        $this->assertStringStartsWith('2026-01-20', (string) $card['client_end_date']);
    }

    public function testGetClientScheduleRetornaSomenteCardsComCronograma(): void
    {
        // Card COM cronograma: deve aparecer.
        $comCronograma = $this->novoCard([
            'title'             => 'Com cronograma',
            'company_id'        => $this->companyId,
            'client_start_date' => '2026-02-01',
            'client_end_date'   => '2026-02-05',
        ]);

        // Card SEM cronograma (client_start_date NULL): não deve aparecer.
        $this->novoCard([
            'title'      => 'Sem cronograma',
            'company_id' => $this->companyId,
        ]);

        $schedule = $this->cards->getClientSchedule($this->companyId);

        $ids = array_column($schedule, 'id');
        $this->assertContains($comCronograma, array_map('intval', $ids));

        // Todos os itens retornados possuem client_start_date preenchido.
        foreach ($schedule as $row) {
            $this->assertNotEmpty($row['client_start_date']);
        }
    }

    public function testGetClientCardDetailRetornaDaPropriaEmpresa(): void
    {
        $id = $this->novoCard([
            'title'             => 'Detalhe visível',
            'company_id'        => $this->companyId,
            'client_start_date' => '2026-03-01',
            'client_end_date'   => '2026-03-10',
        ]);

        $detail = $this->cards->getClientCardDetail($id, $this->companyId);

        $this->assertNotEmpty($detail);
        $this->assertSame('Detalhe visível', $detail['title']);
        // Não deve expor campos internos sensíveis no SELECT.
        $this->assertArrayNotHasKey('technical_responsible_id', $detail);
        $this->assertArrayNotHasKey('cx_hub_number', $detail);
    }

    public function testGetClientCardDetailBloqueiaOutraEmpresa(): void
    {
        $id = $this->novoCard([
            'title'      => 'Card de outra empresa',
            'company_id' => $this->companyId,
            'client_start_date' => '2026-04-01',
        ]);

        // Empresa diferente da dona do card não deve conseguir ver o detalhe.
        $outraEmpresa = (int) $this->db->insert('companies', ['name' => 'Outra ' . uniqid()]);
        try {
            $detail = $this->cards->getClientCardDetail($id, $outraEmpresa);
            $this->assertEmpty($detail ?: []);
        } finally {
            $this->db->delete('companies', 'id = ?', [$outraEmpresa]);
        }
    }

    /**
     * Helper: coleta todos os IDs de cards retornados por getGroupedByStatus,
     * achatando o agrupamento por status.
     */
    private function idsAgrupados(array $filters): array
    {
        $grouped = $this->cards->getGroupedByStatus($filters);
        $ids = [];
        foreach ($grouped as $cards) {
            foreach ($cards as $c) {
                $ids[] = (int) $c['id'];
            }
        }
        return $ids;
    }

    public function testFiltroSemResponsavelTrazSomenteCardsSemDono(): void
    {
        // Um card COM responsável (o próprio usuário de teste) e um SEM.
        $comDono = $this->novoCard(['title' => 'Com dono', 'assigned_to' => $this->userId]);
        $semDono = $this->novoCard(['title' => 'Sem dono', 'assigned_to' => null]);

        // Filtro "sem responsável" (valor especial 'none').
        $ids = $this->idsAgrupados(['assigned_to' => ['none']]);

        $this->assertContains($semDono, $ids, 'Card sem responsável deveria aparecer.');
        $this->assertNotContains($comDono, $ids, 'Card com responsável não deveria aparecer.');
    }

    public function testFiltroCombinaResponsavelEeSemResponsavel(): void
    {
        $comDono = $this->novoCard(['title' => 'Com dono', 'assigned_to' => $this->userId]);
        $semDono = $this->novoCard(['title' => 'Sem dono', 'assigned_to' => null]);

        // Seleciona o usuário E "sem responsável": deve trazer os dois.
        $ids = $this->idsAgrupados(['assigned_to' => [$this->userId, 'none']]);

        $this->assertContains($comDono, $ids);
        $this->assertContains($semDono, $ids);
    }

    /**
     * Helper: coleta os IDs de cards retornados pelo calendário num range amplo.
     */
    private function idsCalendario(string $start, string $end, array $filters = []): array
    {
        $cards = $this->cards->getForCalendar($start, $end, $filters);
        return array_map(fn($c) => (int) $c['id'], $cards);
    }

    public function testCalendarioNaoMostraCardArquivadoComPrazo(): void
    {
        // Card ATIVO com prazo dentro do range: deve aparecer.
        $ativo = $this->novoCard([
            'title'    => 'Ativo com prazo',
            'status'   => 'in_progress',
            'due_date' => '2026-05-15 10:00:00',
        ]);

        // Card ARQUIVADO com o MESMO prazo: não deve aparecer, apesar da data.
        $arquivado = $this->novoCard([
            'title'    => 'Arquivado com prazo',
            'status'   => 'archived',
            'due_date' => '2026-05-15 10:00:00',
        ]);

        $ids = $this->idsCalendario('2026-05-01 00:00:00', '2026-05-31 23:59:59');

        $this->assertContains($ativo, $ids, 'Card ativo com prazo deveria aparecer no calendário.');
        $this->assertNotContains($arquivado, $ids, 'Card arquivado não deveria aparecer no calendário.');
    }

    public function testCalendarioNaoMostraCardArquivadoComJanelaDeDesenvolvimento(): void
    {
        // Card arquivado com start_date/end_date (janela de desenvolvimento)
        // que intersecta o range também deve ser ocultado.
        $arquivado = $this->novoCard([
            'title'      => 'Arquivado com janela',
            'status'     => 'archived',
            'start_date' => '2026-06-05 09:00:00',
            'end_date'   => '2026-06-10 18:00:00',
        ]);

        $ids = $this->idsCalendario('2026-06-01 00:00:00', '2026-06-30 23:59:59');

        $this->assertNotContains($arquivado, $ids, 'Card arquivado com janela de desenvolvimento não deveria aparecer no calendário.');
    }

    public function testCalendarioAindaMostraCardConcluidoComPrazo(): void
    {
        // A correção mira SÓ em arquivadas. Concluídas continuam visíveis.
        $concluido = $this->novoCard([
            'title'    => 'Concluido com prazo',
            'status'   => 'completed',
            'due_date' => '2026-07-15 10:00:00',
        ]);

        $ids = $this->idsCalendario('2026-07-01 00:00:00', '2026-07-31 23:59:59');

        $this->assertContains($concluido, $ids, 'Card concluído com prazo deveria continuar aparecendo no calendário.');
    }

    public function testBuscaNoKanbanFiltraPorTituloEDescricao(): void
    {
        $alvo = $this->novoCard([
            'title'       => 'Implementar relatorio financeiro',
            'description' => '<p>Gerar PDF com totais</p>',
        ]);
        $porDescricao = $this->novoCard([
            'title'       => 'Tarefa qualquer',
            'description' => '<p>Ajustar o relatorio mensal do cliente</p>',
        ]);
        $foraDaBusca = $this->novoCard([
            'title'       => 'Configurar servidor',
            'description' => '<p>Deploy em producao</p>',
        ]);

        // Busca por um termo presente no título de um e na descrição de outro.
        $ids = $this->idsAgrupados(['q' => 'relatorio']);

        $this->assertContains($alvo, $ids, 'Deve achar pelo título.');
        $this->assertContains($porDescricao, $ids, 'Deve achar pela descrição.');
        $this->assertNotContains($foraDaBusca, $ids, 'Card sem o termo não deve aparecer.');
    }

    public function testBuscaNoCalendarioFiltraPorTituloEDescricao(): void
    {
        $alvo = $this->novoCard([
            'title'    => 'Migracao de banco',
            'status'   => 'in_progress',
            'due_date' => '2026-08-12 10:00:00',
        ]);
        $outro = $this->novoCard([
            'title'    => 'Reuniao de alinhamento',
            'status'   => 'in_progress',
            'due_date' => '2026-08-13 10:00:00',
        ]);

        $ids = $this->idsCalendario('2026-08-01 00:00:00', '2026-08-31 23:59:59', ['q' => 'Migracao']);

        $this->assertContains($alvo, $ids, 'Card com o termo deve aparecer no calendário.');
        $this->assertNotContains($outro, $ids, 'Card sem o termo não deve aparecer no calendário.');
    }
}
