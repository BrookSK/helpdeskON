<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ApolloApi;

/**
 * Corpo enviado à busca de pessoas do Apollo: o segmento (indústria) da
 * empresa precisa chegar inteiro. Nomes como "health, wellness & fitness"
 * têm vírgula e não podem ser quebrados.
 */
final class ApolloApiPayloadTest extends TestCase
{
    private function payload(array $filters): array
    {
        $api = (new \ReflectionClass(ApolloApi::class))->newInstanceWithoutConstructor();
        $m = new \ReflectionMethod(ApolloApi::class, 'buildPeopleSearchPayload');
        return $m->invoke($api, $filters);
    }

    public function testSegmentosDaEmpresaVaoParaABusca(): void
    {
        $p = $this->payload(['organization_industries' => ['hospital & health care', 'health, wellness & fitness'], 'person_titles' => ['ceo']]);
        $this->assertSame(['hospital & health care', 'health, wellness & fitness'], $p['organization_industries']);
        $this->assertSame(['ceo'], $p['person_titles']);
        $this->assertArrayNotHasKey('q_keywords', $p);
    }

    public function testPalavraChaveUnicaContinuaFuncionando(): void
    {
        $p = $this->payload(['q_keywords' => ' piscinas ']);
        $this->assertSame('piscinas', $p['q_keywords']);
        $this->assertArrayNotHasKey('organization_industries', $p);
    }
}
