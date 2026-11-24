<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use ApolloApi;
use ApolloNicheRules;
use ApolloProspectingService;
use Database;

/** Apollo falso: grava o que a campanha pediu e devolve páginas configuradas. */
final class FakeApolloForNicheTest extends ApolloApi
{
    public array $calls = [];
    /** @var callable(array):array */
    public $responder;

    public function __construct() {}
    public function isConfigured() { return true; }
    public function searchPeople($filters = [])
    {
        $this->calls[] = $filters;
        return ($this->responder)($filters);
    }
    public function enrichPerson($params = []) { return ['success' => false, 'error' => 'não usado no teste']; }
}

/**
 * Integração (helpdesk_on_test): a campanha automática com NICHO pede ao Apollo
 * os segmentos certos, alterna entre buscas e pagina de verdade entre execuções.
 * O Apollo é falso (sem rede/crédito); o banco é o de teste.
 */
final class ApolloNicheCampaignTest extends TestCase
{
    private Database $db;
    private int $userId;
    private array $campaignIds = [];
    private string $u;

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste não configurado.');
        }
        $this->db = Database::getInstance();
        $this->u = uniqid();
        $this->userId = (int) $this->db->insert('users', [
            'name' => "Nicho {$this->u}", 'email' => "nicho_{$this->u}@example.test",
            'password' => password_hash('x', PASSWORD_BCRYPT), 'role' => 'comercial',
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->campaignIds as $id) {
            try { $this->db->delete('apollo_prospecting_log', 'campaign_id = ?', [$id]); } catch (\Throwable $e) {}
            try { $this->db->delete('apollo_campaigns', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
        try { $this->db->query("DELETE FROM apollo_leads WHERE apollo_id LIKE ?", ["nicho{$this->u}%"]); } catch (\Throwable $e) {}
        try { $this->db->delete('users', 'id = ?', [$this->userId]); } catch (\Throwable $e) {}
    }

    private function campaign(array $searchFilters, int $searchPage = 1): array
    {
        $id = (int) $this->db->insert('apollo_campaigns', [
            'name' => "E2E nicho {$this->u} " . count($this->campaignIds),
            'is_active' => 1, 'lead_source' => 'apollo', 'created_by' => $this->userId,
            'search_filters' => json_encode($searchFilters, JSON_UNESCAPED_UNICODE),
            // ICP que ninguém do Apollo falso atende: nada é revelado (sem crédito).
            'icp_rules' => json_encode(['seniorities' => ['owner']]),
            'min_score' => 0, 'daily_target' => 5, 'search_per_page' => 50, 'search_page' => $searchPage,
            'days_of_week' => '1,2,3,4,5,6,7', 'window_start' => '00:00:00', 'window_end' => '23:59:59',
        ]);
        $this->campaignIds[] = $id;
        return $this->db->fetch("SELECT * FROM apollo_campaigns WHERE id = ?", [$id]);
    }

    private function service(FakeApolloForNicheTest $fake): ApolloProspectingService
    {
        $svc = new ApolloProspectingService();
        $p = new \ReflectionProperty(ApolloProspectingService::class, 'apollo');
        $p->setValue($svc, $fake);
        return $svc;
    }

    /** 50 pessoas fora do ICP (seniority conhecida "entry"), total de entradas informado. */
    private function page(int $page, int $total): array
    {
        $people = [];
        for ($i = 0; $i < 50; $i++) {
            $people[] = ['id' => "nicho{$this->u}_{$page}_{$i}", 'first_name' => 'P', 'title' => 'Analista', 'seniority' => 'entry', 'organization' => ['name' => 'X']];
        }
        return ['success' => true, 'data' => ['total_entries' => $total, 'people' => $people]];
    }

    public function testDoisNichosViramUmaBuscaPorSegmentoENaoTextoColado(): void
    {
        $fake = new FakeApolloForNicheTest();
        $fake->responder = fn($f) => ['success' => true, 'data' => ['total_entries' => 0, 'people' => []]];
        $camp = $this->campaign(['person_titles' => ['ceo'], 'niches' => ['saúde', 'logística']]);

        $m = $this->service($fake)->runApolloCampaign($camp, 5);

        $this->assertCount(1, $fake->calls, 'nichos conhecidos = uma busca só');
        $sent = $fake->calls[0];
        $this->assertArrayNotHasKey('q_keywords', $sent);
        $this->assertContains('hospital & health care', $sent['organization_industries']);
        $this->assertContains('logistics & supply chain', $sent['organization_industries']);
        $this->assertSame(['owner'], $sent['person_seniorities'], 'ICP continua indo para a busca');
        $this->assertSame(0, $m['searched']);
    }

    public function testCampanhaAntigaComNichosColadosTambemFunciona(): void
    {
        $fake = new FakeApolloForNicheTest();
        $fake->responder = fn($f) => ['success' => true, 'data' => ['total_entries' => 0, 'people' => []]];
        $camp = $this->campaign(['q_keywords' => 'saúde logística']);
        $this->service($fake)->runApolloCampaign($camp, 5);
        $this->assertCount(1, $fake->calls);
        $this->assertArrayNotHasKey('q_keywords', $fake->calls[0]);
        $this->assertContains('medical practice', $fake->calls[0]['organization_industries']);
    }

    public function testTermoDesconhecidoEhBuscadoSeparadoDepoisDoSegmento(): void
    {
        $fake = new FakeApolloForNicheTest();
        $fake->responder = fn($f) => ['success' => true, 'data' => ['total_entries' => 0, 'people' => []]];
        $camp = $this->campaign(['niches' => ['saúde', 'piscinas']]);
        $this->service($fake)->runApolloCampaign($camp, 5);

        $this->assertCount(2, $fake->calls, 'uma busca por segmento + uma por palavra-chave; para quando todas acabam');
        $this->assertArrayHasKey('organization_industries', $fake->calls[0]);
        $this->assertSame('piscinas', $fake->calls[1]['q_keywords']);
        $this->assertArrayNotHasKey('organization_industries', $fake->calls[1]);
    }

    public function testPaginaDeVerdadeEContinuaDeOndeParou(): void
    {
        $fake = new FakeApolloForNicheTest();
        $fake->responder = fn($f) => $this->page((int) $f['page'], 120); // 3 páginas de 50
        $camp = $this->campaign(['niches' => ['saúde']], ApolloNicheRules::encodeCursor(0, 2));

        $m = $this->service($fake)->runApolloCampaign($camp, 5);

        $this->assertSame([2, 3], array_column($fake->calls, 'page'), 'retoma da página 2 e vai até a última');
        $this->assertSame(100, $m['searched']);
        $this->assertSame(100, $m['out_of_icp']);
        $this->assertSame(0, $m['revealed_email'], 'fora do ICP não gasta crédito');
        $saved = (int) $this->db->fetch("SELECT search_page FROM apollo_campaigns WHERE id = ?", [$camp['id']])['search_page'];
        $this->assertSame([0, 1], ApolloNicheRules::decodeCursor($saved, 1), 'terminou a busca: próxima execução recomeça');
    }
}
