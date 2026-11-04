<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use DocumentRules;

/**
 * Testes unitários das regras puras de VISIBILIDADE de documentos, após a
 * remoção da opção "Todos" (all). Só valem 'company' e 'team'; qualquer outro
 * valor deve cair no padrão seguro 'company'.
 */
final class DocumentRulesTest extends TestCase
{
    public function testAceitaCompanyETeam(): void
    {
        $this->assertSame('company', DocumentRules::normalizeVisibility('company'));
        $this->assertSame('team', DocumentRules::normalizeVisibility('team'));
    }

    public function testAntigoAllViraCompany(): void
    {
        // "Todos" não existe mais: o legado 'all' é normalizado para 'company'
        // (restrito à empresa, nunca global).
        $this->assertSame('company', DocumentRules::normalizeVisibility('all'));
    }

    public function testValorDesconhecidoViraCompany(): void
    {
        $this->assertSame('company', DocumentRules::normalizeVisibility('qualquer'));
        $this->assertSame('company', DocumentRules::normalizeVisibility(''));
        $this->assertSame('company', DocumentRules::normalizeVisibility(null));
        $this->assertSame('company', DocumentRules::normalizeVisibility(123));
    }

    public function testNormalizaCaixaEEspacos(): void
    {
        $this->assertSame('team', DocumentRules::normalizeVisibility('  TEAM '));
        $this->assertSame('company', DocumentRules::normalizeVisibility('Company'));
    }

    public function testConjuntoDeVisibilidades(): void
    {
        // Garante que a lista oficial tem exatamente as duas opções esperadas.
        $this->assertSame(['company', 'team'], DocumentRules::VISIBILITIES);
    }
}
