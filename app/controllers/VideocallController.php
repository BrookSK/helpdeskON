<?php

/**
 * Videochamada em GRUPO nativa (WebRTC mesh, sem API externa).
 *
 * Fluxo:
 *   [logado]  POST /videocall/create            → cria uma sala e devolve o link público
 *   [público] GET  /videocall/room/{token}      → tela da chamada (entra sem login)
 *   [público] POST /videocall/join/{token}      → registra presença; devolve peers atuais
 *   [público] POST /videocall/signal/{token}    → envia sinal WebRTC (offer/answer/ice/...)
 *   [público] GET  /videocall/poll/{token}      → long-poll: peers ativos + sinais pendentes
 *   [público] POST /videocall/leave/{token}     → sai da sala
 *   [público] POST /videocall/upload/{token}    → envia a gravação (MediaRecorder) ao servidor
 *   [público] GET  /videocall/recording/{recToken} → baixa/reproduz a gravação salva
 *   [logado]  GET  /videocall/recordings/{token}   → lista gravações de uma sala (JSON)
 *
 * Signaling por HTTP long-polling em MySQL (mesmo padrão descrito na
 * especificação de origem). Topologia mesh: cada participante abre uma
 * RTCPeerConnection com cada outro. Recomendado até ~6 participantes.
 */
class VideocallController extends Controller
{
    /** Papéis que podem CRIAR salas (qualquer usuário interno da equipe). */
    private $creatorRoles = ['super_admin', 'attendant', 'developer', 'analyst', 'comercial', 'marketing', 'whatsapp_agent'];

    private $model;

    public function __construct()
    {
        $this->model = new VideoRoom();
    }

    // ============================================================
    // Criação de sala (requer login)
    // ============================================================

    /** Cria uma sala e devolve o link público. */
    public function create()
    {
        $this->requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);

        $user = $this->currentUser();
        if (!in_array($user['role'], $this->creatorRoles, true)) {
            $this->json(['error' => 'Você não tem permissão para criar salas de vídeo.'], 403);
        }

        $title = trim($_POST['title'] ?? '') ?: 'Videochamada';
        // Sem informar limite, usa o teto máximo (só uma proteção técnica; o usuário
        // não precisa decidir quantas pessoas vão entrar).
        $max = (int)($_POST['max_participants'] ?? 15);
        if ($max < 2) $max = 2;
        if ($max > 15) $max = 15;

        $meetingId = !empty($_POST['meeting_id']) ? (int)$_POST['meeting_id'] : null;
        $expiryDays = 30;

        // Pública: qualquer pessoa com o link entra direto.
        // Privada: a entrada precisa ser aprovada por um administrador da sala.
        $visibility = (($_POST['visibility'] ?? 'public') === 'private') ? 'private' : 'public';

        // Permitir apresentar (compartilhar tela): padrão sim. O admin pode mudar depois.
        $allowPresentation = (($_POST['allow_presentation'] ?? '1') === '0') ? 0 : 1;

        $token = $this->model->create([
            'title' => $title,
            'created_by' => $user['id'],
            'company_id' => $this->activeCompanyId(),
            'meeting_id' => $meetingId,
            'max_participants' => $max,
            'allow_recording' => 1,
            'allow_presentation' => $allowPresentation,
            'status' => 'active',
            'visibility' => $visibility,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+' . $expiryDays . ' days')),
        ]);

        $room = $this->model->findByToken($token);

        // Administradores da sala (só faz sentido em privada). O criador é sempre admin.
        $adminIds = array_filter(array_map('intval', (array)($_POST['admins'] ?? [])));
        $adminIds[] = (int)$user['id'];
        if ($visibility === 'private') {
            $this->model->setAdmins($room['id'], $adminIds);
        }

        $this->json(['success' => true, 'token' => $token, 'url' => $this->publicUrl($token), 'visibility' => $visibility]);
    }

    /** URL pública da sala (respeita app_public_url se configurado). */
    private function publicUrl($token)
    {
        $base = rtrim((string) Config::get('app_public_url'), '/') ?: rtrim(baseUrl(''), '/');
        return $base . '/videocall/room/' . $token;
    }

    // ============================================================
    // Sala pública (SEM login) — a pessoa entra pelo link
    // ============================================================

    /** Página pública da chamada. Aceita /videocall/room/{token}. */
    public function room($token = null)
    {
        $token = $this->tokenFromUrl($token, 2);
        $room = $token ? $this->model->findByToken($token) : null;

        if (!$room) {
            $this->renderMessage('Link inválido', 'Esta sala de vídeo não existe ou o link está incorreto.');
            return;
        }
        if ($room['status'] === 'ended') {
            $this->renderMessage('Chamada encerrada', 'Esta chamada foi encerrada pelo organizador.');
            return;
        }
        if (!empty($room['expires_at']) && strtotime($room['expires_at']) < time()) {
            $this->renderMessage('Link expirado', 'Este link de chamada expirou.');
            return;
        }

        // Nome sugerido: se logado, usa o nome da sessão.
        $suggestedName = $_SESSION['user_name'] ?? '';
        $loggedUserId = $_SESSION['user_id'] ?? null;
        $isAdmin = $this->model->isAdminUser($room, $loggedUserId);

        $this->view('videocall/room', [
            'room' => $room,
            'suggestedName' => $suggestedName,
            'loggedUserId' => $loggedUserId,
            'isAdmin' => $isAdmin,
            'allowPresentation' => (int)($room['allow_presentation'] ?? 1) === 1,
            'backgrounds' => $this->backgroundList(),
            'iceServers' => $this->iceServers(),
        ]);
    }

    /** Servidores ICE (STUN/TURN) — TURN opcional via Settings. */
    private function iceServers()
    {
        $servers = [
            ['urls' => 'stun:stun.l.google.com:19302'],
            ['urls' => 'stun:stun1.l.google.com:19302'],
        ];
        $turnUrl = trim((string) Config::get('turn_server_url'));
        if ($turnUrl !== '') {
            $entry = ['urls' => $turnUrl];
            $turnUser = trim((string) Config::get('turn_server_username'));
            $turnCred = trim((string) Config::get('turn_server_credential'));
            if ($turnUser !== '') $entry['username'] = $turnUser;
            if ($turnCred !== '') $entry['credential'] = $turnCred;
            $servers[] = $entry;
        }
        return $servers;
    }

    /** Lista as imagens de fundo padrão disponíveis (public/assets/vc-backgrounds). */
    private function backgroundList()
    {
        $dir = PUBLIC_PATH . '/assets/vc-backgrounds';
        $out = [];
        if (is_dir($dir)) {
            foreach (scandir($dir) as $f) {
                if (preg_match('/\.(jpe?g|png|webp)$/i', $f)) {
                    $label = ucfirst(preg_replace('/[-_]+/', ' ', pathinfo($f, PATHINFO_FILENAME)));
                    $out[] = [
                        'id' => $f,
                        'label' => $label,
                        'url' => rtrim(baseUrl(''), '/') . '/assets/vc-backgrounds/' . rawurlencode($f),
                    ];
                }
            }
        }
        return $out;
    }

    /** Registra a presença do peer e devolve os peers já ativos. */
    public function join($token = null)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);
        $room = $this->requireActiveRoom($token, 2);

        $peerId = $this->safePeerId($_POST['peer_id'] ?? '');
        if ($peerId === '') $this->json(['error' => 'peer_id ausente'], 400);

        $name = trim(substr((string)($_POST['name'] ?? ''), 0, 120)) ?: 'Convidado';
        $browserId = $this->safePeerId($_POST['browser_id'] ?? '');
        $takeover = ((string)($_POST['takeover'] ?? '') === '1');

        // Impede sessão DUPLICADA no MESMO navegador (ex.: abrir outra guia).
        // Dispositivos diferentes têm browser_id distinto, então continuam livres.
        if ($browserId !== '') {
            $dupes = $this->model->activeByBrowser($room['id'], $browserId, $peerId);
            if (!empty($dupes)) {
                if (!$takeover) {
                    // Frontend vai perguntar se deseja mover a chamada para esta guia.
                    $this->json(['duplicate' => true, 'message' => 'Você já está nesta chamada em outra guia deste navegador.']);
                }
                // Takeover: derruba as sessões antigas deste mesmo navegador.
                foreach ($dupes as $d) {
                    $this->model->pushSignal($room['id'], $peerId, $d['peer_id'], 'kick', ['reason' => 'takeover']);
                    $this->model->leavePresence($room['id'], $d['peer_id']);
                    $this->model->pushSignal($room['id'], $d['peer_id'], null, 'leave', null);
                }
            }
        }

        $userId = $_SESSION['user_id'] ?? null;
        $isAdmin = $this->model->isAdminUser($room, $userId);
        $isHost = ($userId && (int)$userId === (int)$room['created_by']);

        // Sala PRIVADA: quem não é admin precisa de aprovação. Se ainda não foi
        // admitido, registra o pedido e responde "aguardando" (não entra na sala).
        $visibility = $room['visibility'] ?? 'public';
        if ($visibility === 'private' && !$isAdmin) {
            $status = $this->model->getRequestStatus($room['id'], $peerId);
            if ($status !== 'admitted') {
                $this->model->requestJoin($room['id'], $peerId, $name, $userId);
                // Avisa os admins presentes que há um novo pedido.
                $this->model->pushSignal($room['id'], $peerId, null, 'request', ['name' => $name]);
                $this->json(['awaiting' => true, 'message' => 'Aguardando aprovação de um administrador da sala.']);
            }
        }

        // Teto de participantes (não conta o próprio peer se já estava presente).
        $existing = $this->model->activeParticipants($room['id'], $peerId);
        if (count($existing) >= (int)$room['max_participants']) {
            $this->json(['error' => 'A sala atingiu o limite de participantes.'], 409);
        }

        $this->model->joinPresence($room['id'], $peerId, $name, $userId, $isHost ? 'host' : 'participant', $browserId ?: null);

        // Avisa a sala que alguém entrou (broadcast).
        $this->model->pushSignal($room['id'], $peerId, null, 'join', ['name' => $name]);

        $this->json([
            'success' => true,
            'self' => ['peer_id' => $peerId, 'name' => $name, 'is_host' => $isHost, 'is_admin' => $isAdmin],
            'peers' => $this->formatPeers($existing),
            'allow_recording' => (int)$room['allow_recording'] === 1,
            'visibility' => $visibility,
            'is_admin' => $isAdmin,
        ]);
    }

    /**
     * Long-poll (até ~20s): entrega sinais pendentes ao peer e a lista de peers ativos.
     * Também funciona como heartbeat de presença.
     */
    public function poll($token = null)
    {
        $room = $this->requireActiveRoom($token, 2, true);
        $peerId = $this->safePeerId($_GET['peer_id'] ?? '');
        if ($peerId === '') $this->json(['error' => 'peer_id ausente'], 400);

        // Long-poll: libera o lock da sessão para não travar as outras chamadas.
        $this->releaseSession();

        $this->model->heartbeat($room['id'], $peerId);

        // Higiene esporádica da fila de sinais.
        if (mt_rand(1, 20) === 1) {
            $this->model->purgeOldSignals(30);
        }

        $deadline = time() + 12;
        $signals = [];
        do {
            $signals = $this->model->pullSignals($room['id'], $peerId);
            if (!empty($signals)) break;
            usleep(300000); // 0,3s
        } while (time() < $deadline);

        $out = array_map(function ($s) {
            return [
                'from' => $s['from_peer_id'],
                'to' => $s['to_peer_id'],
                'kind' => $s['kind'],
                'payload' => json_decode($s['payload_json'] ?? 'null', true),
            ];
        }, $signals);

        $peers = $this->formatPeers($this->model->activeParticipants($room['id'], $peerId));

        $this->json(['signals' => $out, 'peers' => $peers]);
    }

    /** Envia um sinal WebRTC. */
    public function signal($token = null)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);
        $room = $this->requireActiveRoom($token, 2, true);

        // 'end' precisa checar admin (usa a sessão); os demais sinais não usam.
        $endUserId = $_SESSION['user_id'] ?? null;
        $this->releaseSession();

        $raw = file_get_contents('php://input');
        $body = json_decode($raw, true);
        if (!is_array($body)) $body = $_POST;

        $from = $this->safePeerId($body['from'] ?? '');
        $to = $this->safePeerId($body['to'] ?? '');
        $kind = (string)($body['kind'] ?? '');
        $payload = $body['payload'] ?? null;

        $allowed = ['offer', 'answer', 'ice', 'join', 'leave', 'media', 'screen', 'end', 'reaction', 'hand'];
        if ($from === '' || !in_array($kind, $allowed, true)) {
            $this->json(['error' => 'Sinal inválido'], 400);
        }
        // 'end' encerra a sala para todos — só admin da sala pode.
        if ($kind === 'end') {
            if (!$this->model->isAdminUser($room, $endUserId)) $this->json(['error' => 'Sem permissão.'], 403);
        }

        $this->model->heartbeat($room['id'], $from);
        $this->model->pushSignal($room['id'], $from, $to, $kind, $payload);
        $this->json(['success' => true]);
    }

    /** Sai da sala. */
    public function leave($token = null)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);
        $room = $this->requireActiveRoom($token, 2, true);

        $peerId = $this->safePeerId($_POST['peer_id'] ?? '');
        if ($peerId !== '') {
            $this->model->leavePresence($room['id'], $peerId);
            $this->model->pushSignal($room['id'], $peerId, null, 'leave', null);
        }
        $this->json(['success' => true]);
    }

    // ============================================================
    // Moderação (sala privada)
    // ============================================================

    /** Consulta o status do próprio pedido de entrada (para a tela de espera). */
    public function requestStatus($token = null)
    {
        $room = $this->requireActiveRoom($token, 2, true);
        $this->releaseSession();
        $peerId = $this->safePeerId($_GET['peer_id'] ?? '');
        if ($peerId === '') $this->json(['error' => 'peer_id ausente'], 400);
        // Mantém o pedido "vivo" enquanto a pessoa espera.
        $status = $this->model->getRequestStatus($room['id'], $peerId) ?: 'pending';
        $this->json(['status' => $status]);
    }

    /**
     * Painel do admin: lista participantes ativos + fila de pedidos pendentes.
     * Só um admin da sala (ou o criador) pode acessar.
     */
    public function roster($token = null)
    {
        $room = $this->requireActiveRoom($token, 2, true);
        $userId = $_SESSION['user_id'] ?? null;
        $this->releaseSession();
        if (!$this->model->isAdminUser($room, $userId)) $this->json(['error' => 'Sem permissão.'], 403);

        $participants = array_map(function ($p) {
            return ['peer_id' => $p['peer_id'], 'name' => $p['display_name'] ?: 'Convidado', 'role' => $p['role']];
        }, $this->model->activeParticipants($room['id']));

        $requests = array_map(function ($r) {
            return ['peer_id' => $r['peer_id'], 'name' => $r['display_name'] ?: 'Convidado', 'requested_at' => $r['requested_at']];
        }, $this->model->pendingRequests($room['id']));

        $this->json(['participants' => $participants, 'requests' => $requests]);
    }

    /** Admin autoriza a entrada de um peer que aguardava. */
    public function admit($token = null)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);
        $room = $this->requireActiveRoom($token, 2, true);
        $userId = $_SESSION['user_id'] ?? null;
        $this->releaseSession();
        if (!$this->model->isAdminUser($room, $userId)) $this->json(['error' => 'Sem permissão.'], 403);

        $target = $this->safePeerId($_POST['peer_id'] ?? '');
        if ($target === '') $this->json(['error' => 'peer_id ausente'], 400);

        $this->model->decideRequest($room['id'], $target, 'admitted', $userId);
        // Avisa o solicitante que foi liberado (ele então faz o join de verdade).
        $this->model->pushSignal($room['id'], 'admin', $target, 'admit', null);
        $this->json(['success' => true]);
    }

    /** Admin recusa a entrada de um peer. */
    public function deny($token = null)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);
        $room = $this->requireActiveRoom($token, 2, true);
        $userId = $_SESSION['user_id'] ?? null;
        $this->releaseSession();
        if (!$this->model->isAdminUser($room, $userId)) $this->json(['error' => 'Sem permissão.'], 403);

        $target = $this->safePeerId($_POST['peer_id'] ?? '');
        if ($target === '') $this->json(['error' => 'peer_id ausente'], 400);

        $this->model->decideRequest($room['id'], $target, 'denied', $userId);
        $this->model->pushSignal($room['id'], 'admin', $target, 'deny', null);
        $this->json(['success' => true]);
    }

    /** Admin liga/desliga a permissão de apresentar (compartilhar tela). */
    public function setPresentation($token = null)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);
        $room = $this->requireActiveRoom($token, 2, true);
        $userId = $_SESSION['user_id'] ?? null;
        $this->releaseSession();
        if (!$this->model->isAdminUser($room, $userId)) $this->json(['error' => 'Sem permissão.'], 403);

        $allow = (($_POST['allow'] ?? '1') === '1') ? 1 : 0;
        $this->model->update($room['id'], ['allow_presentation' => $allow]);
        // Propaga em tempo real para todos.
        $this->model->pushSignal($room['id'], 'admin', null, 'perm', ['allow_presentation' => $allow]);
        $this->json(['success' => true, 'allow_presentation' => $allow]);
    }

    // ============================================================
    // Gravação
    // ============================================================

    /**
     * Recebe o arquivo de gravação (MediaRecorder no navegador) e salva no servidor.
     * A sala precisa permitir gravação (allow_recording).
     */
    public function upload($token = null)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);
        $room = $this->requireActiveRoom($token, 2, true);
        $userId = $_SESSION['user_id'] ?? null;

        if ((int)$room['allow_recording'] !== 1) {
            $this->json(['error' => 'Gravação não permitida nesta sala.'], 403);
        }
        // Sala privada: só administradores podem gravar.
        if (($room['visibility'] ?? 'public') === 'private' && !$this->model->isAdminUser($room, $userId)) {
            $this->json(['error' => 'Apenas administradores podem gravar nesta sala.'], 403);
        }
        if (empty($_FILES['recording']) || $_FILES['recording']['error'] !== UPLOAD_ERR_OK) {
            $this->json(['error' => 'Arquivo de gravação ausente ou inválido.'], 400);
        }

        $file = $_FILES['recording'];
        // Só aceita vídeo (webm/mp4). Valida por MIME informado + extensão de saída segura.
        $mime = (string)($file['type'] ?? '');
        $ext = 'webm';
        if (strpos($mime, 'mp4') !== false) $ext = 'mp4';
        elseif (strpos($mime, 'webm') === false) {
            // MIME desconhecido: tenta detectar; se não for vídeo, recusa.
            if (function_exists('finfo_open')) {
                $fi = finfo_open(FILEINFO_MIME_TYPE);
                $detected = finfo_file($fi, $file['tmp_name']);
                finfo_close($fi);
                if (strpos((string)$detected, 'video/') !== 0) {
                    $this->json(['error' => 'O arquivo enviado não é um vídeo.'], 415);
                }
                $mime = $detected;
                if (strpos($detected, 'mp4') !== false) $ext = 'mp4';
            }
        }

        // Limite de tamanho (500 MB) para não estourar o disco.
        if ((int)$file['size'] > 500 * 1024 * 1024) {
            $this->json(['error' => 'Gravação muito grande (máx. 500 MB).'], 413);
        }

        $dir = PUBLIC_PATH . '/uploads/recordings';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);

        $recToken = $this->model->generateToken();
        $fileName = 'rec_' . $room['id'] . '_' . $recToken . '.' . $ext;
        $dest = $dir . '/' . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            $this->json(['error' => 'Falha ao salvar a gravação no servidor.'], 500);
        }

        $userId = $_SESSION['user_id'] ?? null;
        $recordedName = trim(substr((string)($_POST['recorded_by_name'] ?? ($_SESSION['user_name'] ?? '')), 0, 120)) ?: null;
        $duration = (int)($_POST['duration_sec'] ?? 0) ?: null;

        $this->model->addRecording([
            'room_id' => $room['id'],
            'token' => $recToken,
            'file_path' => 'recordings/' . $fileName,
            'file_size' => filesize($dest) ?: null,
            'mime_type' => $mime ?: ('video/' . $ext),
            'duration_sec' => $duration,
            'recorded_by' => $userId,
            'recorded_by_name' => $recordedName,
        ]);

        $downloadUrl = rtrim(baseUrl(''), '/') . '/videocall/recording/' . $recToken;
        $this->json(['success' => true, 'token' => $recToken, 'url' => $downloadUrl]);
    }

    /** Reproduz/baixa uma gravação salva (streaming simples com suporte a Range). */
    public function recording($recToken = null)
    {
        $recToken = $this->tokenFromUrl($recToken, 2);
        $rec = $recToken ? $this->model->findRecordingByToken($recToken) : null;
        if (!$rec) {
            http_response_code(404);
            echo 'Gravação não encontrada.';
            return;
        }

        $path = PUBLIC_PATH . '/uploads/' . ltrim($rec['file_path'], '/');
        $real = realpath($path);
        $baseReal = realpath(PUBLIC_PATH . '/uploads/recordings');
        // Proteção contra path traversal: o arquivo tem que estar dentro de recordings/.
        if (!$real || !$baseReal || strpos($real, $baseReal) !== 0 || !is_file($real)) {
            http_response_code(404);
            echo 'Arquivo de gravação indisponível.';
            return;
        }

        $this->streamFile($real, $rec['mime_type'] ?: 'video/webm');
    }

    /** Lista as gravações de uma sala (JSON) — requer login. */
    public function recordings($token = null)
    {
        $this->requireLogin();
        $token = $this->tokenFromUrl($token, 2);
        $room = $token ? $this->model->findByToken($token) : null;
        if (!$room) $this->json(['error' => 'Sala não encontrada'], 404);

        $list = array_map(function ($r) {
            return [
                'token' => $r['token'],
                'url' => rtrim(baseUrl(''), '/') . '/videocall/recording/' . $r['token'],
                'size' => (int)$r['file_size'],
                'duration_sec' => (int)$r['duration_sec'],
                'recorded_by_name' => $r['recorded_by_name'],
                'created_at' => $r['created_at'],
            ];
        }, $this->model->listRecordings($room['id']));

        $this->json(['recordings' => $list]);
    }

    // ============================================================
    // Helpers
    // ============================================================

    /** Extrai o token da URL quando o roteador não passa como argumento. */
    private function tokenFromUrl($token, $index)
    {
        if ($token) return trim((string)$token);
        $parts = explode('/', trim((string)($_GET['url'] ?? ''), '/'));
        return isset($parts[$index]) ? trim($parts[$index]) : null;
    }

    /** Valida e devolve a sala ativa, ou responde erro (JSON) e encerra. */
    private function requireActiveRoom($token, $index, $asJson = true)
    {
        $token = $this->tokenFromUrl($token, $index);
        $room = $token ? $this->model->findByToken($token) : null;
        if (!$room) $this->json(['error' => 'Sala não encontrada'], 404);
        if ($room['status'] === 'ended') $this->json(['error' => 'Chamada encerrada'], 410);
        if (!empty($room['expires_at']) && strtotime($room['expires_at']) < time()) {
            $this->json(['error' => 'Link expirado'], 410);
        }
        return $room;
    }

    /**
     * Libera o lock do arquivo de sessão do PHP.
     *
     * O long-polling segura a requisição por ~20s. Enquanto a sessão está
     * "aberta", o PHP bloqueia TODAS as outras requisições do mesmo usuário
     * (session lock), o que fazia admit/roster/signal demorarem muito. Como
     * estas ações já leram o que precisavam de $_SESSION e não escrevem mais
     * nela, fechamos a sessão para não travar as demais chamadas em paralelo.
     */
    private function releaseSession()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    /** Sanitiza o peer_id (apenas hex/alfanumérico, até 64 chars). */
    private function safePeerId($v)
    {
        $v = trim((string)$v);
        if ($v === '') return '';
        if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $v)) return '';
        return $v;
    }

    private function formatPeers($rows)
    {
        return array_map(function ($p) {
            return [
                'peer_id' => $p['peer_id'],
                'name' => $p['display_name'] ?: 'Convidado',
                'role' => $p['role'],
            ];
        }, $rows);
    }

    /** Streaming de arquivo com suporte a HTTP Range (seek no player). */
    private function streamFile($path, $mime)
    {
        $size = filesize($path);
        $start = 0;
        $end = $size - 1;

        header('Content-Type: ' . $mime);
        header('Accept-Ranges: bytes');

        if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
            if ($m[1] !== '') $start = (int)$m[1];
            if ($m[2] !== '') $end = (int)$m[2];
            if ($start > $end || $start >= $size) {
                header('HTTP/1.1 416 Range Not Satisfiable');
                header("Content-Range: bytes */$size");
                exit;
            }
            header('HTTP/1.1 206 Partial Content');
            header("Content-Range: bytes $start-$end/$size");
        }

        header('Content-Length: ' . ($end - $start + 1));

        $fp = fopen($path, 'rb');
        fseek($fp, $start);
        $remaining = $end - $start + 1;
        while ($remaining > 0 && !feof($fp)) {
            $chunk = ($remaining > 8192) ? 8192 : $remaining;
            echo fread($fp, $chunk);
            flush();
            $remaining -= $chunk;
        }
        fclose($fp);
        exit;
    }

    /** Página simples de mensagem (link inválido/expirado/encerrado). */
    private function renderMessage($title, $message)
    {
        $t = htmlspecialchars($title, ENT_QUOTES);
        $m = htmlspecialchars($message, ENT_QUOTES);
        http_response_code(200);
        echo <<<HTML
<!DOCTYPE html>
<html lang="pt-BR"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$t} · ON Solutions Brasil</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>body{background:#0f1020;color:#e8eaf1;font-family:'Segoe UI',system-ui,Arial,sans-serif;}
.box{max-width:460px;margin:12vh auto;background:#1a1c2e;border-radius:18px;padding:36px 30px;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.4);}
.icon{font-size:3rem;color:#00BFA6;}</style></head>
<body><div class="box"><div class="icon"><i class="bi bi-camera-video-off"></i></div>
<h4 class="mt-3">{$t}</h4><p class="text-secondary">{$m}</p></div></body></html>
HTML;
        exit;
    }
}
