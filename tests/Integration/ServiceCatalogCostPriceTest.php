<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use ServiceCatalog;
use Database;

/**
 * Testes de integração do catálogo de serviços (esteira comercial) focados na
 * coluna cost_price (preço/custo de hora de referência interno), adicionada pela
 * migration 145, contra o banco helpdesk_on_test.
 *
 * Regra de negócio coberta:
 *  - cost_price é persistido e lido como DECIMAL(10,2);
 *  - valor ausente ("") vira NULL (campo opcional);
 *  - update altera apenas o que foi enviado;
 *  - cost_price é independente de hourly_rate (um pode existir sem o outro).
 *
 * Cria os próprios dados e limpa tudo no tearDown; não depende de registros
 * pré-existentes. Faz skip gracioso quando o banco de teste ou a coluna não
 * estão disponíveis (ex.: migration 161 ainda não aplicada no helpdesk_on_test).
 */
final class ServiceCatalogCostPriceTest extends TestCase
{
    private Database $db;
    private ServiceCatalog $model;

    /** @var int[] */
    private array $serviceIds = [];

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste (helpdesk_on_test) não configurado.');
        }

        $db = Database::getInstance();
        // A coluna cost_price (migration 145) é o alvo destes testes.
        $hasCostPrice = (bool) $db->fetch(
            "SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'service_catalog'
               AND COLUMN_NAME = 'cost_price'"
        );
        if (!$hasCostPrice) {
            $this->markTestSkipped('Coluna service_catalog.cost_price ausente: rode a migration 145 (atualizada) no banco de teste.');
        }

        $this->db = $db;
        $this->model = new ServiceCatalog();
    }

    protected function tearDown(): void
    {
        foreach ($this->serviceIds as $id) {
            try { $this->db->delete('service_catalog', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
    }

    private function novoServico(array $overrides = []): int
    {
        $u = uniqid();
        $data = array_merge([
            'name'        => "Serviço {$u}",
            'description' => null,
            'est_hours'   => null,
            'hourly_rate' => null,
            'cost_price'  => null,
            'is_hosting'  => 0,
            'active'      => 1,
        ], $overrides);
        $id = (int) $this->model->create($data);
        $this->serviceIds[] = $id;
        return $id;
    }

    public function testCostPricePersisteELeComoDecimal(): void
    {
        $id = $this->novoServico(['hourly_rate' => 120.00, 'cost_price' => 45.50]);

        $s = $this->model->findById($id);
        $this->assertNotNull($s, 'Serviço deveria existir.');
        $this->assertEquals(45.50, (float) $s['cost_price']);
        // Não misturar custo com valor/hora: cada um guarda o seu.
        $this->assertEquals(120.00, (float) $s['hourly_rate']);
    }

    public function testCostPriceOpcionalFicaNull(): void
    {
        $id = $this->novoServico(['hourly_rate' => 90.00]); // sem cost_price

        $s = $this->model->findById($id);
        $this->assertNull($s['cost_price'], 'cost_price omitido deveria ser NULL.');
    }

    public function testUpdateAlteraApenasCostPrice(): void
    {
        $id = $this->novoServico(['hourly_rate' => 100.00, 'cost_price' => 30.00]);

        $this->model->update($id, ['cost_price' => 55.25]);

        $s = $this->model->findById($id);
        $this->assertEquals(55.25, (float) $s['cost_price'], 'cost_price deveria ter sido atualizado.');
        $this->assertEquals(100.00, (float) $s['hourly_rate'], 'hourly_rate não deveria mudar.');
    }

    public function testCostPricePodeExistirSemHourlyRate(): void
    {
        $id = $this->novoServico(['cost_price' => 10.00]); // sem hourly_rate

        $s = $this->model->findById($id);
        $this->assertEquals(10.00, (float) $s['cost_price']);
        $this->assertNull($s['hourly_rate'], 'hourly_rate ausente deveria permanecer NULL.');
    }
}
