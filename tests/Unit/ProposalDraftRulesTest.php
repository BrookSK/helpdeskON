<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ProposalDraftRules;

final class ProposalDraftRulesTest extends TestCase
{
    private function catalogo(): array
    {
        return [
            ['id' => 1, 'name' => 'Site institucional', 'description' => 'Site de 5 páginas', 'est_hours' => '40.00', 'hourly_rate' => '100.00', 'billing' => 'once', 'renewal' => 'none', 'recurrence_period' => null, 'is_hosting' => 0, 'active' => 1],
            ['id' => 2, 'name' => 'Hospedagem', 'description' => '', 'est_hours' => null, 'hourly_rate' => '80.00', 'billing' => 'monthly', 'renewal' => 'auto', 'recurrence_period' => 'monthly', 'is_hosting' => 1, 'active' => 1],
            ['id' => 3, 'name' => 'Serviço antigo', 'description' => '', 'est_hours' => null, 'hourly_rate' => '50.00', 'billing' => 'once', 'renewal' => 'none', 'recurrence_period' => null, 'is_hosting' => 0, 'active' => 0],
        ];
    }

    public function testSoServicoAtivoDoCatalogoViraItem(): void
    {
        $r = ProposalDraftRules::filterSuggestions(json_encode([
            'itens' => [
                ['service_id' => 1, 'quantidade' => 2, 'motivo' => 'Cliente quer presença online'],
                ['service_id' => 99, 'quantidade' => 1, 'motivo' => 'inexistente'],
                ['service_id' => 3, 'quantidade' => 1, 'motivo' => 'inativo'],
                ['service_id' => 1, 'quantidade' => 1, 'motivo' => 'repetido'],
            ],
            'fora_do_catalogo' => ['Integração com ERP'],
        ]), $this->catalogo());

        $this->assertCount(1, $r['items']);
        $this->assertSame(1, $r['items'][0]['service_id']);
        $this->assertSame('Site institucional', $r['items'][0]['description']);
        $this->assertSame(2, $r['items'][0]['quantity']);
        $this->assertSame('100.00', $r['items'][0]['hourly_rate']);
        $this->assertSame(3, $r['discarded']);
        $this->assertSame(['Site institucional: Cliente quer presença online'], $r['notes']);
        $this->assertSame(['Integração com ERP'], $r['outside']);
    }

    public function testQuantidadeLimitadaERespostaInvalida(): void
    {
        $r = ProposalDraftRules::filterSuggestions(['itens' => [['service_id' => 2, 'quantidade' => 5000]]], $this->catalogo());
        $this->assertSame(ProposalDraftRules::MAX_QUANTITY, $r['items'][0]['quantity']);
        $r = ProposalDraftRules::filterSuggestions(['itens' => [['service_id' => 2, 'quantidade' => -3]]], $this->catalogo());
        $this->assertSame(1, $r['items'][0]['quantity']);

        $vazio = ProposalDraftRules::filterSuggestions('não é json', $this->catalogo());
        $this->assertSame([], $vazio['items']);
        $this->assertSame('', ProposalDraftRules::observations($vazio));
    }

    public function testPromptListaSoServicosAtivos(): void
    {
        $msgs = ProposalDraftRules::buildMessages(['resumo' => 'r', 'necessidades' => ['site']], $this->catalogo());
        $user = $msgs[1]['content'];
        $this->assertStringContainsString('1: Site institucional (Site de 5 páginas)', $user);
        $this->assertStringContainsString('2: Hospedagem', $user);
        $this->assertStringNotContainsString('Serviço antigo', $user);
        $this->assertStringContainsString('- site', $user);
    }

    public function testEntendimentoDoNegocioVemDaMinuta(): void
    {
        $c = ProposalDraftRules::businessContent([
            'resumo' => 'Empresa de logística.', 'decisores' => ['Lucas'],
            'necessidades' => ['Organizar leads'], 'escopo' => ['CRM'], 'prazos' => ['Dezembro'],
            'orcamento' => [], 'topicos_nao_resolvidos' => [],
        ]);
        $this->assertSame("Empresa de logística.\n\nDecisores:\n- Lucas", $c['business_profile']);
        $this->assertSame("Necessidades do cliente:\n- Organizar leads\n\nEscopo desejado:\n- CRM", $c['business_opportunity']);
        $this->assertSame("Prazos citados:\n- Dezembro", $c['business_diagnosis']);
    }

    public function testObservacoesListamMotivoEForaDoCatalogo(): void
    {
        $t = ProposalDraftRules::observations(['notes' => ['Site: motivo'], 'outside' => ['ERP']]);
        $this->assertStringContainsString("- Site: motivo", $t);
        $this->assertStringContainsString("Pontos da reunião sem serviço no catálogo:\n- ERP", $t);
    }
}
