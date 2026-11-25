<?php
/**
 * Procura e-mails devolvidos (bounce) nas mensagens mais recentes das caixas.
 *
 * O cron já trata cada devolução nova. Este scanner serve para o botão
 * "Verificar e-mails devolvidos" (Sequências): revê as últimas mensagens de
 * cada caixa, inclusive as que chegaram antes desta função existir. Tratar a
 * mesma devolução de novo não tem efeito (EmailMessageService::processBounce).
 */
class EmailBounceScanner
{
    /**
     * @param array $accounts linhas de email_accounts (com IMAP)
     * @param int   $perAccount quantas mensagens recentes olhar em cada caixa
     * @return array {accounts, checked, bounces: [...], errors: string[]}
     */
    public function scan(array $accounts, int $perAccount = 300): array
    {
        $svc = new EmailMessageService();
        $out = ['accounts' => 0, 'checked' => 0, 'bounces' => [], 'errors' => []];

        foreach ($accounts as $acc) {
            if (empty($acc['imap_host'])) continue;
            try {
                $reader = new ImapReader($acc);
                $conn = $reader->connect();
                if ($conn !== true) {
                    $out['errors'][] = ($acc['email'] ?? 'conta') . ': ' . (is_string($conn) ? $conn : 'não conectou');
                    continue;
                }
                $out['accounts']++;
                foreach ($reader->fetchRepliesSince(0, $perAccount) as $m) {
                    $out['checked']++;
                    if (!EmailBounceRules::isBounce($m)) continue;
                    $r = $svc->processBounce($m, $reader->fetchBounceText((int) $m['uid']), (int) $acc['id']);
                    if ($r['email'] === null) continue; // não era um envio nosso
                    $r['date'] = $m['date'] ?? null;
                    $out['bounces'][] = $r;
                }
                $reader->disconnect();
            } catch (\Throwable $e) {
                $out['errors'][] = ($acc['email'] ?? 'conta') . ': ' . $e->getMessage();
                Logger::error('EmailBounceScanner', ['account' => $acc['id'] ?? null, 'error' => $e->getMessage()]);
            }
        }
        return $out;
    }
}
