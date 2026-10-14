<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Payable;
use PayableRules;
use Database;

/**
 * Testes de integração do contas a pagar (Payable) contra helpdesk_on_test.
 * Cobre: gerar o plano a partir do prestador, idempotência (não duplica ao
 * reprocessar), preservar pagos ao regerar, e baixa/cancelamento.
 */
final class PayableTest extends TestCase
{
    private Database $db;
    private Payable $model;
    private int $providerId;

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste (helpdesk_on_test) não configurado.');
        }
        $this->db = Database::getInstance();
        $this->model = new Payable();

        $u = uniqid();
        $this->providerId = (int) $this->db->insert('providers', [
            'name' => "Prestador {$u}",
            'engagement_type' => 'pj',
            'pay_type' => 'projeto',
            'pay_amount' => 9000,
            'status' => 'draft',
        ]);
    }

    protected function tearDown(): void
    {
        try { $this->db->delete('payables', 'provider_id = ?', [$this->providerId]); } catch (\Throwable $e) {}
        try { $this->db->delete('providers', 'id = ?', [$this->providerId]); } catch (\Throwable $e) {}
    }

    public function testReplaceForProviderGeraPlanoDeParcelas(): void
    {
        $provider = $this->db->fetch("SELECT * FROM providers WHERE id = ?", [$this->providerId]);
        $plan = PayableRules::buildPlanForProvider($provider, 3);
        $n = $this->model->replaceForProvider($this->providerId, $plan);
        $this->assertSame(3, $n);

        $items = $this->model->getByProvider($this->providerId);
        $this->assertCount(3, $items);
        $this->assertSame(9000.0, round(array_sum(array_map(fn($p) => (float)$p['amount'], $items)), 2));
    }

    public function testReplaceForProviderEhIdempotente(): void
    {
        $provider = $this->db->fetch("SELECT * FROM providers WHERE id = ?", [$this->providerId]);
        $plan = PayableRules::buildPlanForProvider($provider, 3);

        $this->model->replaceForProvider($this->providerId, $plan);
        // Reprocessa (ex.: webhook repetiu) — não deve duplicar.
        $this->model->replaceForProvider($this->providerId, $plan);

        $items = $this->model->getByProvider($this->providerId);
        $this->assertCount(3, $items);
    }

    public function testRegerarPreservaLancamentosPagos(): void
    {
        $provider = $this->db->fetch("SELECT * FROM providers WHERE id = ?", [$this->providerId]);
        $plan = PayableRules::buildPlanForProvider($provider, 3);
        $this->model->replaceForProvider($this->providerId, $plan);

        // Paga a primeira parcela.
        $items = $this->model->getByProvider($this->providerId);
        $this->assertTrue($this->model->markPaid((int)$items[count($items) - 1]['id'])); // a mais antiga

        // Regera: a paga permanece; as pendentes são recriadas.
        $this->model->replaceForProvider($this->providerId, $plan);
        $items = $this->model->getByProvider($this->providerId);

        $pagos = array_filter($items, fn($p) => $p['status'] === 'paid');
        $this->assertCount(1, $pagos, 'O lançamento pago deve ser preservado ao regerar.');
        // 1 pago preservado + 3 recriados pendentes = 4.
        $this->assertCount(4, $items);
    }

    public function testMarkPaidECancel(): void
    {
        $provider = $this->db->fetch("SELECT * FROM providers WHERE id = ?", [$this->providerId]);
        $this->model->replaceForProvider($this->providerId, PayableRules::buildPlanForProvider($provider, 2));
        $items = $this->model->getByProvider($this->providerId);

        $this->assertTrue($this->model->markPaid((int)$items[0]['id']));
        $this->assertTrue($this->model->cancel((int)$items[1]['id']));

        $items = $this->model->getByProvider($this->providerId);
        $byId = [];
        foreach ($items as $it) $byId[(int)$it['id']] = $it['status'];
        $this->assertContains('paid', $byId);
        $this->assertContains('cancelled', $byId);
    }

    public function testPendingTotalIgnoraPagosECancelados(): void
    {
        $provider = $this->db->fetch("SELECT * FROM providers WHERE id = ?", [$this->providerId]);
        $this->model->replaceForProvider($this->providerId, PayableRules::buildPlanForProvider($provider, 3));
        $items = $this->model->getByProvider($this->providerId);

        $totalInicial = $this->model->pendingTotal($this->providerId);
        $this->assertSame(9000.0, round($totalInicial, 2));

        // Paga uma parcela: o pendente cai.
        $this->model->markPaid((int)$items[0]['id']);
        $this->assertLessThan($totalInicial, $this->model->pendingTotal($this->providerId));
    }
}
