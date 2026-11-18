<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use CrmCommissionRules;

/**
 * Regras puras da comissão automática do CRM no Controle Financeiro:
 * quem recebe e quanto, vencimento junto com o salário e o acerto dos
 * lançamentos já existentes (criar/atualizar/cancelar/manter pago).
 */
final class CrmCommissionRulesTest extends TestCase
{
    private function card(array $o = []): array
    {
        return $o + [
            'id' => 10, 'title' => 'Card título', 'lead_name' => 'Padaria Sol',
            'value' => 2000, 'lead_outcome' => 'converted',
            'converted_by' => 1, 'prospected_by' => 1,
        ];
    }

    private function user(int $id, float $closing, float $prosp, float $legacy = 0, string $name = 'Evelyn'): array
    {
        return [
            'id' => $id, 'name' => $name,
            'commission_closing_percent' => $closing,
            'commission_prospection_percent' => $prosp,
            'commission_percent' => $legacy,
        ];
    }

    // ---------- desired ----------

    public function testQuemTrouxeEFechouGanhaSoOFechamento(): void
    {
        $evelyn = $this->user(1, 5, 3);
        $d = CrmCommissionRules::desired($this->card(), $evelyn, $evelyn);
        $this->assertSame(['closing'], array_keys($d));
        $this->assertSame(100.0, $d['closing']['amount']);
        $this->assertSame(1, $d['closing']['user_id']);
        $this->assertSame('Comissão de fechamento – Padaria Sol (5% de R$ 2.000,00)', $d['closing']['description']);
    }

    public function testOutroFechouGeraFechamentoEProspeccao(): void
    {
        $card = $this->card(['converted_by' => 2, 'prospected_by' => 1]);
        $d = CrmCommissionRules::desired($card, $this->user(2, 10, 0, 0, 'Bruno'), $this->user(1, 5, 2.5));
        $this->assertSame(200.0, $d['closing']['amount']);
        $this->assertSame(2, $d['closing']['user_id']);
        $this->assertSame(50.0, $d['prospection']['amount']);
        $this->assertSame(1, $d['prospection']['user_id']);
        $this->assertStringContainsString('prospecção', $d['prospection']['description']);
        $this->assertStringContainsString('(2,5% de R$ 2.000,00)', $d['prospection']['description']);
    }

    public function testPercentualZeradoNaoGeraLancamento(): void
    {
        $card = $this->card(['converted_by' => 2, 'prospected_by' => 1]);
        $d = CrmCommissionRules::desired($card, $this->user(2, 10, 0), $this->user(1, 5, 0));
        $this->assertSame(['closing'], array_keys($d));

        $nada = CrmCommissionRules::desired($card, $this->user(2, 0, 0), $this->user(1, 0, 0));
        $this->assertSame([], $nada);
    }

    public function testFechamentoUsaPercentualLegadoQuandoZerado(): void
    {
        $d = CrmCommissionRules::desired($this->card(), $this->user(1, 0, 0, 4), null);
        $this->assertSame(80.0, $d['closing']['amount']);
    }

    public function testCardNaoFechadoOuSemValorNaoGeraComissao(): void
    {
        $u = $this->user(1, 5, 0);
        $this->assertSame([], CrmCommissionRules::desired($this->card(['lead_outcome' => 'open']), $u, $u));
        $this->assertSame([], CrmCommissionRules::desired($this->card(['lead_outcome' => 'lost']), $u, $u));
        $this->assertSame([], CrmCommissionRules::desired($this->card(['value' => null]), $u, $u));
        $this->assertSame([], CrmCommissionRules::desired($this->card(['value' => 0]), $u, $u));
    }

    public function testSemQuemFechouSoAProspeccao(): void
    {
        $card = $this->card(['converted_by' => null, 'prospected_by' => 1]);
        $d = CrmCommissionRules::desired($card, null, $this->user(1, 5, 3));
        $this->assertSame(['prospection'], array_keys($d));
        $this->assertSame(60.0, $d['prospection']['amount']);
    }

    public function testArredondaCentavos(): void
    {
        $d = CrmCommissionRules::desired($this->card(['value' => 333.33]), $this->user(1, 5, 0), null);
        $this->assertSame(16.67, $d['closing']['amount']);
    }

    public function testDescricaoUsaTituloSemContatoECabeEm255(): void
    {
        $this->assertSame('Card título', CrmCommissionRules::leadName(['title' => 'Card título']));
        $this->assertSame('Card #7', CrmCommissionRules::leadName(['id' => 7]));
        $desc = CrmCommissionRules::description('closing', str_repeat('x', 400), 5, 1000);
        $this->assertLessThanOrEqual(255, mb_strlen($desc));
        $this->assertStringEndsWith('(5% de R$ 1.000,00)', $desc);
    }

    // ---------- dueDate ----------

    public function testVenceJuntoComOSalarioPendenteMaisProximo(): void
    {
        $rows = [
            ['due_date' => '2026-12-05', 'status' => 'pending'],
            ['due_date' => '2027-01-05', 'status' => 'pending'],
            ['due_date' => '2026-11-05', 'status' => 'paid'],
        ];
        $this->assertSame('2026-12-05', CrmCommissionRules::dueDate('2026-11-20', $rows));
    }

    public function testFechouNoDiaDoSalarioPendenteVaiNele(): void
    {
        $rows = [['due_date' => '2026-12-05', 'status' => 'pending']];
        $this->assertSame('2026-12-05', CrmCommissionRules::dueDate('2026-12-05', $rows));
    }

    public function testSalarioRecorrenteJaPagoUsaOProximoDiaDoSalario(): void
    {
        // Só a ocorrência paga está gravada (a próxima é "Previsto").
        $rows = [['due_date' => '2026-11-05', 'status' => 'paid']];
        $this->assertSame('2026-12-05', CrmCommissionRules::dueDate('2026-11-20', $rows));
        // Fechou antes do dia 5, mas o salário do mês já foi pago adiantado.
        $this->assertSame('2026-12-05', CrmCommissionRules::dueDate('2026-11-03', [['due_date' => '2026-11-05', 'status' => 'paid']]));
    }

    public function testSalarioAtrasadoNaoPuxaComissaoParaOPassado(): void
    {
        $rows = [['due_date' => '2026-11-05', 'status' => 'pending']];
        $this->assertSame('2026-12-05', CrmCommissionRules::dueDate('2026-11-20', $rows));
        $this->assertSame('2027-01-05', CrmCommissionRules::dueDate('2026-12-10', $rows));
    }

    public function testDiaDoSalario31EmMesCurto(): void
    {
        $rows = [['due_date' => '2027-01-31', 'status' => 'paid']];
        $this->assertSame('2027-02-28', CrmCommissionRules::dueDate('2027-02-10', $rows));
    }

    public function testSemSalarioLancadoVenceDia5DoMesSeguinte(): void
    {
        $this->assertSame('2026-12-05', CrmCommissionRules::dueDate('2026-11-20', []));
        $this->assertSame('2027-01-05', CrmCommissionRules::dueDate('2026-12-31', []));
        $this->assertSame('2026-12-05', CrmCommissionRules::dueDate('2026-11-20', [['due_date' => '2026-12-05', 'status' => 'cancelled']]));
    }

    // ---------- plan ----------

    private function want(string $role, int $user, float $amount, string $desc = 'd'): array
    {
        return ['role' => $role, 'user_id' => $user, 'amount' => $amount, 'description' => $desc];
    }

    public function testCriaQuandoFechaENaoExiste(): void
    {
        $p = CrmCommissionRules::plan(['closing' => $this->want('closing', 1, 100)], [], true);
        $this->assertCount(1, $p['create']);
        $this->assertSame([], $p['update']);
        $this->assertSame([], $p['cancel']);
    }

    public function testNaoRecriaForaDoFechamento(): void
    {
        // Ex.: financeiro excluiu o lançamento e depois editaram o valor do card.
        $p = CrmCommissionRules::plan(['closing' => $this->want('closing', 1, 100)], [], false);
        $this->assertSame([], $p['create']);
    }

    public function testFecharDeNovoNaoDuplica(): void
    {
        $existing = [['id' => 5, 'commission_role' => 'closing', 'user_id' => 1, 'amount' => '100.00', 'status' => 'pending', 'description' => 'd']];
        $p = CrmCommissionRules::plan(['closing' => $this->want('closing', 1, 100)], $existing, true);
        $this->assertSame([], $p['create']);
        $this->assertSame([], $p['update']);
        $this->assertSame([], $p['cancel']);
    }

    public function testPendenteAcompanhaValorEPessoa(): void
    {
        $existing = [['id' => 5, 'commission_role' => 'closing', 'user_id' => 1, 'amount' => '100.00', 'status' => 'pending', 'description' => 'velha']];
        $p = CrmCommissionRules::plan(['closing' => $this->want('closing', 2, 150, 'nova')], $existing, false);
        $this->assertCount(1, $p['update']);
        [$id, $changes] = $p['update'][0];
        $this->assertSame(5, $id);
        $this->assertSame(['amount' => 150.0, 'user_id' => 2, 'description' => 'nova'], $changes);
    }

    public function testCardReabertoCancelaPendenteEAvisaPaga(): void
    {
        $existing = [
            ['id' => 5, 'commission_role' => 'closing', 'user_id' => 1, 'amount' => 100, 'status' => 'pending', 'description' => 'd'],
            ['id' => 6, 'commission_role' => 'prospection', 'user_id' => 3, 'amount' => 40, 'status' => 'paid', 'description' => 'd'],
        ];
        $p = CrmCommissionRules::plan([], $existing, false);
        $this->assertSame([5], $p['cancel']);
        $this->assertSame([6], $p['paidOrphans']);
        $this->assertSame([], $p['update']);
    }

    public function testPagaNuncaEAlteradaNemDuplicada(): void
    {
        $existing = [['id' => 6, 'commission_role' => 'closing', 'user_id' => 1, 'amount' => 100, 'status' => 'paid', 'description' => 'd']];
        $p = CrmCommissionRules::plan(['closing' => $this->want('closing', 1, 999)], $existing, true);
        $this->assertSame([], $p['create']);
        $this->assertSame([], $p['update']);
        $this->assertSame([], $p['cancel']);
        $this->assertSame([], $p['paidOrphans']);
    }

    public function testPendenteDuplicadaECancelada(): void
    {
        $existing = [
            ['id' => 5, 'commission_role' => 'closing', 'user_id' => 1, 'amount' => 100, 'status' => 'pending', 'description' => 'd'],
            ['id' => 7, 'commission_role' => 'closing', 'user_id' => 1, 'amount' => 100, 'status' => 'pending', 'description' => 'd'],
        ];
        $p = CrmCommissionRules::plan(['closing' => $this->want('closing', 1, 100)], $existing, true);
        $this->assertSame([7], $p['cancel']);
        $this->assertSame([], $p['create']);
    }

    public function testPercentualZeradoDepoisCancelaSoAQueSumiu(): void
    {
        $existing = [
            ['id' => 5, 'commission_role' => 'closing', 'user_id' => 1, 'amount' => 100, 'status' => 'pending', 'description' => 'd'],
            ['id' => 6, 'commission_role' => 'prospection', 'user_id' => 3, 'amount' => 40, 'status' => 'pending', 'description' => 'd'],
        ];
        $p = CrmCommissionRules::plan(['closing' => $this->want('closing', 1, 100)], $existing, false);
        $this->assertSame([6], $p['cancel']);
        $this->assertSame([], $p['update']);
    }
}
