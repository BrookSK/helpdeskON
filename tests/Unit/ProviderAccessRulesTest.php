<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ProviderAccessRules;

/**
 * Testes da regra pura que deriva o PAPEL do usuário de acesso do prestador a
 * partir da função — "como desenvolvedor, ou marketing etc.".
 */
final class ProviderAccessRulesTest extends TestCase
{
    public function testDesenvolvedor(): void
    {
        $this->assertSame('developer', ProviderAccessRules::roleForProvider('Dev PHP pleno'));
        $this->assertSame('developer', ProviderAccessRules::roleForProvider('Programador back-end'));
        $this->assertSame('developer', ProviderAccessRules::roleForProvider('Engenheiro de software'));
    }

    public function testMarketing(): void
    {
        $this->assertSame('marketing', ProviderAccessRules::roleForProvider('Analista de Marketing'));
        $this->assertSame('marketing', ProviderAccessRules::roleForProvider('Social media'));
        $this->assertSame('marketing', ProviderAccessRules::roleForProvider('Designer'));
    }

    public function testAtendenteEAnalista(): void
    {
        $this->assertSame('attendant', ProviderAccessRules::roleForProvider('Suporte N1'));
        $this->assertSame('analyst', ProviderAccessRules::roleForProvider('Analista de QA'));
    }

    public function testDefaultDeveloper(): void
    {
        $this->assertSame('developer', ProviderAccessRules::roleForProvider(null));
        $this->assertSame('developer', ProviderAccessRules::roleForProvider(''));
        $this->assertSame('developer', ProviderAccessRules::roleForProvider('função não mapeada'));
    }
}
