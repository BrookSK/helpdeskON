<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Ticket;
use Database;

/**
 * Testes de integração dos indicadores operacionais de tickets (demanda #251).
 *
 * Cobre Ticket::getOperationalMetrics e Ticket::getOperationalMetricsByAttendant,
 * que alimentam a tela Performance > Operacional > Dados Estatísticos.
 *
 * Fluxo real validado: Criação (created_at) -> Admissão (admitted_at) ->
 * Tratamento/Desenvolvimento -> Conclusão (completed_at).
 *
 * Regras verificadas:
 *  - Admissão (Opção A) = entrada efetiva em trabalho: só carimba admitted_at em
 *    'in_progress' e status posteriores de tratamento; não na atribuição pura nem
 *    nas saídas 'waiting_client'/'denied'/'archived'; grava uma única vez.
 *  - Tempo médio de admissão: criação -> admissão; só tickets com admitted_at.
 *  - Tempo médio de tratamento: admissão -> conclusão; só concluídos e admitidos.
 *  - Tempo médio total: criação -> conclusão; só concluídos.
 *  - Taxa de conclusão = concluídos ÷ admitidos × 100.
 *  - Tickets sem os timestamps necessários não entram nos cálculos.
 *  - Tickets não concluídos não entram em tratamento/total.
 *  - Tickets não admitidos não entram na admissão.
 *  - Nenhum cálculo usa "primeira resposta".
 */
final class TicketOperationalMetricsTest extends TestCase
{
    private Database $db;
    private Ticket $ticket;
    private int $clientId;
    private int $attendantA;
    private int $attendantB;
    /** @var int[] */
    private array $ticketIds = [];
    /** @var int[] */
    private array $cardIds = [];

    private string $start;
    private string $end;
    private string $day; // dia base, dentro do período

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste (helpdesk_on_test) não configurado.');
        }
        $this->db = Database::getInstance();

        // A coluna admitted_at é obrigatória para estes testes (migration 131).
        $col = $this->db->fetch(
            "SELECT COUNT(*) AS c FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'tickets'
               AND COLUMN_NAME = 'admitted_at'"
        );
        if ((int)($col['c'] ?? 0) === 0) {
            $this->markTestSkipped('Coluna tickets.admitted_at ausente. Rode a migration 131 no banco de teste.');
        }

        $this->ticket = new Ticket();

        // Período fixo e determinístico (não usa "hoje") para isolar dos demais dados.
        $this->start = '2024-06-01';
        $this->end = '2024-06-30';
        $this->day = '2024-06-15';

        $u = uniqid();
        $this->clientId = $this->novoUsuario("Cliente {$u}", "cli_{$u}@example.test", 'client');
        $this->attendantA = $this->novoUsuario("Atendente A {$u}", "atA_{$u}@example.test", 'attendant');
        $this->attendantB = $this->novoUsuario("Atendente B {$u}", "atB_{$u}@example.test", 'attendant');
    }

    protected function tearDown(): void
    {
        foreach ($this->cardIds as $id) {
            try { $this->db->delete('planning_cards', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
        foreach ($this->ticketIds as $id) {
            try { $this->db->delete('ticket_attendants', 'ticket_id = ?', [$id]); } catch (\Throwable $e) {}
            try { $this->db->delete('tickets', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
        foreach ([$this->clientId, $this->attendantA, $this->attendantB] as $id) {
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

    /**
     * Insere um ticket com timestamps explícitos para simular pontos do fluxo.
     * $created/$admitted/$completed são 'Y-m-d H:i:s' ou null.
     */
    private function novoTicket(
        ?int $attendantId,
        string $status,
        ?string $created,
        ?string $admitted,
        ?string $completed
    ): int {
        $data = [
            'client_id' => $this->clientId,
            'attendant_id' => $attendantId,
            'title' => 'Ticket perf',
            'description' => 'desc',
            'priority' => 'medium',
            'status' => $status,
            'created_at' => $created,
            'admitted_at' => $admitted,
            'completed_at' => $completed,
        ];
        $id = (int) $this->db->insert('tickets', $data);
        // insert() pode não sobrescrever created_at (DEFAULT CURRENT_TIMESTAMP);
        // força os timestamps exatamente como o cenário pede.
        $this->db->update('tickets', [
            'created_at' => $created,
            'admitted_at' => $admitted,
            'completed_at' => $completed,
        ], 'id = ?', [$id]);
        $this->ticketIds[] = $id;
        return $id;
    }

    private function novoCardComPrazo(int $ticketId, string $status, string $dueDate): int
    {
        $id = (int) $this->db->insert('planning_cards', [
            'ticket_id' => $ticketId,
            'title' => 'card perf',
            'created_by' => $this->clientId,
            'status' => $status,
            'priority' => 'medium',
            'position' => 0,
            'due_date' => $dueDate,
        ]);
        $this->cardIds[] = $id;
        return $id;
    }

    private function novoCardComStart(int $ticketId, string $status, ?string $startDate): int
    {
        $id = (int) $this->db->insert('planning_cards', [
            'ticket_id' => $ticketId,
            'title' => 'card start',
            'created_by' => $this->clientId,
            'status' => $status,
            'priority' => 'medium',
            'position' => 0,
            'start_date' => $startDate,
        ]);
        $this->cardIds[] = $id;
        return $id;
    }

    private function admittedAtDe(int $ticketId): ?string
    {
        $row = $this->db->fetch("SELECT admitted_at FROM tickets WHERE id = ?", [$ticketId]);
        return $row['admitted_at'] ?? null;
    }

    /** Cria um ticket via Ticket::create() (para exercitar a regra de admissão na criação). */
    private function criaViaModel(string $status): int
    {
        $id = (int) $this->ticket->create([
            'client_id' => $this->clientId,
            'title' => 'Ticket regra',
            'description' => 'desc',
            'priority' => 'medium',
            'status' => $status,
        ]);
        $this->ticketIds[] = $id;
        return $id;
    }

    // ===== Regra de admissão (Opção A): quando admitted_at é carimbado =====

    public function testCriacaoEmOpenNaoCarimbaAdmissao(): void
    {
        $id = $this->criaViaModel('open');
        $this->assertNull($this->admittedAtDe($id), 'open não é trabalho: sem admissão');
    }

    public function testCriacaoJaEmTrabalhoCarimbaAdmissao(): void
    {
        $id = $this->criaViaModel('in_progress');
        $this->assertNotNull($this->admittedAtDe($id), 'nascer em in_progress carimba admissão');
    }

    public function testEntradaEmInProgressCarimbaAdmissao(): void
    {
        $id = $this->criaViaModel('open');
        $this->assertNull($this->admittedAtDe($id));
        $this->ticket->updateStatus($id, 'in_progress');
        $this->assertNotNull($this->admittedAtDe($id), 'in_progress carimba admissão');
    }

    public function testSaidasNaoDeTrabalhoNaoCarimbamAdmissao(): void
    {
        foreach (['waiting_client', 'denied', 'archived'] as $status) {
            $id = $this->criaViaModel('open');
            $this->ticket->updateStatus($id, $status);
            $this->assertNull(
                $this->admittedAtDe($id),
                "status '{$status}' não representa trabalho: não deve carimbar admissão"
            );
        }
    }

    public function testAdmissaoGravadaUmaSoVezPreservaOPrimeiroInstante(): void
    {
        $id = $this->criaViaModel('open');
        $this->ticket->updateStatus($id, 'in_progress');
        $primeiro = $this->admittedAtDe($id);
        $this->assertNotNull($primeiro);

        // Avança no fluxo: admitted_at não pode mudar.
        sleep(1);
        $this->ticket->updateStatus($id, 'em_homologacao');
        $this->assertSame($primeiro, $this->admittedAtDe($id), 'admissão preserva o primeiro instante');
    }

    public function testAtribuicaoDeAtendenteCarimbaViaInProgress(): void
    {
        // assignAttendant leva o ticket a in_progress; a admissão vem daí, não da
        // atribuição em si — mas o efeito líquido é admitted_at preenchido.
        $id = $this->criaViaModel('open');
        $this->assertNull($this->admittedAtDe($id));
        $this->ticket->assignAttendant($id, $this->attendantA);
        $this->assertNotNull($this->admittedAtDe($id));
    }

    public function testSaltoOpenParaCompletedNaoCarimbaAdmissao(): void
    {
        // Salto direto open -> completed: nunca houve trabalho registrado, então
        // NÃO pode carimbar admissão (senão o tempo de admissão sairia ≈ 0).
        $id = $this->criaViaModel('open');
        $this->ticket->updateStatus($id, 'completed');
        $this->assertNull(
            $this->admittedAtDe($id),
            'open -> completed direto não representa admissão'
        );
    }

    public function testSaltoParaStatusPosteriorSemTrabalhoNaoCarimba(): void
    {
        // Saltos para status "posteriores" vindos de não-trabalho também não contam.
        foreach (['em_homologacao', 'aprovado_producao'] as $status) {
            $id = $this->criaViaModel('open');
            $this->ticket->updateStatus($id, $status);
            $this->assertNull(
                $this->admittedAtDe($id),
                "open -> {$status} (sem passar por trabalho) não deve carimbar admissão"
            );
        }
    }

    public function testContinuidadeDeTrabalhoAteCompletedCarimbaNoInicio(): void
    {
        // Fluxo correto: in_progress -> completed. A admissão é o in_progress.
        $id = $this->criaViaModel('open');
        $this->ticket->updateStatus($id, 'in_progress');
        $primeiro = $this->admittedAtDe($id);
        $this->assertNotNull($primeiro);

        sleep(1);
        $this->ticket->updateStatus($id, 'completed');
        // Continua admitido e preserva o instante do início do trabalho.
        $this->assertSame($primeiro, $this->admittedAtDe($id));
    }

    public function testCriacaoDiretaEmCompletedNaoCarimbaAdmissao(): void
    {
        // Nascer direto em completed é um salto sem trabalho: sem admissão.
        $id = $this->criaViaModel('completed');
        $this->assertNull(
            $this->admittedAtDe($id),
            'criar direto em completed não deve carimbar admissão'
        );
    }

    // ===== getOperationalMetrics =====

    public function testTemposMediosSeguemFluxoReal(): void
    {
        // Ticket concluído: criado dia 15 00:00, admitido +2h, concluído +10h (total 12h).
        // admissão = 2h; tratamento = 10h; total = 12h.
        $this->novoTicket(
            $this->attendantA, 'completed',
            $this->day . ' 00:00:00',
            $this->day . ' 02:00:00',
            $this->day . ' 12:00:00'
        );

        $m = $this->ticket->getOperationalMetrics($this->start, $this->end, $this->attendantA);

        $this->assertSame(1, $m['admitted'], 'deve haver 1 ticket admitido');
        $this->assertSame(1, $m['completed'], 'deve haver 1 ticket concluído');
        $this->assertEqualsWithDelta(2.0, $m['avg_admission_hours'], 0.01, 'admissão = 2h');
        $this->assertEqualsWithDelta(10.0, $m['avg_treatment_hours'], 0.01, 'tratamento = 10h');
        $this->assertEqualsWithDelta(12.0, $m['avg_total_hours'], 0.01, 'total = 12h');
        $this->assertEqualsWithDelta(100.0, $m['completion_rate'], 0.01, 'taxa 100%');
    }

    public function testNaoAdmitidoNaoEntraNaAdmissao(): void
    {
        // Ticket aberto, sem admissão nem conclusão: não conta em nada de tempo.
        $this->novoTicket(
            $this->attendantA, 'open',
            $this->day . ' 00:00:00',
            null,
            null
        );

        $m = $this->ticket->getOperationalMetrics($this->start, $this->end, $this->attendantA);

        $this->assertSame(0, $m['admitted']);
        $this->assertSame(0, $m['completed']);
        $this->assertSame(1, $m['pending'], 'ticket aberto é pendente');
        // Sem tickets elegíveis => "sem dados" (null), não 0h.
        $this->assertNull($m['avg_admission_hours']);
        $this->assertNull($m['avg_treatment_hours']);
        $this->assertNull($m['avg_total_hours']);
    }

    public function testNaoConcluidoNaoEntraEmTratamentoNemTotal(): void
    {
        // Admitido (2h) mas ainda em andamento: entra em admissão, NÃO em tratamento/total.
        $this->novoTicket(
            $this->attendantA, 'in_progress',
            $this->day . ' 00:00:00',
            $this->day . ' 02:00:00',
            null
        );

        $m = $this->ticket->getOperationalMetrics($this->start, $this->end, $this->attendantA);

        $this->assertSame(1, $m['admitted']);
        $this->assertSame(0, $m['completed']);
        $this->assertEqualsWithDelta(2.0, $m['avg_admission_hours'], 0.01);
        $this->assertNull($m['avg_treatment_hours'], 'não concluído não entra em tratamento (sem dados)');
        $this->assertNull($m['avg_total_hours'], 'não concluído não entra no total (sem dados)');
        $this->assertSame(0.0, $m['completion_rate'], '0 de 1 admitido = 0%');
    }

    public function testTaxaConclusaoConcluidosSobreAdmitidos(): void
    {
        // 2 admitidos; 1 concluído -> taxa 50%.
        $this->novoTicket(
            $this->attendantA, 'completed',
            $this->day . ' 00:00:00', $this->day . ' 01:00:00', $this->day . ' 05:00:00'
        );
        $this->novoTicket(
            $this->attendantA, 'in_progress',
            $this->day . ' 00:00:00', $this->day . ' 03:00:00', null
        );

        $m = $this->ticket->getOperationalMetrics($this->start, $this->end, $this->attendantA);

        $this->assertSame(2, $m['admitted']);
        $this->assertSame(1, $m['completed']);
        $this->assertEqualsWithDelta(50.0, $m['completion_rate'], 0.01);
    }

    public function testTicketAntigoSemAdmissaoNaoEntraNemEstouraTaxa(): void
    {
        // Cenário de transição: ticket concluído SEM admitted_at (dado legado,
        // sem backfill). Não deve contar como admitido nem no tempo de admissão,
        // e a taxa de conclusão não pode passar de 100%.
        // Legado concluído sem admissão:
        $this->novoTicket(
            $this->attendantA, 'completed',
            $this->day . ' 00:00:00',
            null,
            $this->day . ' 06:00:00'
        );
        // Novo, admitido e concluído (adm 2h):
        $this->novoTicket(
            $this->attendantA, 'completed',
            $this->day . ' 00:00:00',
            $this->day . ' 02:00:00',
            $this->day . ' 08:00:00'
        );

        $m = $this->ticket->getOperationalMetrics($this->start, $this->end, $this->attendantA);

        $this->assertSame(1, $m['admitted'], 'só o novo ticket tem admissão');
        $this->assertSame(2, $m['completed'], 'ambos concluídos contam como volume');
        $this->assertSame(1, $m['completed_legacy'], '1 concluído sem data de admissão');
        // Numerador da taxa = concluídos-admitidos (1), denominador = admitidos (1).
        $this->assertEqualsWithDelta(100.0, $m['completion_rate'], 0.01, 'taxa não estoura 100%');
        // Tempo médio de admissão considera só o ticket com admitted_at.
        $this->assertEqualsWithDelta(2.0, $m['avg_admission_hours'], 0.01);
        // Tempo total considera ambos concluídos: (6h + 8h)/2 = 7h.
        $this->assertEqualsWithDelta(7.0, $m['avg_total_hours'], 0.01);
        // Tratamento considera só o admitido-concluído: 8h - 2h = 6h.
        $this->assertEqualsWithDelta(6.0, $m['avg_treatment_hours'], 0.01);
    }

    public function testSemAdmitidosTaxaEhNullEContaLegados(): void
    {
        // Cenário "Julia": só concluídos legados (sem admitted_at), nenhum admitido.
        // Taxa não tem base de cálculo -> null (a view mostra "—", não "0%").
        $this->novoTicket(
            $this->attendantA, 'completed',
            $this->day . ' 00:00:00', null, $this->day . ' 06:00:00'
        );
        $this->novoTicket(
            $this->attendantA, 'completed',
            $this->day . ' 00:00:00', null, $this->day . ' 10:00:00'
        );

        $m = $this->ticket->getOperationalMetrics($this->start, $this->end, $this->attendantA);

        $this->assertSame(0, $m['admitted']);
        $this->assertSame(2, $m['completed']);
        $this->assertSame(2, $m['completed_legacy'], 'ambos concluídos são legados');
        $this->assertNull($m['completion_rate'], 'sem admitidos => taxa sem base (null)');
        $this->assertNull($m['avg_admission_hours']);
        $this->assertNull($m['avg_treatment_hours']);
        // Total ainda considera os concluídos: (6h + 10h)/2 = 8h.
        $this->assertEqualsWithDelta(8.0, $m['avg_total_hours'], 0.01);
    }

    public function testPorProfissionalSemAdmitidosTaxaNull(): void
    {
        // Mesmo cenário na tabela por profissional.
        $this->novoTicket(
            $this->attendantA, 'completed',
            $this->day . ' 00:00:00', null, $this->day . ' 06:00:00'
        );

        $rows = $this->ticket->getOperationalMetricsByAttendant($this->start, $this->end);
        $byId = [];
        foreach ($rows as $r) { $byId[(int)$r['user_id']] = $r; }

        $a = $byId[$this->attendantA];
        $this->assertSame(0, $a['admitted']);
        $this->assertSame(1, $a['completed']);
        $this->assertSame(1, $a['completed_legacy']);
        $this->assertNull($a['completion_rate'], 'sem admitidos => taxa null');
    }

    public function testForaDoPeriodoNaoEntra(): void
    {
        // Admissão fora do período (maio): não conta como admitido no período.
        $this->novoTicket(
            $this->attendantA, 'in_progress',
            '2024-05-10 00:00:00',
            '2024-05-10 02:00:00',
            null
        );

        $m = $this->ticket->getOperationalMetrics($this->start, $this->end, $this->attendantA);
        $this->assertSame(0, $m['admitted'], 'admissão fora do período não conta');
    }

    // ===== getOperationalMetricsByAttendant =====

    public function testPorProfissionalSeparaMetricas(): void
    {
        // Atendente A: 1 concluído (adm 2h, trat 6h, total 8h)
        $this->novoTicket(
            $this->attendantA, 'completed',
            $this->day . ' 00:00:00', $this->day . ' 02:00:00', $this->day . ' 08:00:00'
        );
        // Atendente A: 1 pendente admitido
        $this->novoTicket(
            $this->attendantA, 'in_progress',
            $this->day . ' 00:00:00', $this->day . ' 01:00:00', null
        );
        // Atendente B: 1 concluído
        $this->novoTicket(
            $this->attendantB, 'completed',
            $this->day . ' 00:00:00', $this->day . ' 04:00:00', $this->day . ' 20:00:00'
        );

        $rows = $this->ticket->getOperationalMetricsByAttendant($this->start, $this->end);
        $byId = [];
        foreach ($rows as $r) { $byId[(int)$r['user_id']] = $r; }

        $this->assertArrayHasKey($this->attendantA, $byId);
        $a = $byId[$this->attendantA];
        $this->assertSame(2, $a['admitted']);
        $this->assertSame(1, $a['completed']);
        $this->assertSame(1, $a['pending']);
        $this->assertEqualsWithDelta(1.5, $a['avg_admission_hours'], 0.01, 'A admissão média = (2h+1h)/2 = 1.5h');
        $this->assertEqualsWithDelta(6.0, $a['avg_treatment_hours'], 0.01, 'A tratamento = 6h');
        $this->assertEqualsWithDelta(8.0, $a['avg_total_hours'], 0.01, 'A total = 8h');
        $this->assertEqualsWithDelta(50.0, $a['completion_rate'], 0.01);

        $this->assertArrayHasKey($this->attendantB, $byId);
        $b = $byId[$this->attendantB];
        $this->assertSame(1, $b['completed']);
        $this->assertEqualsWithDelta(16.0, $b['avg_treatment_hours'], 0.01, 'B tratamento = 16h');
    }

    public function testProfissionalAtrasadosContaCardVencido(): void
    {
        // Ticket pendente com card de planejamento vencido -> conta como atrasado.
        $t1 = $this->novoTicket(
            $this->attendantA, 'in_progress',
            $this->day . ' 00:00:00', $this->day . ' 01:00:00', null
        );
        $this->novoCardComPrazo($t1, 'in_progress', '2024-06-16 00:00:00'); // vencido (passado)

        // Ticket pendente com card no futuro -> NÃO atrasado.
        $t2 = $this->novoTicket(
            $this->attendantA, 'in_progress',
            $this->day . ' 00:00:00', $this->day . ' 01:00:00', null
        );
        $this->novoCardComPrazo($t2, 'in_progress', '2099-01-01 00:00:00');

        $rows = $this->ticket->getOperationalMetricsByAttendant($this->start, $this->end);
        $byId = [];
        foreach ($rows as $r) { $byId[(int)$r['user_id']] = $r; }

        $this->assertArrayHasKey($this->attendantA, $byId);
        $this->assertSame(1, $byId[$this->attendantA]['overdue'], 'apenas 1 card vencido conta como atrasado');
    }

    public function testProfissionalSemConclusaoTemTratamentoETotalNulos(): void
    {
        // Profissional com só um ticket admitido e pendente: admissão tem valor,
        // mas tratamento e total ficam "sem dados" (null), não 0.
        $this->novoTicket(
            $this->attendantA, 'in_progress',
            $this->day . ' 00:00:00', $this->day . ' 02:00:00', null
        );

        $rows = $this->ticket->getOperationalMetricsByAttendant($this->start, $this->end);
        $byId = [];
        foreach ($rows as $r) { $byId[(int)$r['user_id']] = $r; }

        $a = $byId[$this->attendantA];
        $this->assertEqualsWithDelta(2.0, $a['avg_admission_hours'], 0.01);
        $this->assertNull($a['avg_treatment_hours'], 'sem concluídos => tratamento sem dados');
        $this->assertNull($a['avg_total_hours'], 'sem concluídos => total sem dados');
    }

    // ===== Precisão: diferença exata de timestamps (não trunca em horas) =====

    public function testPrecisaoConsideraMinutosNaoTruncaHoras(): void
    {
        // Admissão 90 min após a criação, conclusão 30 min após a admissão.
        // admissão = 1.5h; tratamento = 0.5h; total = 2.0h.
        $this->novoTicket(
            $this->attendantA, 'completed',
            $this->day . ' 10:00:00',
            $this->day . ' 11:30:00',
            $this->day . ' 12:00:00'
        );

        $m = $this->ticket->getOperationalMetrics($this->start, $this->end, $this->attendantA);

        $this->assertEqualsWithDelta(1.5, $m['avg_admission_hours'], 0.01, 'admissão = 90min = 1.5h');
        $this->assertEqualsWithDelta(0.5, $m['avg_treatment_hours'], 0.01, 'tratamento = 30min = 0.5h');
        $this->assertEqualsWithDelta(2.0, $m['avg_total_hours'], 0.01, 'total = 120min = 2h');
    }

    public function testExemploDaDemandaTresDiasDeAdmissao(): void
    {
        // Exemplo literal da demanda: criação 10:00 e admissão +72h (3 dias).
        $this->novoTicket(
            $this->attendantA, 'in_progress',
            '2024-06-10 10:00:00',
            '2024-06-13 10:00:00',
            null
        );

        $m = $this->ticket->getOperationalMetrics($this->start, $this->end, $this->attendantA);
        $this->assertEqualsWithDelta(72.0, $m['avg_admission_hours'], 0.01, '3 dias = 72h');
    }

    // ===== Backfill estimado do histórico (migration 132) =====

    public function testBackfillUsaStartDateDoCardQuandoExiste(): void
    {
        // Ticket legado concluído, sem admitted_at, com card cujo start_date é a
        // melhor estimativa do início do trabalho.
        $t = $this->novoTicket(
            $this->attendantA, 'completed',
            $this->day . ' 00:00:00', null, $this->day . ' 20:00:00'
        );
        // updated_at fica "agora" (insert), mas o start_date do card deve prevalecer.
        $this->novoCardComStart($t, 'completed', $this->day . ' 04:00:00');

        $this->ticket->backfillEstimatedAdmittedAt();

        $adm = $this->admittedAtDe($t);
        $this->assertNotNull($adm, 'backfill deve preencher admitted_at');
        $this->assertSame($this->day . ' 04:00:00', $adm, 'usa o start_date do card');
    }

    public function testBackfillCaiParaUpdatedAtSemStartDate(): void
    {
        // Sem card com start_date: usa updated_at como fallback.
        $t = $this->novoTicket(
            $this->attendantA, 'in_progress',
            $this->day . ' 00:00:00', null, null
        );
        // Define updated_at explicitamente (o insert grava "agora"); forçamos aqui.
        $this->db->update('tickets', ['updated_at' => $this->day . ' 05:00:00'], 'id = ?', [$t]);

        $this->ticket->backfillEstimatedAdmittedAt();

        $this->assertSame($this->day . ' 05:00:00', $this->admittedAtDe($t), 'fallback = updated_at');
    }

    public function testBackfillNaoTocaOpenNemSaidasSemTrabalho(): void
    {
        // open / denied / archived nunca representam trabalho: não recebem admissão.
        foreach (['open', 'denied', 'archived'] as $status) {
            $t = $this->novoTicket(
                $this->attendantA, $status,
                $this->day . ' 00:00:00', null, null
            );
            $this->ticket->backfillEstimatedAdmittedAt();
            $this->assertNull($this->admittedAtDe($t), "status '{$status}' não deve receber backfill");
        }
    }

    public function testBackfillNaoUltrapassaConclusao(): void
    {
        // start_date depois da conclusão (dado inconsistente): admissão é limitada
        // ao completed_at, garantindo tratamento >= 0.
        $t = $this->novoTicket(
            $this->attendantA, 'completed',
            $this->day . ' 00:00:00', null, $this->day . ' 08:00:00'
        );
        $this->novoCardComStart($t, 'completed', $this->day . ' 23:00:00'); // > conclusão

        $this->ticket->backfillEstimatedAdmittedAt();

        $this->assertSame($this->day . ' 08:00:00', $this->admittedAtDe($t), 'admissão limitada à conclusão');
    }

    public function testBackfillNaoSobrescreveAdmissaoReal(): void
    {
        // Ticket que já tem admitted_at (carimbo real) não é alterado pelo backfill.
        $t = $this->novoTicket(
            $this->attendantA, 'completed',
            $this->day . ' 00:00:00', $this->day . ' 02:00:00', $this->day . ' 09:00:00'
        );
        $this->novoCardComStart($t, 'completed', $this->day . ' 06:00:00');

        $this->ticket->backfillEstimatedAdmittedAt();

        $this->assertSame($this->day . ' 02:00:00', $this->admittedAtDe($t), 'não sobrescreve admissão real');
    }
}
