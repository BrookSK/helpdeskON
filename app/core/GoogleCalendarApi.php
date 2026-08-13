<?php

/**
 * Cliente da Google Calendar API para criar eventos com Google Meet.
 *
 * Autenticação via OAuth 2.0 com refresh token (fluxo offline).
 * Configure em Configurações:
 *  - google_client_id
 *  - google_client_secret
 *  - google_refresh_token   (gerado uma vez com escopo https://www.googleapis.com/auth/calendar)
 *  - google_calendar_id     (opcional; padrão "primary")
 *
 * O access token é obtido dinamicamente a partir do refresh token.
 */
class GoogleCalendarApi
{
    private $clientId;
    private $clientSecret;
    private $refreshToken;
    private $calendarId;
    private $accessToken = null;

    public function __construct()
    {
        $this->clientId = Config::get('google_client_id');
        $this->clientSecret = Config::get('google_client_secret');
        $this->refreshToken = Config::get('google_refresh_token');
        $this->calendarId = Config::get('google_calendar_id') ?: 'primary';
    }

    public function isConfigured()
    {
        return !empty($this->clientId) && !empty($this->clientSecret) && !empty($this->refreshToken);
    }

    /** Troca o refresh token por um access token válido. */
    private function getAccessToken()
    {
        if ($this->accessToken) return $this->accessToken;
        if (!$this->isConfigured()) return null;

        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'refresh_token' => $this->refreshToken,
                'grant_type' => 'refresh_token',
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($resp, true);
        $this->accessToken = $data['access_token'] ?? null;
        return $this->accessToken;
    }

    /**
     * Cria um evento com Google Meet.
     * @param array $params: title, description, start (Y-m-d H:i:s), durationMin, timezone, attendees (emails)
     * @return array ['success'=>bool, 'event_id'=>?, 'meet_link'=>?, 'html_link'=>?, 'error'=>?]
     */
    public function createEvent($params)
    {
        $token = $this->getAccessToken();
        if (!$token) return ['success' => false, 'error' => 'Google não configurado ou token inválido.'];

        $tz = $params['timezone'] ?? 'America/Sao_Paulo';
        $start = new DateTime($params['start'], new DateTimeZone($tz));
        $end = clone $start;
        $end->modify('+' . (int)($params['durationMin'] ?? 60) . ' minutes');

        $attendees = [];
        foreach (($params['attendees'] ?? []) as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) $attendees[] = ['email' => $email];
        }

        $body = [
            'summary' => $params['title'] ?? 'Reunião',
            'description' => $params['description'] ?? '',
            'start' => ['dateTime' => $start->format('c'), 'timeZone' => $tz],
            'end' => ['dateTime' => $end->format('c'), 'timeZone' => $tz],
            'attendees' => $attendees,
            'conferenceData' => [
                'createRequest' => [
                    'requestId' => uniqid('meet_'),
                    'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
                ],
            ],
            'reminders' => ['useDefault' => true],
        ];

        $url = 'https://www.googleapis.com/calendar/v3/calendars/' . rawurlencode($this->calendarId)
             . '/events?conferenceDataVersion=1&sendUpdates=all';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($resp, true);
        if ($code >= 400 || empty($data['id'])) {
            return ['success' => false, 'error' => $data['error']['message'] ?? 'Falha ao criar evento no Google.'];
        }

        // Extrai o link do Meet
        $meetLink = $data['hangoutLink'] ?? null;
        if (!$meetLink && !empty($data['conferenceData']['entryPoints'])) {
            foreach ($data['conferenceData']['entryPoints'] as $ep) {
                if (($ep['entryPointType'] ?? '') === 'video') { $meetLink = $ep['uri']; break; }
            }
        }

        return [
            'success' => true,
            'event_id' => $data['id'],
            'meet_link' => $meetLink,
            'html_link' => $data['htmlLink'] ?? null,
        ];
    }

    /** Atualiza data/hora de um evento existente. */
    public function updateEventTime($eventId, $start, $durationMin = 60, $timezone = 'America/Sao_Paulo')
    {
        $token = $this->getAccessToken();
        if (!$token || !$eventId) return ['success' => false];

        $tz = $timezone;
        $s = new DateTime($start, new DateTimeZone($tz));
        $e = clone $s; $e->modify('+' . (int)$durationMin . ' minutes');

        $body = [
            'start' => ['dateTime' => $s->format('c'), 'timeZone' => $tz],
            'end' => ['dateTime' => $e->format('c'), 'timeZone' => $tz],
        ];
        $url = 'https://www.googleapis.com/calendar/v3/calendars/' . rawurlencode($this->calendarId)
             . '/events/' . rawurlencode($eventId) . '?sendUpdates=all';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'PATCH',
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['success' => $code < 400];
    }
}
