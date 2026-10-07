<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use MinutesAckRules;

/**
 * Testes unitários das regras puras do reconhecimento/assinatura da minuta.
 * Cobre: normalização de status, quando pode reconhecer/contestar (minuta
 * precisa estar 'done' e não reconhecida), motivo obrigatório na contestação,
 * e o reset ao reenviar.
 */
final class MinutesAckRulesTest extends TestCase
{
    public function testNormalizeAckStatus(): void
    {
        $this->assertSame('pending', MinutesAckRules::normalizeAckStatus(null));
        $this->assertSame('pending', MinutesAckRules::normalizeAckStatus(''));
        $this->assertSame('pending', MinutesAckRules::normalizeAckStatus('qualquer'));
        $this->assertSame('acknowledged', MinutesAckRules::normalizeAckStatus('acknowledged'));
        $this->assertSame('contested', MinutesAckRules::normalizeAckStatus('contested'));
    }

    public function testSoReconheceMinutaPronta(): void
    {
        // Minuta pronta e pendente: pode.
        $this->assertTrue(MinutesAckRules::canAcknowledge('done', 'pending'));
        // Minuta não pronta: não pode, independentemente do aceite.
        $this->assertFalse(MinutesAckRules::canAcknowledge('none', 'pending'));
        $this->assertFalse(MinutesAckRules::canAcknowledge('processing', 'pending'));
        $this->assertFalse(MinutesAckRules::canAcknowledge('error', 'pending'));
        $this->assertFalse(MinutesAckRules::canAcknowledge(null, 'pending'));
    }

    public function testNaoReconheceDuasVezes(): void
    {
        // Já reconhecida: não reconhece de novo (idempotência de aceite).
        $this->assertFalse(MinutesAckRules::canAcknowledge('done', 'acknowledged'));
    }

    public function testMinutaContestadaPodeSerReconhecidaDepois(): void
    {
        // Após contestar e a equipe reenviar, o cliente pode reconhecer.
        $this->assertTrue(MinutesAckRules::canAcknowledge('done', 'contested'));
    }

    public function testSoContestaMinutaProntaENaoReconhecida(): void
    {
        $this->assertTrue(MinutesAckRules::canContest('done', 'pending'));
        $this->assertTrue(MinutesAckRules::canContest('done', 'contested')); // recontestar atualiza o motivo
        $this->assertFalse(MinutesAckRules::canContest('done', 'acknowledged'));
        $this->assertFalse(MinutesAckRules::canContest('processing', 'pending'));
        $this->assertFalse(MinutesAckRules::canContest(null, 'pending'));
    }

    public function testMotivoDaContestacaoEhObrigatorio(): void
    {
        $this->assertNull(MinutesAckRules::sanitizeContestReason(''));
        $this->assertNull(MinutesAckRules::sanitizeContestReason('   '));
        $this->assertNull(MinutesAckRules::sanitizeContestReason(null));
        $this->assertSame('Faltou o prazo', MinutesAckRules::sanitizeContestReason('  Faltou o prazo  '));
    }

    public function testMotivoRespeitaLimiteDeTamanho(): void
    {
        $long = str_repeat('x', MinutesAckRules::MAX_CONTEST_REASON + 50);
        $out = MinutesAckRules::sanitizeContestReason($long);
        $this->assertNotNull($out);
        $this->assertSame(MinutesAckRules::MAX_CONTEST_REASON, mb_strlen($out));
    }

    public function testIsAcknowledged(): void
    {
        $this->assertTrue(MinutesAckRules::isAcknowledged('acknowledged'));
        $this->assertFalse(MinutesAckRules::isAcknowledged('pending'));
        $this->assertFalse(MinutesAckRules::isAcknowledged('contested'));
        $this->assertFalse(MinutesAckRules::isAcknowledged(null));
    }

    public function testResetForResend(): void
    {
        // Reenviar reabre para 'pending'...
        $this->assertSame('pending', MinutesAckRules::resetForResend('pending'));
        $this->assertSame('pending', MinutesAckRules::resetForResend('contested'));
        // ...mas nunca reabre uma minuta já reconhecida.
        $this->assertSame('acknowledged', MinutesAckRules::resetForResend('acknowledged'));
    }
}
