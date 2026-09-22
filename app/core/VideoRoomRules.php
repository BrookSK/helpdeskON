<?php

/**
 * Regras de negócio puras (sem banco/HTTP) da Sala de Vídeo (videochamada nativa).
 *
 * Extraídas do VideocallController/room.php para ficarem testáveis por unidade e
 * servirem de fonte única de verdade para: limites de participantes, visibilidade,
 * permissão de apresentação, allowlist de sinais WebRTC, extensão do arquivo de
 * gravação por MIME, e o estado inicial de mídia (câmera/microfone) que a sala
 * deve assumir a partir do preview/lobby.
 */
class VideoRoomRules
{
    /** Limites do teto de participantes (mesh degrada acima de ~6). */
    public const MIN_PARTICIPANTS = 2;
    public const MAX_PARTICIPANTS = 15;
    public const DEFAULT_PARTICIPANTS = 15;

    /** Visibilidades possíveis de uma sala. */
    public const VISIBILITIES = ['public', 'private'];

    /**
     * Tipos de sinal (kind) aceitos pela sinalização. Espelha o ENUM final de
     * video_room_signals.kind (migrations 117 + 118/120/121/123/125/126).
     */
    public const SIGNAL_KINDS = [
        'offer', 'answer', 'ice', 'join', 'leave', 'media', 'screen', 'end',
        'kick', 'request', 'admit', 'deny', 'reaction', 'hand', 'perm', 'rec',
        'state', 'forcemute',
    ];

    /** Sinais que exigem privilégio de administrador da sala. */
    public const ADMIN_ONLY_SIGNALS = ['end', 'forcemute'];

    /** Sinais efêmeros: só valem em tempo real (descartados se atrasam). */
    public const EPHEMERAL_SIGNALS = ['reaction', 'hand'];

    /** Segundos sem heartbeat até considerar o participante "saiu". */
    public const PRESENCE_TIMEOUT = 10;

    /** Janela (segundos) além da qual um sinal efêmero é considerado lixo. */
    public const EPHEMERAL_CUTOFF = 8;

    /**
     * Limita o teto de participantes ao intervalo seguro [2, 15].
     */
    public static function clampParticipants($value): int
    {
        $n = (int) $value;
        if ($n < self::MIN_PARTICIPANTS) return self::MIN_PARTICIPANTS;
        if ($n > self::MAX_PARTICIPANTS) return self::MAX_PARTICIPANTS;
        return $n;
    }

    /**
     * Normaliza a visibilidade: 'private' só quando explicitamente 'private';
     * qualquer outra coisa vira 'public'.
     */
    public static function normalizeVisibility($value): string
    {
        return $value === 'private' ? 'private' : 'public';
    }

    /**
     * Normaliza a permissão de apresentar (compartilhar tela). Só desliga quando
     * o valor for exatamente '0' (checkbox desmarcado); padrão é permitir.
     */
    public static function normalizeAllowPresentation($value): int
    {
        return ($value === '0' || $value === 0 || $value === false) ? 0 : 1;
    }

    /**
     * Um tipo de sinal é válido para a sinalização?
     */
    public static function isValidSignal($kind): bool
    {
        return in_array($kind, self::SIGNAL_KINDS, true);
    }

    /**
     * Este sinal exige que o remetente seja admin da sala?
     */
    public static function signalRequiresAdmin($kind): bool
    {
        return in_array($kind, self::ADMIN_ONLY_SIGNALS, true);
    }

    /**
     * Este sinal é efêmero (só vale em tempo real)?
     */
    public static function isEphemeralSignal($kind): bool
    {
        return in_array($kind, self::EPHEMERAL_SIGNALS, true);
    }

    /**
     * Decide a extensão do arquivo de gravação a partir do MIME informado.
     * Retorna 'mp4' quando o MIME é mp4; caso contrário 'webm' (padrão do
     * MediaRecorder). Não decide se o arquivo é aceito — só a extensão.
     */
    public static function recordingExtension(?string $mime): string
    {
        return (is_string($mime) && strpos($mime, 'mp4') !== false) ? 'mp4' : 'webm';
    }

    /**
     * Um MIME representa vídeo aceitável para gravação (webm/mp4/video-*)?
     */
    public static function isAcceptableRecordingMime(?string $mime): bool
    {
        if (!is_string($mime) || $mime === '') return false;
        return strpos($mime, 'webm') !== false
            || strpos($mime, 'mp4') !== false
            || strpos($mime, 'video/') === 0;
    }

    /**
     * Estado inicial de mídia da sala a partir das escolhas do preview/lobby.
     *
     * Regra central por trás do bug do ícone de câmera: o estado escolhido no
     * preview (lobbyMic/lobbyCam) É o estado com que a pessoa entra na sala. A
     * toolbar deve refletir isso na entrada — inclusive marcando o ícone de
     * câmera como "desativado" quando a pessoa entrou com a câmera desligada.
     *
     * @return array{micOn:bool, camOn:bool, micButtonOff:bool, camButtonOff:bool}
     */
    public static function initialMediaState(bool $lobbyMic, bool $lobbyCam): array
    {
        $micOn = $lobbyMic;
        $camOn = $lobbyCam;
        return [
            'micOn' => $micOn,
            'camOn' => $camOn,
            // O botão fica no estado "off" (vermelho) quando a mídia está desligada.
            'micButtonOff' => !$micOn,
            'camButtonOff' => !$camOn,
        ];
    }
}
