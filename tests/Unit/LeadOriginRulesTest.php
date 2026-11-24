<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use LeadOriginRules;

/**
 * Dono, origem e "dados do lead" no CRM (LeadOriginRules).
 *
 * Origem: a captação automática criava todos os leads em nome do primeiro
 * Super Admin (Lucas Narciso), não de quem fazia a prospecção (Evelyn), e o
 * card do CRM só mostrava cargo/empresa escondidos nas observações do briefing.
 */
final class LeadOriginRulesTest extends TestCase
{
    public function testDonoDoLeadEhOResponsavelDaCampanha(): void
    {
        $this->assertSame(7, LeadOriginRules::campaignLeadOwner(['assigned_to' => 7, 'created_by' => 3], 1));
    }

    public function testSemResponsavelVaiParaQuemCriouACampanha(): void
    {
        $this->assertSame(3, LeadOriginRules::campaignLeadOwner(['assigned_to' => null, 'created_by' => 3], 1));
        $this->assertSame(3, LeadOriginRules::campaignLeadOwner(['assigned_to' => 0, 'created_by' => '3'], 1));
    }

    public function testSemResponsavelNemCriadorCaiNoSuperAdmin(): void
    {
        $this->assertSame(1, LeadOriginRules::campaignLeadOwner([], 1));
        $this->assertNull(LeadOriginRules::campaignLeadOwner([], null));
    }

    public function testRotulosDeOrigem(): void
    {
        $a = LeadOriginRules::originBadge('prospeccao_auto', 'Captação Site Operacional');
        $this->assertSame('Automática', $a['short']);
        $this->assertSame('Prospecção automática · Captação Site Operacional', $a['label']);
        $this->assertSame('Prospecção manual (Apollo)', LeadOriginRules::originBadge('prospeccao_manual')['label']);
        $this->assertSame('Importado de planilha · leads.xlsx', LeadOriginRules::originBadge('planilha', 'leads.xlsx')['label']);
        $this->assertNull(LeadOriginRules::originBadge(null));
        $this->assertNull(LeadOriginRules::originBadge('outra'));
    }

    public function testLeObservacoesNoPadraoDaCaptacao(): void
    {
        $p = LeadOriginRules::parseNotes('Cargo: Diretora Clínica | Empresa: Clínica Sorriso | LinkedIn: https://linkedin.com/in/x');
        $this->assertSame('Diretora Clínica', $p['job_title']);
        $this->assertSame('Clínica Sorriso', $p['company']);
        $this->assertSame('https://linkedin.com/in/x', $p['linkedin']);

        $p = LeadOriginRules::parseNotes("Lead quente\ncargo: CEO");
        $this->assertSame('CEO', $p['job_title']);
        $this->assertNull($p['company']);
        $this->assertSame(['job_title' => null, 'company' => null, 'linkedin' => null], LeadOriginRules::parseNotes(null));
    }

    public function testPerfilPrioridadeColunasDepoisApolloDepoisObservacoes(): void
    {
        $p = LeadOriginRules::profile(
            ['lead_email' => 'ana@x.com', 'job_title' => 'CEO', 'company_name' => null, 'linkedin_url' => ''],
            ['title' => 'Founder', 'organization_name' => 'Acme', 'organization_website' => 'acme.com', 'city' => 'Curitiba', 'state' => 'PR', 'country' => 'Brazil', 'linkedin_url' => 'https://linkedin.com/in/ana'],
            'Empresa: Outra | Cargo: Outro'
        );
        $this->assertSame('ana@x.com', $p['email']);
        $this->assertSame('CEO', $p['job_title'], 'coluna do contato tem prioridade');
        $this->assertSame('Acme', $p['company'], 'depois o staging do Apollo');
        $this->assertSame('https://linkedin.com/in/ana', $p['linkedin']);
        $this->assertSame('acme.com', $p['website']);
        $this->assertSame('Curitiba, PR, Brazil', $p['location']);

        $p = LeadOriginRules::profile([], [], 'Cargo: Sócio | Empresa: Alfa');
        $this->assertSame('Sócio', $p['job_title']);
        $this->assertSame('Alfa', $p['company']);
        $this->assertNull($p['email']);
        $this->assertNull($p['location']);
    }

    public function testEmailNaoLiberadoDoApolloNaoAparece(): void
    {
        $p = LeadOriginRules::profile([], ['email' => 'email_not_unlocked@domain.com']);
        $this->assertNull($p['email']);
        $p = LeadOriginRules::profile([], ['email' => 'joao@empresa.com']);
        $this->assertSame('joao@empresa.com', $p['email']);
    }
}
