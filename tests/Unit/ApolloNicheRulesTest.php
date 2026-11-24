<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ApolloNicheRules;

/**
 * Nicho da prospecção automática (ApolloNicheRules).
 *
 * Bug de produção: os nichos iam colados num texto só (q_keywords = "saúde
 * logística"). O Apollo exige todas as palavras ao mesmo tempo: dois nichos
 * davam 0 resultados e um nicho em português trazia ~7 contatos.
 */
final class ApolloNicheRulesTest extends TestCase
{
    public function testVariosNichosViramSegmentosDoApolloNumaBuscaSoComOu(): void
    {
        $v = ApolloNicheRules::searchVariants([
            'person_titles' => ['ceo'], 'organization_num_employees_ranges' => ['11,50'],
            'niches' => ['saúde', 'logística'],
        ]);
        $this->assertCount(1, $v, 'nichos conhecidos ficam na MESMA busca (qualquer um deles)');
        $this->assertArrayNotHasKey('q_keywords', $v[0], 'nunca mais texto livre colado');
        $this->assertContains('hospital & health care', $v[0]['organization_industries']);
        $this->assertContains('logistics & supply chain', $v[0]['organization_industries']);
        $this->assertSame(['ceo'], $v[0]['person_titles'], 'os demais filtros continuam');
        $this->assertSame(['11,50'], $v[0]['organization_num_employees_ranges']);
        $this->assertSame(count($v[0]['organization_industries']), count(array_unique($v[0]['organization_industries'])));
    }

    public function testFormatoAntigoColadoEmQKeywordsEhSeparadoDeVolta(): void
    {
        $this->assertSame(['saúde', 'logística'], ApolloNicheRules::nichesFromFilters(['q_keywords' => 'saúde logística']));
        $this->assertSame(['construção civil', 'saúde'], ApolloNicheRules::nichesFromFilters(['q_keywords' => 'construção civil saúde']));
        $v = ApolloNicheRules::searchVariants(['q_keywords' => 'construção civil saúde']);
        $this->assertCount(1, $v);
        $this->assertContains('construction', $v[0]['organization_industries']);
        $this->assertContains('medical practice', $v[0]['organization_industries']);
    }

    public function testTermoDesconhecidoViraBuscaSeparadaUmTermoPorBusca(): void
    {
        $v = ApolloNicheRules::searchVariants(['niches' => ['saúde', 'piscinas', 'drones'], 'person_locations' => ['Brazil']]);
        $this->assertCount(3, $v);
        $this->assertArrayHasKey('organization_industries', $v[0]);
        $this->assertSame('piscinas', $v[1]['q_keywords']);
        $this->assertSame('drones', $v[2]['q_keywords']);
        $this->assertArrayNotHasKey('organization_industries', $v[1]);
        $this->assertSame(['Brazil'], $v[2]['person_locations']);
    }

    public function testSemNichoUmaBuscaComOsFiltros(): void
    {
        $this->assertSame([['person_titles' => ['ceo']]], ApolloNicheRules::searchVariants(['person_titles' => ['ceo'], 'page' => 3]));
        $this->assertSame([[]], ApolloNicheRules::searchVariants([]));
    }

    public function testReconheceSemAcentoMaiusculasENomeDoApolloEmIngles(): void
    {
        $this->assertSame(ApolloNicheRules::industriesFor('saúde'), ApolloNicheRules::industriesFor('  SAUDE '));
        $this->assertSame(['accounting'], ApolloNicheRules::industriesFor('Contabilidade'));
        $this->assertSame(['hospital & health care'], ApolloNicheRules::industriesFor('Hospital & Health Care'));
        $this->assertSame([], ApolloNicheRules::industriesFor('piscinas'));
        $this->assertContains('odontologia', ApolloNicheRules::nicheKeys());
    }

    public function testResolveSeparaMapeadosEDesconhecidos(): void
    {
        $r = ApolloNicheRules::resolve(['Odontologia', 'piscinas', '']);
        $this->assertSame(['Odontologia'], array_keys($r['mapped']));
        $this->assertSame(['piscinas'], $r['keywords']);
        $this->assertContains('medical practice', $r['industries']);
    }

    public function testCursorGuardaBuscaEPagina(): void
    {
        $this->assertSame(2005, ApolloNicheRules::encodeCursor(2, 5));
        $this->assertSame([2, 5], ApolloNicheRules::decodeCursor(2005, 3));
        // Campanha antiga (só página): busca 0.
        $this->assertSame([0, 7], ApolloNicheRules::decodeCursor(7, 1));
        $this->assertSame([0, 1], ApolloNicheRules::decodeCursor(null, 1));
        // Busca que não existe mais (nichos mudaram): recomeça.
        $this->assertSame([0, 1], ApolloNicheRules::decodeCursor(3004, 2));
    }

    public function testAvancoDePaginaEDeBusca(): void
    {
        $this->assertSame([0, 2, false], ApolloNicheRules::advance(0, 1, 10, 2, false));
        $this->assertSame([1, 1, true], ApolloNicheRules::advance(0, 10, 10, 2, false), 'última página: próxima busca');
        $this->assertSame([0, 1, true], ApolloNicheRules::advance(1, 3, 10, 2, true), 'página vazia na última busca: volta à primeira');
        $this->assertSame([0, 1, true], ApolloNicheRules::advance(0, 4, 4, 1, false));
    }
}
