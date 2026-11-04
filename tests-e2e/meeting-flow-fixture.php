<?php
/**
 * Dados do E2E do fluxo comercial (reunião até proposta) no banco LOCAL.
 * Cada comando imprime JSON no stdout.
 *
 *   php tests-e2e/meeting-flow-fixture.php user
 *   php tests-e2e/meeting-flow-fixture.php recordings <userId>
 *   php tests-e2e/meeting-flow-fixture.php room <userId>
 *   php tests-e2e/meeting-flow-fixture.php state <recToken>
 *   php tests-e2e/meeting-flow-fixture.php room-recording <roomId>
 *   php tests-e2e/meeting-flow-fixture.php meeting-room <título>
 *   php tests-e2e/meeting-flow-fixture.php cleanup <userId>
 *
 * Tudo o que é criado pertence ao usuário de teste e sai no cleanup.
 */

// Mesmo banco que a app usa servida em 127.0.0.1 (ver make-room.php).
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('PUBLIC_PATH', BASE_PATH . '/public');
date_default_timezone_set('America/Sao_Paulo');
spl_autoload_register(function ($class) {
    foreach (['core', 'controllers', 'models'] as $d) {
        $f = APP_PATH . "/{$d}/{$class}.php";
        if (is_file($f)) { require_once $f; return; }
    }
});

$db = Database::getInstance();
$rooms = new VideoRoom();
$cmd = $argv[1] ?? '';
$arg = $argv[2] ?? '';

function out($data): void { echo json_encode($data, JSON_UNESCAPED_UNICODE); }

function newRoom(VideoRoom $rooms, int $userId, array $extra = []): array
{
    $token = $rooms->create(array_merge([
        'title' => 'E2E fluxo comercial', 'created_by' => $userId, 'max_participants' => 8,
        'allow_recording' => 1, 'allow_presentation' => 1, 'status' => 'active', 'visibility' => 'public',
        'expires_at' => date('Y-m-d H:i:s', strtotime('+1 day')),
    ], $extra));
    return $rooms->findByToken($token);
}

switch ($cmd) {
    case 'user':
        $u = uniqid();
        $email = "e2e_comercial_{$u}@example.test";
        $password = 'E2e-' . bin2hex(random_bytes(6));
        $id = (int)$db->insert('users', [
            'name' => 'E2E Comercial', 'email' => $email,
            'password' => password_hash($password, PASSWORD_BCRYPT), 'role' => 'comercial', 'is_active' => 1,
        ]);
        out(['id' => $id, 'email' => $email, 'password' => $password]);
        break;

    case 'recordings':
        $userId = (int)$arg;
        $meetingId = (int)$db->insert('agenda_meetings', [
            'title' => 'E2E Descoberta ACME', 'created_by' => $userId, 'assigned_to' => $userId,
            'meeting_type' => 'comercial', 'client_name' => 'ACME E2E', 'client_email' => 'acme-e2e@example.test',
        ]);
        $room = newRoom($rooms, $userId, ['meeting_id' => $meetingId]);
        $queued = $rooms->addRecording([
            'room_id' => $room['id'], 'file_path' => 'recordings/e2e-queued.webm', 'mime_type' => 'video/webm',
            'transcribe_status' => 'queued',
        ]);
        $q = $rooms->findRecordingByToken($queued);
        $db->insert('video_recording_segments', [
            'recording_id' => $q['id'], 'seq' => 1, 'start_sec' => 0,
            'file_path' => "recording-audio/{$queued}/seg_000001.webm", 'status' => 'pending',
        ]);
        $old = $rooms->addRecording([
            'room_id' => $room['id'], 'file_path' => 'recordings/e2e-old.webm', 'mime_type' => 'video/webm',
        ]);
        $summary = [
            'resumo' => 'ACME quer organizar o comercial.', 'topicos' => ['CRM'], 'topicos_nao_resolvidos' => [],
            'decisoes' => ['Enviar proposta'], 'proximos_passos' => ['Montar proposta até sexta'],
            'necessidades' => ['Organizar leads'], 'escopo' => ['CRM com WhatsApp'], 'prazos' => ['Dezembro'],
            'orcamento' => [], 'decisores' => ['Diretor comercial'],
        ];
        $draft = $rooms->addRecording([
            'room_id' => $room['id'], 'file_path' => 'recordings/e2e-draft.webm', 'mime_type' => 'video/webm',
            'transcribe_status' => 'done', 'transcribed_at' => date('Y-m-d H:i:s'),
            'transcript' => "[00:01] Precisamos organizar os leads\n[10:00] Mandem a proposta",
            'transcript_json' => json_encode([['start' => 1, 'end' => 3, 'text' => 'Precisamos organizar os leads'], ['start' => 600, 'end' => 602, 'text' => 'Mandem a proposta']]),
            'summary' => json_encode($summary, JSON_UNESCAPED_UNICODE),
            'minutes' => MeetingMinutesRules::textFromStructured($summary),
            'minutes_status' => 'done', 'minutes_generated_at' => date('Y-m-d H:i:s'),
        ]);
        out(['queued' => $queued, 'old' => $old, 'draft' => $draft, 'roomId' => (int)$room['id'], 'meetingId' => $meetingId]);
        break;

    case 'room':
        $room = newRoom($rooms, (int)$arg);
        out(['token' => $room['token'], 'id' => (int)$room['id']]);
        break;

    case 'state':
        $rec = $rooms->findRecordingByToken($arg);
        $segs = $rec ? (new RecordingSegment())->listByRecording((int)$rec['id']) : [];
        out(['rec' => $rec ?: null, 'segments' => count($segs)]);
        break;

    case 'room-recording':
        $rec = $db->fetch('SELECT * FROM video_recordings WHERE room_id = ? ORDER BY id DESC LIMIT 1', [(int)$arg]);
        $segModel = new RecordingSegment();
        $segs = $rec ? $segModel->listByRecording((int)$rec['id']) : [];
        out([
            'rec' => $rec ?: null,
            'segments' => array_map(function ($s) use ($segModel) {
                $path = $segModel->absolutePath($s['file_path']);
                return ['seq' => (int)$s['seq'], 'start_sec' => (float)$s['start_sec'], 'size' => is_file($path) ? filesize($path) : 0, 'mime' => $s['mime_type']];
            }, $segs),
        ]);
        break;

    case 'meeting-room':
        $m = $db->fetch('SELECT id FROM agenda_meetings WHERE title = ? ORDER BY id DESC LIMIT 1', [$arg]);
        $room = $m ? $db->fetch('SELECT auto_record FROM video_rooms WHERE meeting_id = ? ORDER BY id DESC LIMIT 1', [$m['id']]) : null;
        out(['meeting' => $m ?: null, 'auto_record' => $room ? (int)$room['auto_record'] : null]);
        break;

    case 'cleanup':
        $userId = (int)$arg;
        $segModel = new RecordingSegment();
        foreach ($db->fetchAll('SELECT id FROM video_rooms WHERE created_by = ?', [$userId]) as $r) {
            foreach ($db->fetchAll('SELECT id, token, file_path FROM video_recordings WHERE room_id = ?', [$r['id']]) as $rec) {
                $db->delete('video_recording_segments', 'recording_id = ?', [$rec['id']]);
                foreach (glob(BASE_PATH . '/storage/recording-audio/' . $rec['token'] . '/*') ?: [] as $f) @unlink($f);
                @rmdir(BASE_PATH . '/storage/recording-audio/' . $rec['token']);
                @unlink(PUBLIC_PATH . '/uploads/' . $rec['file_path']);
                foreach ($db->fetchAll('SELECT id FROM proposals WHERE recording_id = ?', [$rec['id']]) as $p) {
                    $db->delete('proposal_items', 'proposal_id = ?', [$p['id']]);
                    $db->delete('proposal_events', 'proposal_id = ?', [$p['id']]);
                    $db->delete('proposals', 'id = ?', [$p['id']]);
                }
            }
            $db->delete('video_recordings', 'room_id = ?', [$r['id']]);
            foreach (['video_room_participants', 'video_room_signals', 'video_room_admins', 'video_room_join_requests'] as $t) {
                try { $db->delete($t, 'room_id = ?', [$r['id']]); } catch (\Throwable $e) {}
            }
            foreach (glob(BASE_PATH . '/storage/video-recordings/' . $r['id'] . '_*', GLOB_ONLYDIR) ?: [] as $dir) {
                foreach (glob($dir . '/{,.}*', GLOB_BRACE) ?: [] as $f) if (is_file($f)) @unlink($f);
                foreach (glob($dir . '/audio/*') ?: [] as $f) @unlink($f);
                @rmdir($dir . '/audio');
                @rmdir($dir);
            }
            $db->delete('video_rooms', 'id = ?', [$r['id']]);
        }
        foreach ($db->fetchAll('SELECT id FROM agenda_meetings WHERE created_by = ?', [$userId]) as $m) {
            $db->delete('agenda_meeting_clients', 'meeting_id = ?', [$m['id']]);
            $db->delete('agenda_meeting_participants', 'meeting_id = ?', [$m['id']]);
            $db->delete('agenda_meetings', 'id = ?', [$m['id']]);
        }
        $db->delete('notifications', 'user_id = ?', [$userId]);
        try { $db->delete('user_activity_log', 'user_id = ?', [$userId]); } catch (\Throwable $e) {}
        $db->delete('users', 'id = ?', [$userId]);
        out(['ok' => true]);
        break;

    default:
        fwrite(STDERR, "comando desconhecido\n");
        exit(1);
}
