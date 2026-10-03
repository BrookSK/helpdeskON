<?php

/**
 * Cofre de credenciais do cliente (Fase 6).
 *
 * O segredo (senha/token) é SEMPRE criptografado via CredentialCrypto antes de
 * gravar (coluna secret_encrypted). A listagem nunca expõe o valor: usa máscara.
 * Só a ação explícita de "revelar" (restrita a super_admin, com log) descriptografa.
 */
class ClientCredential
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById($id)
    {
        return $this->db->fetch("SELECT * FROM client_credentials WHERE id = ?", [$id]);
    }

    /**
     * Lista as credenciais (opcionalmente por empresa) SEM o segredo em claro.
     * Cada linha recebe 'secret_mask' no lugar do valor; secret_encrypted é
     * removido do retorno para não vazar o ciphertext em telas/logs.
     *
     * @return array<int,array>
     */
    public function getAll($companyId = null): array
    {
        $sql = "SELECT c.*, co.name AS company_name
                FROM client_credentials c
                LEFT JOIN companies co ON c.company_id = co.id";
        $params = [];
        if ($companyId !== null) {
            $sql .= " WHERE c.company_id = ?";
            $params[] = $companyId;
        }
        $sql .= " ORDER BY co.name, c.service_label";
        $rows = $this->db->fetchAll($sql, $params);
        foreach ($rows as &$r) {
            $r['secret_mask'] = CredentialCrypto::mask($r['secret_encrypted'] ?? null);
            unset($r['secret_encrypted']);
        }
        unset($r);
        return $rows;
    }

    /**
     * Cria uma credencial. $data['secret'] (texto puro) é criptografado aqui e
     * nunca persiste em claro.
     */
    public function create(array $data): int
    {
        $row = $this->buildRow($data);
        $row['created_by'] = $data['created_by'] ?? null;
        return $this->db->insert('client_credentials', $row);
    }

    /**
     * Atualiza uma credencial. Só re-criptografa o segredo se 'secret' vier
     * preenchido (campo em branco mantém o segredo atual).
     */
    public function update($id, array $data): bool
    {
        $row = $this->buildRow($data, false);
        return $this->db->update('client_credentials', $row, 'id = ?', [$id]);
    }

    public function delete($id): bool
    {
        return $this->db->delete('client_credentials', 'id = ?', [$id]);
    }

    /**
     * Revela o segredo em texto puro (descriptografado). Só deve ser chamado
     * após checagem de permissão + log no controller. Retorna null se não achar
     * ou se a descriptografia falhar (chave trocada / dado corrompido).
     */
    public function revealSecret($id): ?string
    {
        $row = $this->findById($id);
        if (!$row || empty($row['secret_encrypted'])) return null;
        return CredentialCrypto::decrypt($row['secret_encrypted']);
    }

    /**
     * Monta a linha a persistir a partir do input, criptografando o segredo.
     * Quando $forceSecret=false, só inclui secret_encrypted se 'secret' veio
     * não-vazio (permite editar sem reescrever a senha).
     */
    private function buildRow(array $data, bool $forceSecret = true): array
    {
        $row = [
            'company_id'    => $data['company_id'] ?? null,
            'service_label' => trim((string)($data['service_label'] ?? '')),
            'username'      => $data['username'] ?? null,
            'url'           => $data['url'] ?? null,
            'notes'         => $data['notes'] ?? null,
        ];
        $secret = (string)($data['secret'] ?? '');
        if ($forceSecret || $secret !== '') {
            $row['secret_encrypted'] = $secret === '' ? null : CredentialCrypto::encrypt($secret);
        }
        return $row;
    }
}
