<?php

/**
 * Assinatura do contrato e o PDF assinado (change 02, D3).
 *
 * O webhook da ClickSign e o upload manual passam pelo mesmo caminho:
 * onContractSigned marca o contrato como assinado e garante um único projeto
 * financeiro, travando a linha do contrato para que webhook repetido, upload
 * depois do webhook e webhook depois do upload não dupliquem nada.
 *
 * O PDF fica fora de public/, em storage/contracts/<id>/. O caminho gravado no
 * banco é relativo a storage/. A ClickSign é injetável para os testes: objeto
 * com isConfigured(), getDocument($key) e downloadFile($url).
 */
class ContractSigning
{
    public const ORIGIN_CLICKSIGN = 'clicksign';
    public const ORIGIN_UPLOAD = 'upload';

    public const FILE_PENDING = 'pending';
    public const FILE_STORED = 'stored';
    public const FILE_FAILED = 'failed';

    /** Depois disso o cron para de tentar baixar e a tela pede o upload. */
    public const MAX_DOWNLOAD_ATTEMPTS = 10;

    private $model;
    private $db;
    private $storageRoot;
    private $clicksign;

    public function __construct(?Contract $model = null, ?string $storageRoot = null, $clicksign = null)
    {
        $this->model = $model ?? new Contract();
        $this->db = Database::getInstance();
        $this->storageRoot = rtrim($storageRoot ?? BASE_PATH . '/storage', '/\\');
        $this->clicksign = $clicksign;
    }

    public function absolutePath(string $relative): string
    {
        return $this->storageRoot . DIRECTORY_SEPARATOR . ltrim($relative, '/\\');
    }

    /**
     * Marca o contrato como assinado (se ainda não estiver) e garante o projeto
     * financeiro. Idempotente.
     *
     * @return array{ok:bool,code:int,error?:string,newly_signed:bool,finance_id:?int}
     */
    public function onContractSigned(int $contractId, string $origin, ?int $userId = null): array
    {
        $pdo = $this->db->getConnection();
        $pdo->beginTransaction();
        try {
            $c = $this->db->fetch("SELECT * FROM contracts WHERE id = ? FOR UPDATE", [$contractId]);
            if (!$c) {
                $pdo->rollBack();
                return ['ok' => false, 'code' => 404, 'error' => 'Contrato não encontrado.', 'newly_signed' => false, 'finance_id' => null];
            }
            $newly = false;
            if ($c['status'] !== ContractRules::STATUS_SIGNED) {
                if (!ContractRules::canTransition($c['status'], ContractRules::STATUS_SIGNED)) {
                    $pdo->rollBack();
                    return ['ok' => false, 'code' => 409, 'error' => 'O contrato precisa estar aprovado pelo cliente para ser assinado.', 'newly_signed' => false, 'finance_id' => null];
                }
                $this->model->update($contractId, [
                    'status' => ContractRules::STATUS_SIGNED,
                    'signed_at' => date('Y-m-d H:i:s'),
                    'signed_via' => $origin,
                ]);
                $this->model->addEvent($contractId, $userId, 'status', "Status: {$c['status']} -> signed");
                $this->model->addEvent($contractId, $userId, 'signed',
                    $origin === self::ORIGIN_UPLOAD ? 'Assinatura registrada pelo upload do PDF assinado' : 'Assinatura confirmada pela ClickSign');
                $c['status'] = ContractRules::STATUS_SIGNED;
                $newly = true;
            }
            $financeId = $this->ensureFinanceProject($c);
            $pdo->commit();
            return ['ok' => true, 'code' => 200, 'newly_signed' => $newly, 'finance_id' => $financeId];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /** Projeto financeiro único por contrato. Chamado com a linha do contrato travada. */
    private function ensureFinanceProject(array $contract): int
    {
        $fp = new FinanceProject();
        $existing = $fp->findByContract((int)$contract['id']);
        if ($existing) return (int)$existing['id'];

        $total = 0.0;
        if (!empty($contract['proposal_id'])) {
            $p = (new Proposal())->findById((int)$contract['proposal_id']);
            if ($p) $total = (float)$p['total'];
        }
        // created_by: o criador do contrato (o webhook não tem usuário logado).
        $userId = !empty($contract['created_by']) ? (int)$contract['created_by'] : null;
        return (int)$fp->createFromContract($contract, $total, $userId);
    }

    /**
     * Upload do PDF assinado fora da ClickSign. Confere tamanho e conteúdo,
     * guarda o arquivo e assina o contrato pelo mesmo caminho do webhook.
     *
     * @return array{ok:bool,code:int,error?:string,finance_id?:?int,newly_signed?:bool}
     */
    public function uploadSigned(int $contractId, string $filePath, ?int $userId = null, ?string $userName = null): array
    {
        $c = $this->model->findById($contractId);
        if (!$c) return ['ok' => false, 'code' => 404, 'error' => 'Contrato não encontrado.'];
        if (!ContractRules::canUploadSigned($c['status'])) {
            return ['ok' => false, 'code' => 409, 'error' => 'O contrato precisa estar aprovado pelo cliente antes do upload do assinado.'];
        }
        $size = is_file($filePath) ? (int)filesize($filePath) : 0;
        $head = $size > 0 ? (string)file_get_contents($filePath, false, null, 0, 5) : '';
        if ($error = ContractRules::signedFileError($size, $head)) {
            return ['ok' => false, 'code' => 400, 'error' => $error];
        }

        $relative = $this->store($contractId, (string)file_get_contents($filePath));
        try {
            $signed = $this->onContractSigned($contractId, self::ORIGIN_UPLOAD, $userId);
        } catch (\Throwable $e) {
            @unlink($this->absolutePath($relative));
            throw $e;
        }
        if (!$signed['ok']) {
            @unlink($this->absolutePath($relative));
            return $signed;
        }
        $this->replaceFile($c, $relative);
        $this->model->addEvent($contractId, $userId, 'signed_file',
            'PDF assinado enviado por upload' . ($userName ? ' por ' . $userName : '') . ' (' . number_format($size / 1048576, 2, ',', '.') . ' MB)');
        return $signed;
    }

    /** Depois do webhook: marca o PDF como pendente, se ainda não há arquivo. */
    public function markFilePending(int $contractId): void
    {
        $this->db->query(
            "UPDATE contracts SET signed_file_status = ?, signed_file_attempts = 0, signed_file_error = NULL
             WHERE id = ? AND (signed_file_status IS NULL OR signed_file_status = ?) AND signed_file_path IS NULL",
            [self::FILE_PENDING, $contractId, self::FILE_FAILED]
        );
    }

    /**
     * Uma tentativa de baixar o PDF assinado na ClickSign. A falha conta a
     * tentativa e guarda o erro, sem mexer no status do contrato.
     *
     * @return array{ok:bool,status:?string,error?:string}
     */
    public function fetchSignedFile(int $contractId): array
    {
        $c = $this->model->findById($contractId);
        if (!$c) return ['ok' => false, 'status' => null, 'error' => 'Contrato não encontrado.'];
        if (($c['signed_file_status'] ?? null) === self::FILE_STORED) return ['ok' => true, 'status' => self::FILE_STORED];
        if (empty($c['clicksign_doc_key'])) return ['ok' => false, 'status' => $c['signed_file_status'] ?? null, 'error' => 'Contrato sem documento na ClickSign.'];

        $api = $this->clicksign ?? new ClickSignApi();
        $error = null;
        $body = null;
        if (!$api->isConfigured()) {
            $error = 'ClickSign não configurada.';
        } else {
            $doc = $api->getDocument((string)$c['clicksign_doc_key']);
            $url = $doc['data']['document']['downloads']['signed_file_url'] ?? null;
            if (empty($doc['success'])) {
                $error = (string)($doc['error'] ?? 'Falha ao consultar o documento.');
            } elseif (!$url) {
                $error = 'A ClickSign ainda não liberou o PDF assinado.';
            } else {
                $dl = $api->downloadFile((string)$url);
                $body = $dl['body'] ?? null;
                if (empty($dl['success']) || !is_string($body)) {
                    $error = (string)($dl['error'] ?? 'Falha no download.');
                } elseif ($err = ContractRules::signedFileError(strlen($body), substr($body, 0, 5))) {
                    $error = 'Arquivo recebido da ClickSign recusado: ' . $err;
                }
            }
        }

        if ($error !== null) {
            $attempts = (int)($c['signed_file_attempts'] ?? 0) + 1;
            $status = $attempts >= self::MAX_DOWNLOAD_ATTEMPTS ? self::FILE_FAILED : self::FILE_PENDING;
            $this->model->update($contractId, [
                'signed_file_status' => $status, 'signed_file_attempts' => $attempts,
                'signed_file_error' => mb_substr($error, 0, 255),
            ]);
            return ['ok' => false, 'status' => $status, 'error' => $error];
        }

        $relative = $this->store($contractId, $body);
        $this->replaceFile($c, $relative);
        $this->model->update($contractId, ['signed_file_attempts' => (int)($c['signed_file_attempts'] ?? 0) + 1]);
        $this->model->addEvent($contractId, null, 'signed_file', 'PDF assinado baixado da ClickSign');
        return ['ok' => true, 'status' => self::FILE_STORED];
    }

    /**
     * Rotina do cron: tenta de novo os PDFs pendentes.
     * @return array{checked:int,stored:int,failed:int}
     */
    public function retryPending(int $limit = 20): array
    {
        $rows = $this->db->fetchAll(
            "SELECT id FROM contracts WHERE signed_file_status = ? AND clicksign_doc_key IS NOT NULL
             ORDER BY id ASC LIMIT " . max(1, $limit),
            [self::FILE_PENDING]
        );
        $out = ['checked' => 0, 'stored' => 0, 'failed' => 0];
        foreach ($rows as $r) {
            $out['checked']++;
            $res = $this->fetchSignedFile((int)$r['id']);
            $res['ok'] ? $out['stored']++ : $out['failed']++;
        }
        return $out;
    }

    /** Grava o PDF em storage/contracts/<id>/ e devolve o caminho relativo. */
    private function store(int $contractId, string $bytes): string
    {
        $relDir = 'contracts/' . $contractId;
        $absDir = $this->absolutePath($relDir);
        if (!is_dir($absDir) && !mkdir($absDir, 0750, true) && !is_dir($absDir)) {
            throw new \RuntimeException('Não foi possível criar a pasta do contrato.');
        }
        $relative = $relDir . '/assinado-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.pdf';
        if (file_put_contents($this->absolutePath($relative), $bytes) === false) {
            throw new \RuntimeException('Não foi possível gravar o PDF assinado.');
        }
        return $relative;
    }

    /** Aponta o contrato para o arquivo novo e apaga o anterior, se havia. */
    private function replaceFile(array $contract, string $relative): void
    {
        $this->model->update((int)$contract['id'], [
            'signed_file_path' => $relative, 'signed_file_status' => self::FILE_STORED, 'signed_file_error' => null,
        ]);
        $old = (string)($contract['signed_file_path'] ?? '');
        if ($old !== '' && $old !== $relative) @unlink($this->absolutePath($old));
    }
}
