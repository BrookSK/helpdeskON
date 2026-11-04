<?php
namespace Tests\Integration;

use Database;

/**
 * Dados da cadeia comercial (aceite até atendimento) para os testes de
 * integração: usuário, empresa, proposta com itens, contrato, projeto
 * financeiro e onboarding. Tudo o que é criado é registrado e apagado no
 * tearDown, na ordem inversa.
 */
trait CommercialFixtures
{
    protected Database $db;
    /** @var array<int,array{0:string,1:int}> */
    private array $created = [];

    protected function requireTestDb(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste não configurado.');
        }
        $this->db = Database::getInstance();
    }

    protected function track(string $table, $id): int
    {
        $this->created[] = [$table, (int)$id];
        return (int)$id;
    }

    protected function cleanupCreated(): void
    {
        if (!isset($this->db)) return;
        // Com as FKs ligadas, os ON DELETE CASCADE levam eventos e itens
        // criados pelo código testado. O que sobrar (FK sem cascade) sai na
        // segunda passada.
        $left = [];
        foreach (array_reverse($this->created) as [$table, $id]) {
            try { $this->db->delete($table, 'id = ?', [$id]); } catch (\Throwable $e) { $left[] = [$table, $id]; }
        }
        if ($left) {
            $this->db->query('SET FOREIGN_KEY_CHECKS=0');
            foreach ($left as [$table, $id]) {
                try { $this->db->delete($table, 'id = ?', [$id]); } catch (\Throwable $e) {}
            }
            $this->db->query('SET FOREIGN_KEY_CHECKS=1');
        }
        $this->created = [];
        \Config::reload();
    }

    protected function insertRow(string $table, array $data): int
    {
        return $this->track($table, $this->db->insert($table, $data));
    }

    protected function newUser(string $role = 'comercial', array $over = []): int
    {
        $u = uniqid('', true);
        return $this->insertRow('users', array_merge([
            'name' => "Teste {$role}", 'email' => "t_" . str_replace('.', '', $u) . "@example.test",
            'password' => password_hash('x', PASSWORD_BCRYPT), 'role' => $role, 'is_active' => 1,
        ], $over));
    }

    protected function newCompany(array $over = []): int
    {
        return $this->insertRow('companies', array_merge(['name' => 'Empresa ' . uniqid()], $over));
    }

    /**
     * Proposta com itens. Itens: [description, amount, billing('once'|'monthly')].
     */
    protected function newProposal(int $userId, array $over = [], array $items = [['Site', 10000, 'once']]): int
    {
        $id = $this->insertRow('proposals', array_merge([
            'public_token' => bin2hex(random_bytes(16)), 'title' => 'Proposta ' . uniqid(),
            'client_name' => 'Cliente Teste', 'client_email' => 'cliente@example.test',
            'status' => 'sent', 'total' => 0, 'created_by' => $userId,
        ], $over));
        if (!array_key_exists('root_proposal_id', $over)) {
            $this->db->update('proposals', ['root_proposal_id' => $id], 'id = ?', [$id]);
        }
        $pos = 0;
        $total = 0.0;
        foreach ($items as [$desc, $amount, $billing]) {
            $this->insertRow('proposal_items', [
                'proposal_id' => $id, 'description' => $desc, 'amount' => $amount,
                'billing' => $billing, 'position' => $pos++,
            ]);
            $total += $amount;
        }
        $this->db->update('proposals', ['total' => $total], 'id = ?', [$id]);
        return $id;
    }

    protected function newContract(int $userId, array $over = []): int
    {
        return $this->insertRow('contracts', array_merge([
            'public_token' => bin2hex(random_bytes(16)), 'title' => 'Contrato ' . uniqid(),
            'client_name' => 'Cliente Teste', 'client_email' => 'cliente@example.test',
            'status' => 'approved', 'created_by' => $userId,
        ], $over));
    }

    protected function newFinanceProject(int $userId, array $over = []): int
    {
        return $this->insertRow('finance_projects', array_merge([
            'title' => 'Financeiro ' . uniqid(), 'total_value' => 1000, 'status' => 'open', 'created_by' => $userId,
        ], $over));
    }

    /** Registra para limpeza as linhas criadas pelo código testado. */
    protected function trackWhere(string $table, string $where, array $params): void
    {
        foreach ($this->db->fetchAll("SELECT id FROM {$table} WHERE {$where}", $params) as $r) {
            $this->track($table, $r['id']);
        }
    }

    protected function setSetting(string $key, ?string $value): void
    {
        $old = $this->db->fetch('SELECT id FROM settings WHERE setting_key = ?', [$key]);
        if ($old) {
            $this->db->update('settings', ['setting_value' => $value], 'id = ?', [$old['id']]);
        } else {
            $this->insertRow('settings', ['setting_key' => $key, 'setting_value' => $value]);
        }
        \Config::reload();
    }
}
