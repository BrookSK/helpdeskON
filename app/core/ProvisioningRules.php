<?php

/**
 * Regras puras (sem banco/HTTP) do Provisionamento (Fase 7).
 *
 * Define as etapas do provisionamento de infra no LRV Cloud e, para cada uma,
 * se ela é AUTOMATIZÁVEL pela API hoje ('auto') ou se vira PENDÊNCIA MANUAL
 * ('manual') por falta de endpoint na API (ver api-lrv-cloud-gaps.md). Isso
 * garante que nada fique "concluído" sem ter acontecido de verdade: enquanto a
 * API não expõe criar conta/VPS/app, essas etapas são explicitamente manuais.
 *
 * Também valida a assinatura HMAC do webhook do LRV Cloud e interpreta o evento.
 */
class ProvisioningRules
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_BLOCKED = 'blocked';
    public const STATUS_DONE = 'done';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUSES = [
        self::STATUS_PENDING, self::STATUS_IN_PROGRESS, self::STATUS_BLOCKED,
        self::STATUS_DONE, self::STATUS_CANCELLED,
    ];

    public const STEP_STATUS = ['pending', 'in_progress', 'done', 'blocked', 'skipped'];
    public const MODES = ['auto', 'manual'];

    public const PIPELINE_CX = 'esteira_cx';
    public const PIPELINE_OUT = 'fora_esteira';

    /**
     * Capacidades da API LRV Cloud HOJE (conferido na OpenAPI v1). Define quais
     * etapas podem ser 'auto'. Quando a API publicar os endpoints que faltam,
     * basta virar o flag para true aqui (fonte única de verdade).
     *
     * @return array<string,bool>
     */
    public static function apiCapabilities(): array
    {
        // API LRV Cloud v1.1: os 5 gaps foram implementados (POST /clients,
        // POST /hosting, POST /applications/git [+staging_subdomain], deploy,
        // POST /databases). Todas as etapas agora são automatizáveis.
        return [
            'create_client'   => true,  // POST /clients
            'create_vps'      => true,  // POST /hosting (provisiona)
            'create_database' => true,  // POST /databases
            'create_app'      => true,  // POST /applications/git
            'deploy'          => true,  // POST /applications/git/deploy
            'staging'         => true,  // staging_subdomain no deploy Git
            'add_domain'      => true,  // POST /domains
        ];
    }

    /**
     * Etapas padrão do provisionamento (ordem). mode é definido pela capacidade
     * atual da API: 'auto' só quando o endpoint existe; senão 'manual'.
     *
     * @return array<int,array{step_key:string,title:string,required:int,mode:string}>
     */
    public static function defaultSteps(?array $capabilities = null): array
    {
        $cap = $capabilities ?? self::apiCapabilities();
        $mode = fn(string $key) => (($cap[$key] ?? false) === true) ? 'auto' : 'manual';
        return [
            ['step_key' => 'create_client',       'title' => 'Criar conta do cliente no LRV Cloud', 'required' => 1, 'mode' => $mode('create_client')],
            ['step_key' => 'create_vps',          'title' => 'Provisionar VPS',                     'required' => 1, 'mode' => $mode('create_vps')],
            ['step_key' => 'create_database',     'title' => 'Criar banco de dados',                'required' => 1, 'mode' => $mode('create_database')],
            ['step_key' => 'create_app',          'title' => 'Criar aplicação (runtime + Git)',     'required' => 1, 'mode' => $mode('create_app')],
            ['step_key' => 'deploy',              'title' => 'Deploy inicial (pull/build)',         'required' => 1, 'mode' => $mode('deploy')],
            ['step_key' => 'staging',             'title' => 'Ambiente de homologação (domínio temporário)', 'required' => 0, 'mode' => $mode('staging')],
            ['step_key' => 'collect_credentials', 'title' => 'Reunir credenciais no cofre',         'required' => 1, 'mode' => 'manual'],
            ['step_key' => 'deliver',             'title' => 'Entregar link + escopo ao cliente',   'required' => 1, 'mode' => 'manual'],
        ];
    }

    public static function normalizeStatus($v): string
    {
        return in_array($v, self::STATUSES, true) ? $v : self::STATUS_PENDING;
    }

    public static function normalizeStepStatus($v): string
    {
        return in_array($v, self::STEP_STATUS, true) ? $v : 'pending';
    }

    public static function normalizeMode($v): string
    {
        return in_array($v, self::MODES, true) ? $v : 'manual';
    }

    public static function normalizePipeline($v): ?string
    {
        return in_array($v, [self::PIPELINE_CX, self::PIPELINE_OUT], true) ? $v : null;
    }

    /**
     * Uma etapa 'auto' só pode ser concluída pelo retorno da API (apiOk=true).
     * Uma etapa 'manual' é concluída pela ação do responsável (sempre permitida).
     */
    public static function canCompleteStep(array $step, bool $apiOk = false): bool
    {
        $mode = self::normalizeMode($step['mode'] ?? 'manual');
        if ($mode === 'auto') {
            return $apiOk === true;
        }
        return true;
    }

    /**
     * O provisionamento pode ser concluído? Todas as etapas obrigatórias
     * precisam estar 'done' ou 'skipped'.
     *
     * @param array<int,array> $steps
     */
    public static function canFinish(array $steps): bool
    {
        $required = array_filter($steps, fn($s) => (int)($s['required'] ?? 0) === 1);
        if (empty($required)) {
            foreach ($steps as $s) {
                if (in_array($s['status'] ?? '', ['pending', 'blocked', 'in_progress'], true)) return false;
            }
            return true;
        }
        foreach ($required as $s) {
            if (!in_array($s['status'] ?? '', ['done', 'skipped'], true)) return false;
        }
        return true;
    }

    /** Etapas obrigatórias ainda não concluídas (motivo do bloqueio). */
    public static function pendingRequired(array $steps): array
    {
        $out = [];
        foreach ($steps as $s) {
            if ((int)($s['required'] ?? 0) === 1 && !in_array($s['status'] ?? '', ['done', 'skipped'], true)) {
                $out[] = $s['title'] ?? ($s['step_key'] ?? 'etapa');
            }
        }
        return $out;
    }

    /** Etapas que hoje dependem de ação manual (gap de API) — para alertar na UI. */
    public static function manualSteps(array $steps): array
    {
        $out = [];
        foreach ($steps as $s) {
            if (self::normalizeMode($s['mode'] ?? 'manual') === 'manual') {
                $out[] = $s['title'] ?? ($s['step_key'] ?? 'etapa');
            }
        }
        return $out;
    }

    /**
     * Valida a assinatura HMAC-SHA256 do webhook do LRV Cloud. O cloud assina o
     * corpo cru com o secret e envia em X-Webhook-Signature como 'sha256=...'.
     */
    public static function verifyWebhookSignature(string $rawBody, string $signatureHeader, string $secret): bool
    {
        if ($secret === '' || $signatureHeader === '') return false;
        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);
        return hash_equals($expected, trim($signatureHeader));
    }

    /**
     * Interpreta o evento do webhook do LRV Cloud e devolve a etapa afetada +
     * se concluiu. Eventos conhecidos: hosting.created (VPS pronta),
     * application.installed (app instalada/deploy), domain.added.
     *
     * @return array{step_key:?string,done:bool}
     */
    public static function interpretEvent(?string $eventName): array
    {
        $e = strtolower(trim((string)$eventName));
        switch ($e) {
            case 'hosting.created':
            case 'hosting.ready':
                return ['step_key' => 'create_vps', 'done' => true];
            case 'application.installed':
            case 'application.deployed':
                return ['step_key' => 'deploy', 'done' => true];
            case 'domain.added':
                return ['step_key' => 'staging', 'done' => true];
            default:
                return ['step_key' => null, 'done' => false];
        }
    }

    /** A integração está configurada? (precisa de API key do LRV Cloud). */
    public static function isConfigured(?string $apiKey): bool
    {
        return is_string($apiKey) && trim($apiKey) !== '';
    }
}
