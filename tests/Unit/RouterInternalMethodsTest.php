<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Router;

final class RouterInternalMethodsTest extends TestCase
{
    public function testMetodosDoCronNaoSaoRotaPelaUrl(): void
    {
        $this->assertTrue(Router::isInternal('VideocallController', 'completeTranscription'));
        $this->assertTrue(Router::isInternal('VideocallController', 'finalizeAbandonedSessions'));
    }

    public function testCaixaDiferenteNaoEscapaDoBloqueio(): void
    {
        // method_exists ignora caixa: /videocall/completetranscription chegaria ao método.
        $this->assertTrue(Router::isInternal('VideocallController', 'completetranscription'));
        $this->assertTrue(Router::isInternal('VideocallController', 'FINALIZEABANDONEDSESSIONS'));
    }

    public function testRotasNormaisContinuamLiberadas(): void
    {
        $this->assertFalse(Router::isInternal('VideocallController', 'recAudioV2'));
        $this->assertFalse(Router::isInternal('VideocallController', 'watch'));
        $this->assertFalse(Router::isInternal('ProposalController', 'fromRecording'));
    }
}
