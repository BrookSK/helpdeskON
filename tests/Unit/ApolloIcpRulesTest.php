<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ApolloIcpRules;

/**
 * ICP e score da prospecção sobre os dados da busca do Apollo (ApolloIcpRules).
 *
 * Bug de produção: a campanha "Captação Site Operacional (11 - 50 func)"
 * encontrava 350 pessoas por dia e reprovava todas como "fora do perfil (ICP)".
 * A busca atual do Apollo (/mixed_people/api_search) não devolve seniority,
 * país, site nem nº de funcionários; o filtro lia esses campos vazios e
 * reprovava todo mundo. Os payloads abaixo seguem o formato da documentação
 * oficial dessa busca.
 */
final class ApolloIcpRulesTest extends TestCase
{
    /** Pessoa no formato EXATO da busca nova (sem seniority/país/site/porte). */
    private function apiSearchPerson(string $title, array $orgOver = []): array
    {
        return [
            'id' => '66ed23831ae8c9000186c75b',
            'first_name' => 'Nicholas',
            'last_name_obfuscated' => 'Th***n',
            'title' => $title,
            'has_email' => true, 'has_city' => true, 'has_state' => true, 'has_country' => true,
            'has_direct_phone' => 'Yes',
            'organization' => array_merge([
                'name' => 'Clínica Exemplo', 'has_industry' => true, 'has_phone' => true,
                'has_city' => true, 'has_state' => true, 'has_country' => true,
                'has_zip_code' => true, 'has_revenue' => false, 'has_employee_count' => true,
            ], $orgOver),
        ];
    }

    /** ICP da campanha do print. */
    private function icpDoPrint(): array
    {
        return [
            'seniorities' => ['owner', 'founder', 'c_suite', 'partner', 'director'],
            'require_website' => true,
            'score' => ['decisor' => 35, 'title' => 30, 'size' => 10, 'region' => 15, 'website' => 10, 'technology' => 0],
        ];
    }

    /** Filtros de busca da campanha do print. */
    private function buscaDoPrint(): array
    {
        return [
            'person_titles' => ['ceo', 'sócio', 'proprietário', 'Founder'],
            'person_locations' => ['Brazil'],
            'organization_num_employees_ranges' => ['11,50'],
            'q_keywords' => 'saúde',
        ];
    }

    // ---- cenário do print ----

    public function testCenarioDoPrintPassaNoIcpEAtingeScoreMinimo(): void
    {
        $icp = $this->icpDoPrint();
        $filters = ApolloIcpRules::effectiveSearchFilters($this->buscaDoPrint(), $icp);

        foreach (['CEO', 'Sócio-proprietário', 'Founder & CEO', 'Proprietária', 'Diretora Clínica'] as $title) {
            $p = $this->apiSearchPerson($title);
            $this->assertTrue(ApolloIcpRules::matchesIcp($p, $icp, $filters), "Reprovou '$title' no ICP");
            $this->assertGreaterThanOrEqual(50, ApolloIcpRules::score($p, $icp, $filters), "Score baixo para '$title'");
        }
    }

    public function testCargoSemSenioridadeReconhecivelNaoEhReprovadoPorFaltaDeDado(): void
    {
        $icp = $this->icpDoPrint();
        $filters = ApolloIcpRules::effectiveSearchFilters($this->buscaDoPrint(), $icp);
        // "Odontologista responsável" não diz a seniority; o Apollo já filtrou por ela.
        $p = $this->apiSearchPerson('Odontologista responsável');
        $this->assertTrue(ApolloIcpRules::matchesIcp($p, $icp, $filters));
        $this->assertSame(60, ApolloIcpRules::score($p, $icp, $filters), 'decisor (busca só de decisores) + porte + região');
    }

    // ---- envio do ICP para a busca ----

    public function testIcpVaiParaABuscaQuandoACampanhaNaoFiltrou(): void
    {
        $f = ApolloIcpRules::effectiveSearchFilters(['person_titles' => ['ceo']], ['seniorities' => ['owner', 'c_suite'], 'employee_min' => 11, 'employee_max' => 200]);
        $this->assertSame(['owner', 'c_suite'], $f['person_seniorities']);
        $this->assertSame(['11,200'], $f['organization_num_employees_ranges']);
        $this->assertSame(['ceo'], $f['person_titles']);

        $f = ApolloIcpRules::effectiveSearchFilters([], ['employee_min' => 51]);
        $this->assertSame(['51,1000000'], $f['organization_num_employees_ranges']);
    }

    public function testFiltroDaBuscaDefinidoPelaCampanhaNaoEhSobrescrito(): void
    {
        $f = ApolloIcpRules::effectiveSearchFilters(
            ['person_seniorities' => ['director'], 'organization_num_employees_ranges' => ['11,50']],
            ['seniorities' => ['owner'], 'employee_min' => 100]
        );
        $this->assertSame(['director'], $f['person_seniorities']);
        $this->assertSame(['11,50'], $f['organization_num_employees_ranges']);
        $this->assertSame([], ApolloIcpRules::effectiveSearchFilters([], []));
    }

    // ---- regressão: quando o dado VEM, o ICP continua reprovando ----

    public function testSeniorityPresenteEForaDoIcpReprova(): void
    {
        $icp = ['seniorities' => ['owner', 'c_suite']];
        $p = ['id' => 'x', 'title' => 'Analista', 'seniority' => 'entry', 'organization' => []];
        $this->assertFalse(ApolloIcpRules::matchesIcp($p, $icp, ['person_seniorities' => ['owner', 'c_suite']]));
        $p['seniority'] = 'owner';
        $this->assertTrue(ApolloIcpRules::matchesIcp($p, $icp));
    }

    public function testCargoDeduzidoForaDoIcpReprovaSeABuscaNaoGarante(): void
    {
        $icp = ['seniorities' => ['owner']];
        $p = $this->apiSearchPerson('Gerente Comercial');
        // Sem filtro de seniority na busca: "Gerente" = manager, fora do ICP.
        $this->assertFalse(ApolloIcpRules::matchesIcp($p, $icp, []));
        // Busca filtrada pelo Apollo só com as senioridades do ICP: confia no Apollo.
        $this->assertTrue(ApolloIcpRules::matchesIcp($p, $icp, ['person_seniorities' => ['owner']]));
    }

    public function testPorteEsiteConhecidosForaDoIcpReprovam(): void
    {
        $icp = ['employee_min' => 11, 'employee_max' => 50, 'require_website' => true];
        $p = ['id' => 'x', 'title' => 'CEO', 'organization' => ['estimated_num_employees' => 300, 'website_url' => 'https://a.com']];
        $this->assertFalse(ApolloIcpRules::matchesIcp($p, $icp));
        $p['organization']['estimated_num_employees'] = 30;
        $this->assertTrue(ApolloIcpRules::matchesIcp($p, $icp));
        // Site informado como vazio (formato antigo, campo presente): reprova.
        $p['organization']['website_url'] = null;
        $p['organization']['primary_domain'] = null;
        $this->assertFalse(ApolloIcpRules::matchesIcp($p, $icp));
        // Campo de site ausente (busca nova): desconhecido, não reprova.
        unset($p['organization']['website_url'], $p['organization']['primary_domain']);
        $this->assertTrue(ApolloIcpRules::matchesIcp($p, $icp));
    }

    public function testCargoContemDoIcp(): void
    {
        $icp = ['titles_any' => ['diretor', 'ceo']];
        $this->assertTrue(ApolloIcpRules::matchesIcp($this->apiSearchPerson('Diretor de Operações'), $icp));
        $this->assertFalse(ApolloIcpRules::matchesIcp($this->apiSearchPerson('Analista Financeiro'), $icp));
        // Sem cargo na busca: desconhecido.
        $this->assertTrue(ApolloIcpRules::matchesIcp($this->apiSearchPerson(''), $icp));
        $this->assertTrue(ApolloIcpRules::matchesIcp($this->apiSearchPerson('Analista'), []));
    }

    // ---- dedução da seniority pelo cargo ----

    public function testInferSeniority(): void
    {
        $this->assertSame('c_suite', ApolloIcpRules::inferSeniority('CEO'));
        $this->assertSame('founder', ApolloIcpRules::inferSeniority('Co-Founder & CTO'));
        $this->assertSame('owner', ApolloIcpRules::inferSeniority('Proprietário'));
        $this->assertSame('partner', ApolloIcpRules::inferSeniority('Sócia'));
        $this->assertSame('director', ApolloIcpRules::inferSeniority('Diretora Clínica'));
        $this->assertSame('manager', ApolloIcpRules::inferSeniority('Gerente de Vendas'));
        $this->assertNull(ApolloIcpRules::inferSeniority('Médico'));
        $this->assertNull(ApolloIcpRules::inferSeniority(''));
        // "cio" não casa dentro de "sócio" (palavra inteira).
        $this->assertSame('partner', ApolloIcpRules::inferSeniority('sócio'));
        $this->assertSame('vp', ApolloIcpRules::seniorityOf(['seniority' => 'VP', 'title' => 'CEO']));
    }

    // ---- score ----

    public function testScoreComDadosCompletosContinuaIgualAoAntigo(): void
    {
        $icp = ['titles_any' => ['ceo'], 'employee_min' => 11, 'employee_max' => 50];
        $p = ['id' => 'x', 'title' => 'CEO', 'seniority' => 'c_suite', 'country' => 'Brazil',
              'organization' => ['estimated_num_employees' => 30, 'website_url' => 'https://a.com']];
        $this->assertSame(35 + 30 + 10 + 15 + 10, ApolloIcpRules::score($p, $icp));

        $p['seniority'] = 'entry';
        $p['title'] = 'Estagiário';
        $p['organization']['estimated_num_employees'] = 500;
        $this->assertSame(15 + 10, ApolloIcpRules::score($p, $icp));
    }

    public function testScoreNaoGanhaPortePorFlagSemFiltroDeFaixa(): void
    {
        $p = $this->apiSearchPerson('Analista');
        $this->assertSame(15, ApolloIcpRules::score($p, [], []), 'só região');
        $this->assertSame(25, ApolloIcpRules::score($p, [], ['organization_num_employees_ranges' => ['11,50']]));
        $p['organization']['has_employee_count'] = false;
        $this->assertSame(15, ApolloIcpRules::score($p, [], ['organization_num_employees_ranges' => ['11,50']]));
    }

    public function testScoreDecisorPelaBuscaSoQuandoTodasAsSenioridadesSaoDecisoras(): void
    {
        $p = $this->apiSearchPerson('Médico');
        $this->assertSame(35 + 15, ApolloIcpRules::score($p, [], ['person_seniorities' => ['owner', 'director']]));
        $this->assertSame(15, ApolloIcpRules::score($p, [], ['person_seniorities' => ['owner', 'manager']]));
    }

    // ---- paginação ----

    public function testTotalPagesFormatosAntigoENovo(): void
    {
        $this->assertSame(7, ApolloIcpRules::totalPages(['pagination' => ['total_pages' => 7]], 50));
        $this->assertSame(3, ApolloIcpRules::totalPages(['total_entries' => 101, 'people' => []], 50));
        $this->assertSame(2, ApolloIcpRules::totalPages(['pagination' => ['total_entries' => 60]], 50));
        $this->assertSame(500, ApolloIcpRules::totalPages(['total_entries' => 232764882], 100), 'limite do Apollo');
        $this->assertSame(1, ApolloIcpRules::totalPages(['total_entries' => 0], 50));
        $this->assertSame(1, ApolloIcpRules::totalPages([], 50));
    }
}
