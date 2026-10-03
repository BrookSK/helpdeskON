<?php

/**
 * Ponte lead -> cliente (Fase 9). Resolve o GAP histórico: não havia vínculo
 * entre whatsapp_contacts (lead) e companies/users (cliente).
 *
 * Registra a conversão em lead_company_links, preservando a cadeia potencial
 * cliente -> reunião -> proposta -> contrato -> onboarding -> projeto, sem
 * duplicar cadastro. Reutiliza empresa/usuário existentes quando possível.
 */
class LeadConversion
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** Vínculo existente para um contato (lead), se houver. */
    public function findByContact($contactId)
    {
        return $this->db->fetch("SELECT * FROM lead_company_links WHERE contact_id = ? ORDER BY id DESC LIMIT 1", [$contactId]);
    }

    public function findByCompany($companyId)
    {
        return $this->db->fetch("SELECT * FROM lead_company_links WHERE company_id = ? ORDER BY id DESC LIMIT 1", [$companyId]);
    }

    public function getAll()
    {
        return $this->db->fetchAll(
            "SELECT l.*, c.name AS company_name, wc.name AS contact_name
             FROM lead_company_links l
             LEFT JOIN companies c ON l.company_id = c.id
             LEFT JOIN whatsapp_contacts wc ON l.contact_id = wc.id
             ORDER BY l.id DESC"
        );
    }

    /**
     * Registra (ou devolve) o vínculo lead->empresa. Idempotente pela UNIQUE
     * (contact_id, company_id): se já existe, atualiza as referências e retorna o id.
     *
     * @param array $data contact_id, company_id, user_id, proposal_id, contract_id,
     *                    onboarding_id, converted_by, notes
     */
    public function link(array $data): int
    {
        $contactId = $data['contact_id'] ?? null;
        $companyId = $data['company_id'] ?? null;

        $existing = null;
        if ($contactId && $companyId) {
            $existing = $this->db->fetch(
                "SELECT * FROM lead_company_links WHERE contact_id = ? AND company_id = ? LIMIT 1",
                [$contactId, $companyId]
            );
        }

        $row = [
            'contact_id' => $contactId,
            'company_id' => $companyId,
            'user_id' => $data['user_id'] ?? null,
            'proposal_id' => $data['proposal_id'] ?? null,
            'contract_id' => $data['contract_id'] ?? null,
            'onboarding_id' => $data['onboarding_id'] ?? null,
            'converted_by' => $data['converted_by'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];

        if ($existing) {
            // Atualiza só as referências que vieram preenchidas (não apaga as antigas).
            $upd = [];
            foreach (['user_id', 'proposal_id', 'contract_id', 'onboarding_id'] as $k) {
                if (!empty($row[$k])) $upd[$k] = $row[$k];
            }
            if (!empty($upd)) $this->db->update('lead_company_links', $upd, 'id = ?', [$existing['id']]);
            return (int)$existing['id'];
        }

        return $this->db->insert('lead_company_links', $row);
    }

    /**
     * Converte um lead em cliente: cria a empresa e o usuário dono (se ainda não
     * existirem pelo e-mail) e registra o vínculo. Reutiliza registros existentes.
     * Não envia convite aqui (o caller decide) para manter o model sem efeitos de rede.
     *
     * @return array{company_id:int,user_id:?int,link_id:int,created_company:bool,created_user:bool}
     */
    public function convert(array $lead, $userId = null): array
    {
        $companyName = trim((string)($lead['company_name'] ?? $lead['name'] ?? 'Cliente'));
        $email = trim((string)($lead['email'] ?? ''));
        $phone = trim((string)($lead['phone'] ?? ''));
        $contactId = $lead['contact_id'] ?? null;

        // Empresa: reutiliza por nome se já existir.
        $createdCompany = false;
        $company = $this->db->fetch("SELECT * FROM companies WHERE name = ? LIMIT 1", [$companyName]);
        if ($company) {
            $companyId = (int)$company['id'];
        } else {
            $companyId = (int)$this->db->insert('companies', [
                'name' => $companyName,
                'email' => $email ?: null,
                'phone' => $phone ?: null,
            ]);
            $createdCompany = true;
        }

        // Usuário dono: reutiliza por e-mail se já existir.
        $ownerId = null;
        $createdUser = false;
        if ($email !== '') {
            $u = $this->db->fetch("SELECT * FROM users WHERE email = ? LIMIT 1", [$email]);
            if ($u) {
                $ownerId = (int)$u['id'];
            } else {
                $ownerId = (int)$this->db->insert('users', [
                    'name' => trim((string)($lead['name'] ?? $companyName)),
                    'email' => $email,
                    'password' => password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT),
                    'phone' => $phone ?: null,
                    'role' => 'client',
                    'company_id' => $companyId,
                    'is_company_owner' => 1,
                    'is_active' => 1,
                ]);
                $createdUser = true;
            }
        }

        $linkId = $this->link([
            'contact_id' => $contactId,
            'company_id' => $companyId,
            'user_id' => $ownerId,
            'proposal_id' => $lead['proposal_id'] ?? null,
            'contract_id' => $lead['contract_id'] ?? null,
            'onboarding_id' => $lead['onboarding_id'] ?? null,
            'converted_by' => $userId,
        ]);

        return [
            'company_id' => $companyId,
            'user_id' => $ownerId,
            'link_id' => $linkId,
            'created_company' => $createdCompany,
            'created_user' => $createdUser,
        ];
    }
}
