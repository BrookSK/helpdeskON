<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use DemandDateRules;

/**
 * Regras das datas da demanda (início, término, previsão de término).
 * As datas são definidas pela equipe: o cliente nunca as informa.
 */
final class DemandDateRulesTest extends TestCase
{
    private const OK = [
        'data_inicio' => '2026-11-10',
        'data_termino' => '2026-11-20',
        'data_prevista_entrega' => '2026-11-25',
    ];

    public function testClienteNaoPodeDefinirDatas(): void
    {
        $this->assertFalse(DemandDateRules::canSetDates('client'));
        $this->assertFalse(DemandDateRules::canSetDates(null));
        $this->assertFalse(DemandDateRules::canSetDates(''));
        $this->assertFalse(DemandDateRules::canSetDates('papel_inexistente'));
    }

    public function testEquipePodeDefinirDatas(): void
    {
        foreach (['super_admin', 'attendant', 'whatsapp_agent', 'developer', 'analyst'] as $role) {
            $this->assertTrue(DemandDateRules::canSetDates($role), $role);
        }
    }

    public function testCriacaoPeloClienteIgnoraDatasEnviadas(): void
    {
        // Mesmo que o cliente forje o POST com as datas, nada é gravado.
        $r = DemandDateRules::forCreate('client', self::OK);
        $this->assertTrue($r['ok']);
        $this->assertSame([], $r['data']);

        // Sem datas também passa (o formulário do cliente não tem os campos).
        $r = DemandDateRules::forCreate('client', []);
        $this->assertTrue($r['ok']);
        $this->assertSame([], $r['data']);
    }

    public function testCriacaoPelaEquipeExigeAsTresDatas(): void
    {
        foreach (['super_admin', 'developer'] as $role) {
            $r = DemandDateRules::forCreate($role, self::OK);
            $this->assertTrue($r['ok'], $role);
            $this->assertSame(self::OK, $r['data']);

            foreach (array_keys(self::OK) as $missing) {
                $in = self::OK;
                $in[$missing] = '  ';
                $r = DemandDateRules::forCreate($role, $in);
                $this->assertFalse($r['ok'], "{$role} sem {$missing}");
                $this->assertStringContainsString('Informe', $r['error']);
            }
        }
    }

    public function testDataInvalidaERejeitada(): void
    {
        foreach (['2026-02-30', '10/11/2026', 'abc', '2026-13-01'] as $bad) {
            $in = self::OK;
            $in['data_termino'] = $bad;
            $r = DemandDateRules::forCreate('super_admin', $in);
            $this->assertFalse($r['ok'], $bad);
            $this->assertStringContainsString('inválidas', $r['error']);

            $r = DemandDateRules::parse($in, false);
            $this->assertFalse($r['ok'], "edição: {$bad}");
        }
    }

    public function testEdicaoPermiteDatasVaziasComoNull(): void
    {
        $r = DemandDateRules::parse(['data_inicio' => '2026-11-10', 'data_termino' => '', 'data_prevista_entrega' => ' '], false);
        $this->assertTrue($r['ok']);
        $this->assertSame([
            'data_inicio' => '2026-11-10',
            'data_termino' => null,
            'data_prevista_entrega' => null,
        ], $r['data']);

        // Tudo vazio limpa as três datas.
        $r = DemandDateRules::parse([], false);
        $this->assertTrue($r['ok']);
        $this->assertSame(['data_inicio' => null, 'data_termino' => null, 'data_prevista_entrega' => null], $r['data']);
    }

    public function testIgnoraCamposForaDasDatas(): void
    {
        $r = DemandDateRules::parse(self::OK + ['status' => 'closed', 'client_id' => 1], false);
        $this->assertSame(array_keys(self::OK), array_keys($r['data']));
    }

    public function testValidacaoDeFormato(): void
    {
        $this->assertTrue(DemandDateRules::isValidDate('2028-02-29'));
        $this->assertFalse(DemandDateRules::isValidDate('2027-02-29'));
        $this->assertFalse(DemandDateRules::isValidDate(''));
        $this->assertFalse(DemandDateRules::isValidDate(null));
        $this->assertFalse(DemandDateRules::isValidDate(20261110));
    }
}
