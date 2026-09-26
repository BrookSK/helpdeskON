<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use VideoRoomRules;

/**
 * Testes unitários das regras puras da Sala de Vídeo (sem banco).
 * Cobre limites de participantes, visibilidade, permissão de apresentação,
 * allowlist de sinais, extensão de gravação por MIME e o estado inicial de
 * mídia a partir do preview (regra por trás do bug do ícone de câmera).
 */
final class VideoRoomRulesTest extends TestCase
{
    // ---- participantes ----

    public function testClampParticipantsRespeitaLimites(): void
    {
        $this->assertSame(2, VideoRoomRules::clampParticipants(0));
        $this->assertSame(2, VideoRoomRules::clampParticipants(1));
        $this->assertSame(8, VideoRoomRules::clampParticipants(8));
        $this->assertSame(15, VideoRoomRules::clampParticipants(15));
        $this->assertSame(15, VideoRoomRules::clampParticipants(100));
        // string vira int
        $this->assertSame(10, VideoRoomRules::clampParticipants('10'));
    }

    // ---- visibilidade ----

    public function testVisibilidadeApenasPrivateExplicito(): void
    {
        $this->assertSame('private', VideoRoomRules::normalizeVisibility('private'));
        $this->assertSame('public', VideoRoomRules::normalizeVisibility('public'));
        $this->assertSame('public', VideoRoomRules::normalizeVisibility('qualquer'));
        $this->assertSame('public', VideoRoomRules::normalizeVisibility(''));
    }

    // ---- apresentação ----

    public function testAllowPresentationSoDesligaComZero(): void
    {
        $this->assertSame(0, VideoRoomRules::normalizeAllowPresentation('0'));
        $this->assertSame(0, VideoRoomRules::normalizeAllowPresentation(0));
        $this->assertSame(1, VideoRoomRules::normalizeAllowPresentation('1'));
        $this->assertSame(1, VideoRoomRules::normalizeAllowPresentation('sim'));
        $this->assertSame(1, VideoRoomRules::normalizeAllowPresentation(null));
    }

    // ---- sinais ----

    public function testSinaisValidosEInvalidos(): void
    {
        foreach (['offer', 'answer', 'ice', 'join', 'leave', 'end', 'reaction', 'hand', 'rec', 'state', 'forcemute'] as $k) {
            $this->assertTrue(VideoRoomRules::isValidSignal($k), "kind {$k} deveria ser válido");
        }
        $this->assertFalse(VideoRoomRules::isValidSignal('hackear'));
        $this->assertFalse(VideoRoomRules::isValidSignal(''));
    }

    public function testSinaisDeModeracaoExigemAdmin(): void
    {
        $this->assertTrue(VideoRoomRules::signalRequiresAdmin('end'));
        $this->assertTrue(VideoRoomRules::signalRequiresAdmin('forcemute'));
        $this->assertFalse(VideoRoomRules::signalRequiresAdmin('offer'));
        $this->assertFalse(VideoRoomRules::signalRequiresAdmin('reaction'));
    }

    public function testSinaisEfemeros(): void
    {
        $this->assertTrue(VideoRoomRules::isEphemeralSignal('reaction'));
        $this->assertTrue(VideoRoomRules::isEphemeralSignal('hand'));
        $this->assertFalse(VideoRoomRules::isEphemeralSignal('offer'));
        $this->assertFalse(VideoRoomRules::isEphemeralSignal('join'));
    }

    // ---- gravação ----

    public function testExtensaoDeGravacaoPorMime(): void
    {
        $this->assertSame('mp4', VideoRoomRules::recordingExtension('video/mp4'));
        $this->assertSame('webm', VideoRoomRules::recordingExtension('video/webm'));
        $this->assertSame('webm', VideoRoomRules::recordingExtension(''));
        $this->assertSame('webm', VideoRoomRules::recordingExtension(null));
        $this->assertSame('webm', VideoRoomRules::recordingExtension('application/octet-stream'));
    }

    public function testMimeAceitavelParaGravacao(): void
    {
        $this->assertTrue(VideoRoomRules::isAcceptableRecordingMime('video/webm'));
        $this->assertTrue(VideoRoomRules::isAcceptableRecordingMime('video/mp4'));
        $this->assertTrue(VideoRoomRules::isAcceptableRecordingMime('video/x-matroska'));
        $this->assertFalse(VideoRoomRules::isAcceptableRecordingMime('application/pdf'));
        $this->assertFalse(VideoRoomRules::isAcceptableRecordingMime(''));
        $this->assertFalse(VideoRoomRules::isAcceptableRecordingMime(null));
    }

    // ---- estado inicial de mídia (bug do ícone de câmera) ----

    public function testEstadoInicialComTudoLigado(): void
    {
        $s = VideoRoomRules::initialMediaState(true, true);
        $this->assertTrue($s['micOn']);
        $this->assertTrue($s['camOn']);
        $this->assertFalse($s['micButtonOff']);
        $this->assertFalse($s['camButtonOff']);
    }

    public function testEntrarComCameraDesligadaMarcaBotaoComoOff(): void
    {
        // Cenário do bug: entrou com câmera desligada no preview.
        $s = VideoRoomRules::initialMediaState(true, false);
        $this->assertTrue($s['micOn']);
        $this->assertFalse($s['camOn']);
        // O ícone da câmera DEVE ficar "off" (vermelho) na entrada.
        $this->assertTrue($s['camButtonOff']);
        $this->assertFalse($s['micButtonOff']);
    }

    public function testEntrarComMicrofoneMudo(): void
    {
        $s = VideoRoomRules::initialMediaState(false, true);
        $this->assertFalse($s['micOn']);
        $this->assertTrue($s['camOn']);
        $this->assertTrue($s['micButtonOff']);
        $this->assertFalse($s['camButtonOff']);
    }

    public function testEntrarSoParaOuvirTudoDesligado(): void
    {
        $s = VideoRoomRules::initialMediaState(false, false);
        $this->assertTrue($s['micButtonOff']);
        $this->assertTrue($s['camButtonOff']);
    }

    // ---- rótulo do link de reunião (Google Meet real x sala de vídeo do sistema) ----

    public function testDetectaLinkDaSalaNativa(): void
    {
        // Cenário do bug: link é a sala interna do helpdesk, não o Google Meet.
        $url = 'https://helpdesk.onsolutionsbrasil.com.br/videocall/room/f97c2bc7f8b52bed1569ff8b7012cc93';
        $this->assertTrue(VideoRoomRules::isNativeRoomLink($url));
        $this->assertFalse(VideoRoomRules::isGoogleMeetLink($url));
        // Sem domínio (rota relativa) também conta como sala nativa.
        $this->assertTrue(VideoRoomRules::isNativeRoomLink('/videocall/room/abc'));
    }

    public function testDetectaLinkDoGoogleMeet(): void
    {
        $url = 'https://meet.google.com/abc-defg-hij';
        $this->assertTrue(VideoRoomRules::isGoogleMeetLink($url));
        $this->assertFalse(VideoRoomRules::isNativeRoomLink($url));
    }

    public function testLinkVazioOuNuloNaoEhNenhumDosDois(): void
    {
        foreach (['', null] as $v) {
            $this->assertFalse(VideoRoomRules::isNativeRoomLink($v));
            $this->assertFalse(VideoRoomRules::isGoogleMeetLink($v));
        }
    }

    public function testRotuloDoBotaoConformeTipoDeLink(): void
    {
        // Meet real -> menciona Google Meet.
        $this->assertSame(
            'Entrar na reunião (Google Meet)',
            VideoRoomRules::meetingButtonLabel('https://meet.google.com/abc-defg-hij')
        );
        // Sala nativa -> NÃO pode dizer Google Meet (era o bug).
        $labelSala = VideoRoomRules::meetingButtonLabel('https://helpdesk.onsolutionsbrasil.com.br/videocall/room/xyz');
        $this->assertSame('Entrar na sala de vídeo', $labelSala);
        $this->assertStringNotContainsStringIgnoringCase('Google Meet', $labelSala);
        // Indefinido -> rótulo neutro, sem prometer provedor.
        $this->assertSame('Entrar na reunião', VideoRoomRules::meetingButtonLabel(''));
        $this->assertSame('Entrar na reunião', VideoRoomRules::meetingButtonLabel(null));
    }

    public function testRotuloDoCanalConformeTipoDeLink(): void
    {
        $this->assertSame('Google Meet', VideoRoomRules::meetingChannelLabel('https://meet.google.com/abc'));
        $this->assertSame('sala de vídeo', VideoRoomRules::meetingChannelLabel('https://x/videocall/room/1'));
        $this->assertSame('reunião', VideoRoomRules::meetingChannelLabel(''));
    }
}
