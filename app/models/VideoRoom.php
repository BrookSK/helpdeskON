<?php

/**
 * Sala de videochamada em grupo (WebRTC mesh nativo, sem API externa).
 *
 * Uma sala tem um token público (link para entrar sem login). A sinalização
 * WebRTC roda por HTTP long-polling persistido em MySQL, e a presença de cada
 * participante é mantida via heartbeat (last_seen_at).
 */
class VideoRoom
{
    private $db;

    /** Segundos sem heartbeat até considerar o participante "saiu". */
    const PRESENCE_TIMEOUT = 10;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ================= Salas =================

    /** Gera um token único para a sala/gravação. */
    public function generateToken()
    {
        return bin2hex(random_bytes(16));
    }

    public function create($data)
    {
        if (empty($data['token'])) $data['token'] = $this->generateToken();
        if (empty($data['created_at'])) $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert('video_rooms', $data);
        return $data['token'];
    }

    public function findById($id)
    {
        return $this->db->fetch("SELECT * FROM video_rooms WHERE id = ? LIMIT 1", [$id]);
    }

    public function findByToken($token)
    {
        $token = trim((string)$token);
        if ($token === '') return null;
        return $this->db->fetch("SELECT * FROM video_rooms WHERE token = ? LIMIT 1", [$token]);
    }

    public function update($id, $data)
    {
        return $this->db->update('video_rooms', $data, 'id = ?', [$id]);
    }

    public function end($id)
    {
        return $this->db->update('video_rooms', ['status' => 'ended', 'ended_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
    }

    /** Salas criadas por um usuário (mais recentes primeiro). */
    public function listByUser($userId, $limit = 50)
    {
        return $this->db->fetchAll(
            "SELECT * FROM video_rooms WHERE created_by = ? ORDER BY id DESC LIMIT " . (int)$limit,
            [$userId]
        );
    }

    // ================= Presença =================

    /**
     * Registra/atualiza a presença de um peer na sala (upsert por room+peer).
     */
    public function joinPresence($roomId, $peerId, $displayName = null, $userId = null, $role = 'participant', $browserId = null)
    {
        $now = date('Y-m-d H:i:s');
        $existing = $this->db->fetch(
            "SELECT id FROM video_room_participants WHERE room_id = ? AND peer_id = ? LIMIT 1",
            [$roomId, $peerId]
        );
        if ($existing) {
            $this->db->update('video_room_participants', [
                'display_name' => $displayName,
                'user_id' => $userId,
                'browser_id' => $browserId,
                'last_seen_at' => $now,
                'left_at' => null,
            ], 'id = ?', [$existing['id']]);
            return (int)$existing['id'];
        }
        return $this->db->insert('video_room_participants', [
            'room_id' => $roomId,
            'peer_id' => $peerId,
            'browser_id' => $browserId,
            'user_id' => $userId,
            'display_name' => $displayName,
            'role' => in_array($role, ['host', 'participant']) ? $role : 'participant',
            'joined_at' => $now,
            'last_seen_at' => $now,
        ]);
    }

    /**
     * Sessões ATIVAS do mesmo navegador (browser_id) nesta sala, exceto o peer atual.
     * Usado para impedir sessão duplicada no mesmo navegador (nova guia).
     */
    public function activeByBrowser($roomId, $browserId, $excludePeerId = null)
    {
        if (empty($browserId)) return [];
        $cutoff = date('Y-m-d H:i:s', time() - self::PRESENCE_TIMEOUT);
        $sql = "SELECT peer_id FROM video_room_participants
                WHERE room_id = ? AND browser_id = ? AND left_at IS NULL AND last_seen_at >= ?";
        $params = [$roomId, $browserId, $cutoff];
        if ($excludePeerId !== null) { $sql .= " AND peer_id <> ?"; $params[] = $excludePeerId; }
        return $this->db->fetchAll($sql, $params);
    }

    /** Atualiza o heartbeat de um peer (mantém presença viva). */
    public function heartbeat($roomId, $peerId)
    {
        return $this->db->update(
            'video_room_participants',
            ['last_seen_at' => date('Y-m-d H:i:s'), 'left_at' => null],
            'room_id = ? AND peer_id = ?',
            [$roomId, $peerId]
        );
    }

    public function leavePresence($roomId, $peerId)
    {
        return $this->db->update(
            'video_room_participants',
            ['left_at' => date('Y-m-d H:i:s')],
            'room_id = ? AND peer_id = ?',
            [$roomId, $peerId]
        );
    }

    /** Participantes considerados ativos (heartbeat recente e sem left_at). */
    public function activeParticipants($roomId, $excludePeerId = null)
    {
        $cutoff = date('Y-m-d H:i:s', time() - self::PRESENCE_TIMEOUT);
        $sql = "SELECT p.peer_id, p.display_name, p.user_id, p.role, p.joined_at, u.avatar
                FROM video_room_participants p
                LEFT JOIN users u ON p.user_id = u.id
                WHERE p.room_id = ? AND p.left_at IS NULL AND p.last_seen_at >= ?";
        $params = [$roomId, $cutoff];
        if ($excludePeerId !== null) {
            $sql .= " AND p.peer_id <> ?";
            $params[] = $excludePeerId;
        }
        $sql .= " ORDER BY p.joined_at ASC";
        return $this->db->fetchAll($sql, $params);
    }

    public function activeCount($roomId)
    {
        $cutoff = date('Y-m-d H:i:s', time() - self::PRESENCE_TIMEOUT);
        $r = $this->db->fetch(
            "SELECT COUNT(*) c FROM video_room_participants WHERE room_id = ? AND left_at IS NULL AND last_seen_at >= ?",
            [$roomId, $cutoff]
        );
        return (int)($r['c'] ?? 0);
    }

    // ================= Sinalização (signaling) =================

    /** Enfileira um sinal WebRTC para um peer específico ou broadcast (to_peer_id NULL). */
    public function pushSignal($roomId, $fromPeerId, $toPeerId, $kind, $payload)
    {
        return $this->db->insert('video_room_signals', [
            'room_id' => $roomId,
            'from_peer_id' => $fromPeerId,
            'to_peer_id' => ($toPeerId === '' ? null : $toPeerId),
            'kind' => $kind,
            'payload_json' => is_string($payload) ? $payload : json_encode($payload, JSON_UNESCAPED_UNICODE),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Busca sinais pendentes destinados a um peer (diretos + broadcasts de outros)
     * e os marca como entregues. Ignora broadcasts enviados pelo próprio peer.
     */
    public function pullSignals($roomId, $peerId)
    {
        // Sinais EFÊMEROS (reação, mão) só valem em tempo real: se não foram
        // entregues em poucos segundos, viraram lixo e NÃO devem ser reproduzidos
        // — senão quem acaba de entrar na sala recebe uma enxurrada de reações
        // antigas ainda pendentes. Os demais tipos (offer/answer/ice/join/leave/
        // screen/end/state/perm/rec/forcemute) continuam sendo entregues sempre.
        $ephemeralCutoff = date('Y-m-d H:i:s', time() - 8);
        $rows = $this->db->fetchAll(
            "SELECT * FROM video_room_signals
             WHERE room_id = ? AND delivered_at IS NULL
               AND (to_peer_id = ? OR (to_peer_id IS NULL AND from_peer_id <> ?))
               AND (kind NOT IN ('reaction','hand') OR created_at >= ?)
             ORDER BY id ASC LIMIT 100",
            [$roomId, $peerId, $peerId, $ephemeralCutoff]
        );
        if ($rows) {
            $ids = array_column($rows, 'id');
            $in = implode(',', array_fill(0, count($ids), '?'));
            $this->db->query(
                "UPDATE video_room_signals SET delivered_at = ? WHERE id IN ($in)",
                array_merge([date('Y-m-d H:i:s')], $ids)
            );
        }
        return $rows;
    }

    /** Limpeza de sinais antigos já entregues (higiene da tabela). */
    public function purgeOldSignals($minutes = 30)
    {
        $cutoff = date('Y-m-d H:i:s', time() - ($minutes * 60));
        return $this->db->delete('video_room_signals', 'created_at < ?', [$cutoff]);
    }

    // ================= Gravações =================

    public function addRecording($data)
    {
        if (empty($data['token'])) $data['token'] = $this->generateToken();
        if (empty($data['created_at'])) $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert('video_recordings', $data);
        return $data['token'];
    }

    public function findRecordingByToken($token)
    {
        return $this->db->fetch("SELECT * FROM video_recordings WHERE token = ? LIMIT 1", [$token]);
    }

    public function updateRecording($token, $data)
    {
        return $this->db->update('video_recordings', $data, 'token = ?', [$token]);
    }

    public function deleteRecording($token)
    {
        return $this->db->delete('video_recordings', 'token = ?', [$token]);
    }

    public function listRecordings($roomId)
    {
        return $this->db->fetchAll(
            "SELECT * FROM video_recordings WHERE room_id = ? ORDER BY id DESC",
            [$roomId]
        );
    }

    public function listRecordingsByUser($userId, $limit = 100)
    {
        return $this->db->fetchAll(
            "SELECT r.*, rm.title AS room_title
             FROM video_recordings r
             JOIN video_rooms rm ON r.room_id = rm.id
             WHERE rm.created_by = ?
             ORDER BY r.id DESC LIMIT " . (int)$limit,
            [$userId]
        );
    }

    /**
     * Gravações visíveis para um usuário:
     * - Sala PÚBLICA: qualquer usuário logado da equipe vê.
     * - Sala PRIVADA: apenas quem gravou (recorded_by) ou o criador da sala.
     * super_admin vê todas.
     */
    public function listRecordingsVisibleTo($userId, $role = null, $limit = 200)
    {
        if ($role === 'super_admin') {
            return $this->db->fetchAll(
                "SELECT r.*, rm.title AS room_title, rm.visibility, rm.created_by AS room_owner, u.name AS recorder_name
                 FROM video_recordings r
                 JOIN video_rooms rm ON r.room_id = rm.id
                 LEFT JOIN users u ON r.recorded_by = u.id
                 ORDER BY r.id DESC LIMIT " . (int)$limit
            );
        }
        return $this->db->fetchAll(
            "SELECT r.*, rm.title AS room_title, rm.visibility, rm.created_by AS room_owner, u.name AS recorder_name
             FROM video_recordings r
             JOIN video_rooms rm ON r.room_id = rm.id
             LEFT JOIN users u ON r.recorded_by = u.id
             WHERE rm.visibility = 'public'
                OR r.recorded_by = ?
                OR rm.created_by = ?
             ORDER BY r.id DESC LIMIT " . (int)$limit,
            [$userId, $userId]
        );
    }

    /** Um usuário pode ver esta gravação específica? */
    public function canUserSeeRecording($rec, $userId, $role = null)
    {
        if ($role === 'super_admin') return true;
        $room = $this->findById($rec['room_id']);
        if (!$room) return false;
        if (($room['visibility'] ?? 'public') === 'public') return true;
        return ((int)$rec['recorded_by'] === (int)$userId) || ((int)$room['created_by'] === (int)$userId);
    }

    // ================= Administradores da sala =================

    public function setAdmins($roomId, array $userIds)
    {
        $this->db->delete('video_room_admins', 'room_id = ?', [$roomId]);
        foreach (array_unique(array_filter(array_map('intval', $userIds))) as $uid) {
            try {
                $this->db->insert('video_room_admins', ['room_id' => $roomId, 'user_id' => $uid]);
            } catch (\Throwable $e) { /* ignora duplicado */ }
        }
    }

    public function getAdminIds($roomId)
    {
        $rows = $this->db->fetchAll("SELECT user_id FROM video_room_admins WHERE room_id = ?", [$roomId]);
        return array_map('intval', array_column($rows, 'user_id'));
    }

    /** O usuário é moderador da sala? (criador ou admin explícito). */
    public function isAdminUser($room, $userId)
    {
        if (!$userId) return false;
        if ((int)$room['created_by'] === (int)$userId) return true;
        return in_array((int)$userId, $this->getAdminIds($room['id']), true);
    }

    // ================= Pedidos de entrada (sala privada) =================

    /** Cria/atualiza um pedido de entrada como 'pending'. */
    public function requestJoin($roomId, $peerId, $displayName = null, $userId = null)
    {
        $now = date('Y-m-d H:i:s');
        $existing = $this->db->fetch(
            "SELECT id FROM video_room_join_requests WHERE room_id = ? AND peer_id = ? LIMIT 1",
            [$roomId, $peerId]
        );
        if ($existing) {
            $this->db->update('video_room_join_requests', [
                'display_name' => $displayName,
                'user_id' => $userId,
                'status' => 'pending',
                'requested_at' => $now,
                'decided_at' => null,
                'decided_by' => null,
            ], 'id = ?', [$existing['id']]);
            return (int)$existing['id'];
        }
        return $this->db->insert('video_room_join_requests', [
            'room_id' => $roomId,
            'peer_id' => $peerId,
            'display_name' => $displayName,
            'user_id' => $userId,
            'status' => 'pending',
            'requested_at' => $now,
        ]);
    }

    public function getRequestStatus($roomId, $peerId)
    {
        $r = $this->db->fetch(
            "SELECT status FROM video_room_join_requests WHERE room_id = ? AND peer_id = ? LIMIT 1",
            [$roomId, $peerId]
        );
        return $r['status'] ?? null;
    }

    public function pendingRequests($roomId)
    {
        return $this->db->fetchAll(
            "SELECT peer_id, display_name, user_id, requested_at
             FROM video_room_join_requests
             WHERE room_id = ? AND status = 'pending'
             ORDER BY requested_at ASC",
            [$roomId]
        );
    }

    public function decideRequest($roomId, $peerId, $status, $decidedBy = null)
    {
        if (!in_array($status, ['admitted', 'denied'], true)) return 0;
        return $this->db->update('video_room_join_requests', [
            'status' => $status,
            'decided_at' => date('Y-m-d H:i:s'),
            'decided_by' => $decidedBy,
        ], 'room_id = ? AND peer_id = ?', [$roomId, $peerId]);
    }
}
