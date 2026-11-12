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

    // ================= Atividade predecessora =================

    public function testPredecessorIdEhPersistido(): void
    {
        $muro = $this->novoCard(['title' => 'Construir muro']);
        $emassar = $this->novoCard([
            'title'          => 'Emassar muro',
            'predecessor_id' => $muro,
            'predecessor_lag_days' => 2,
        ]);

        $card = $this->cards->findById($emassar);
        $this->assertSame($muro, (int) $card['predecessor_id']);
        $this->assertSame(2, (int) $card['predecessor_lag_days']);
    }

    public function testGetDependentsTrazSomenteOsQueDependem(): void
    {
        $muro = $this->novoCard(['title' => 'Construir muro']);
        $emassar = $this->novoCard(['title' => 'Emassar', 'predecessor_id' => $muro]);
        $pintar = $this->novoCard(['title' => 'Pintar', 'predecessor_id' => $emassar]);

        $depsDoMuro = array_map(fn($c) => (int) $c['id'], $this->cards->getDependents($muro));
        $this->assertContains($emassar, $depsDoMuro);
        $this->assertNotContains($pintar, $depsDoMuro, 'Pintar depende de emassar, não do muro (dependência direta).');
    }

    public function testGetSelectableCardsExcluiOProprioEOsEncerrados(): void
    {
        $a = $this->novoCard(['title' => 'Atividade A', 'status' => 'in_progress']);
        $concluido = $this->novoCard(['title' => 'Já concluída', 'status' => 'completed']);

        $ids = array_map(fn($c) => (int) $c['id'], $this->cards->getSelectableCards($a));
        $this->assertNotContains($a, $ids, 'Não pode escolher a si mesmo como predecessor.');
        $this->assertNotContains($concluido, $ids, 'Card concluído não deve ser selecionável.');
    }

    public function testCascataDeslocaDependenteQuandoPredecessorAtrasa(): void
    {
        // Muro termina 10/01; emassar (2 dias) começa depois; prazo 31/01.
        $muro = $this->novoCard([
            'title'      => 'Construir muro',
            'start_date' => '2026-01-05 08:00:00',
            'end_date'   => '2026-01-10 18:00:00',
        ]);
        $emassar = $this->novoCard([
            'title'          => 'Emassar muro',
            'predecessor_id' => $muro,
            'start_date'     => '2026-01-11 08:00:00',
            'end_date'       => '2026-01-13 08:00:00', // 2 dias
            'due_date'       => '2026-01-31 23:59:00',
        ]);

        // Muro atrasa: novo fim 20/01. A cascata deve reagendar o emassar.
        $this->cards->update($muro, ['end_date' => '2026-01-20 18:00:00']);
        $afetados = $this->cards->cascadeRecalcDependents($muro);

        $this->assertNotEmpty($afetados, 'A cascata deveria ter afetado o dependente.');
        $dep = $this->cards->findById($emassar);
        // Começa no fim do predecessor (20/01 18:00) e mantém ~2 dias.
        $this->assertStringStartsWith('2026-01-20 18:00', (string) $dep['start_date']);
        $this->assertStringStartsWith('2026-01-22 18:00', (string) $dep['end_date']);
    }

    public function testCascataRespeitaPrazoFinalDoDependente(): void
    {
        // Muro atrasa MUITO (termina 30/01), mas o emassar tem prazo final 31/01.
        $muro = $this->novoCard([
            'title'    => 'Construir muro',
            'end_date' => '2026-01-10 00:00:00',
        ]);
        $emassar = $this->novoCard([
            'title'          => 'Emassar muro',
            'predecessor_id' => $muro,
            'start_date'     => '2026-01-11 00:00:00',
            'end_date'       => '2026-01-16 00:00:00', // 5 dias
            'due_date'       => '2026-01-31 00:00:00', // prazo final
        ]);

        $this->cards->update($muro, ['end_date' => '2026-01-30 00:00:00']);
        $this->cards->cascadeRecalcDependents($muro);

        $dep = $this->cards->findById($emassar);
        // O fim não pode passar do prazo final (31/01).
        $this->assertLessThanOrEqual(
            strtotime('2026-01-31 00:00:00'),
            strtotime((string) $dep['end_date']),
            'A cascata não pode ultrapassar o prazo final do dependente.'
        );
    }

    public function testCascataEmCadeiaDeTresAtividades(): void
    {
        // muro -> emassar -> pintar. Atrasar o muro empurra os dois seguintes.
        $muro = $this->novoCard([
            'title'    => 'Construir muro',
            'end_date' => '2026-01-10 00:00:00',
        ]);
        $emassar = $this->novoCard([
            'title'          => 'Emassar',
            'predecessor_id' => $muro,
            'start_date'     => '2026-01-11 00:00:00',
            'end_date'       => '2026-01-13 00:00:00', // 2 dias
            'due_date'       => '2026-03-01 00:00:00',
        ]);
        $pintar = $this->novoCard([
            'title'          => 'Pintar',
            'predecessor_id' => $emassar,
            'start_date'     => '2026-01-14 00:00:00',
            'end_date'       => '2026-01-15 00:00:00', // 1 dia
            'due_date'       => '2026-03-01 00:00:00',
        ]);

        $this->cards->update($muro, ['end_date' => '2026-01-20 00:00:00']);
        $this->cards->cascadeRecalcDependents($muro);

        $emassarCard = $this->cards->findById($emassar);
        $pintarCard = $this->cards->findById($pintar);

        // Emassar começa quando o muro termina (20/01) e dura 2 dias → fim 22/01.
        $this->assertStringStartsWith('2026-01-20 00:00', (string) $emassarCard['start_date']);
        $this->assertStringStartsWith('2026-01-22 00:00', (string) $emassarCard['end_date']);
        // Pintar começa quando emassar termina (22/01) e dura 1 dia → fim 23/01.
        $this->assertStringStartsWith('2026-01-22 00:00', (string) $pintarCard['start_date']);
        $this->assertStringStartsWith('2026-01-23 00:00', (string) $pintarCard['end_date']);
    }

    // ================= Demandas sem data (aviso "Revisar") =================

    public function testGetUndatedTrazSomenteCardsAtivosSemData(): void
    {
        // Sem nenhuma data e ativo: deve aparecer.
        $semData = $this->novoCard(['title' => 'Sem data', 'status' => 'open']);
        // Com prazo: não aparece (tem data).
        $comPrazo = $this->novoCard(['title' => 'Com prazo', 'due_date' => '2026-05-10 10:00:00']);
        // Com janela de desenvolvimento: não aparece (tem data).
        $comJanela = $this->novoCard([
            'title'      => 'Com janela',
            'start_date' => '2026-05-01 09:00:00',
            'end_date'   => '2026-05-03 18:00:00',
        ]);

        $ids = array_map(fn($c) => (int) $c['id'], $this->cards->getUndated([]));

        $this->assertContains($semData, $ids, 'Card sem data deveria aparecer.');
        $this->assertNotContains($comPrazo, $ids, 'Card com prazo não deveria aparecer.');
        $this->assertNotContains($comJanela, $ids, 'Card com janela não deveria aparecer.');
    }

    public function testGetUndatedIgnoraEncerrados(): void
    {
        // Sem data mas já encerrados (concluído/negado/arquivado): não precisam
        // de agendamento, então não entram na revisão.
        $concluido = $this->novoCard(['title' => 'Concluido sem data', 'status' => 'completed']);
        $negado    = $this->novoCard(['title' => 'Negado sem data', 'status' => 'denied']);
        $arquivado = $this->novoCard(['title' => 'Arquivado sem data', 'status' => 'archived']);
        $aberto    = $this->novoCard(['title' => 'Aberto sem data', 'status' => 'open']);

        $ids = array_map(fn($c) => (int) $c['id'], $this->cards->getUndated([]));

        $this->assertContains($aberto, $ids);
        $this->assertNotContains($concluido, $ids);
        $this->assertNotContains($negado, $ids);
        $this->assertNotContains($arquivado, $ids);
    }

    public function testGetUndatedRespeitaFiltroSemResponsavel(): void
    {
        $semDono = $this->novoCard(['title' => 'Sem dono sem data', 'assigned_to' => null]);
        $comDono = $this->novoCard(['title' => 'Com dono sem data', 'assigned_to' => $this->userId]);

        $ids = array_map(fn($c) => (int) $c['id'], $this->cards->getUndated(['assigned_to' => ['none']]));

        $this->assertContains($semDono, $ids);
        $this->assertNotContains($comDono, $ids);
    }

    // ================= Paginação por coluna do Kanban =================

    public function testColumnCountsEContaTotalPorStatus(): void
    {
        // Cria 3 cards "open" desta empresa; conta deve refletir pelo menos eles.
        $a = $this->novoCard(['status' => 'open', 'company_id' => $this->companyId]);
        $b = $this->novoCard(['status' => 'open', 'company_id' => $this->companyId]);
        $c = $this->novoCard(['status' => 'open', 'company_id' => $this->companyId]);

        $counts = $this->cards->getColumnCounts(['company_id' => [$this->companyId]]);
        $this->assertArrayHasKey('open', $counts);
        $this->assertSame(3, (int) $counts['open'], 'Deve contar os 3 cards open da empresa.');
    }

    public function testGetColumnCardsPaginaComOffsetELimit(): void
    {
        // 5 cards open na empresa; pagina de 2 em 2.
        for ($i = 0; $i < 5; $i++) {
            $this->novoCard(['status' => 'open', 'company_id' => $this->companyId, 'title' => 'Pag ' . $i]);
        }
        $filtro = ['company_id' => [$this->companyId]];

        $pag1 = $this->cards->getColumnCards('open', $filtro, 0, 2);
        $pag2 = $this->cards->getColumnCards('open', $filtro, 2, 2);
        $pag3 = $this->cards->getColumnCards('open', $filtro, 4, 2);

        $this->assertCount(2, $pag1);
        $this->assertCount(2, $pag2);
        $this->assertCount(1, $pag3, 'A última página traz o card restante.');

        // Sem sobreposição entre as páginas (offsets distintos).
        $ids = array_merge(
            array_column($pag1, 'id'),
            array_column($pag2, 'id'),
            array_column($pag3, 'id')
        );
        $this->assertSame(count($ids), count(array_unique($ids)), 'Páginas não devem repetir cards.');
    }

    // ================= Busca de cards (autocomplete predecessor) =================

    public function testGetSelectableCardsBuscaPorTituloEExcluiProprio(): void
    {
        $alvo  = $this->novoCard(['title' => 'Construir muro ZZZ', 'status' => 'open', 'company_id' => $this->companyId]);
        $outro = $this->novoCard(['title' => 'Pintar portão', 'status' => 'open', 'company_id' => $this->companyId]);

        // Busca por "muro" acha o alvo e não o outro.
        $r = $this->cards->getSelectableCards(null, null, 'muro ZZZ', 30);
        $ids = array_map(fn($x) => (int) $x['id'], $r);
        $this->assertContains($alvo, $ids);
        $this->assertNotContains($outro, $ids);

        // Excluir o próprio card: não volta nos resultados.
        $r2 = $this->cards->getSelectableCards($alvo, null, 'muro ZZZ', 30);
        $ids2 = array_map(fn($x) => (int) $x['id'], $r2);
        $this->assertNotContains($alvo, $ids2, 'O card excluído não pode aparecer na busca.');
    }

    public function testGetSelectableCardsBuscaPorNumero(): void
    {
        $alvo = $this->novoCard(['title' => 'Qualquer', 'status' => 'open', 'company_id' => $this->companyId]);
        $r = $this->cards->getSelectableCards(null, null, '#' . $alvo, 30);
        $ids = array_map(fn($x) => (int) $x['id'], $r);
        $this->assertContains($alvo, $ids, 'Busca por #id deve achar o card.');
    }
}
