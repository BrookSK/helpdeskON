<?php

/**
 * Resposta do cliente à proposta pelo link público: aceite e recusa.
 *
 * Fica fora do controller para ser testável. Cada método devolve
 * ['ok' => bool, 'code' => http, 'error' => msg] e, no aceite, os dados
 * gravados. WhatsApp e e-mail para a equipe continuam no controller; o aviso
 * no sino (notifications) é gravado aqui.
 */
class ProposalResponse
{
    private $model;
    private $db;

    public function __construct(?Proposal $model = null)
    {
        $this->model = $model ?? new Proposal();
        $this->db = Database::getInstance();
    }

    /**
     * Motivo para a proposta não aceitar resposta agora, ou null se aceita.
     * Versão substituída e proposta encerrada não respondem.
     */
    public function blockReason(array $proposal): ?array
    {
        if (!empty($proposal['superseded_by'])) {
            return ['code' => 409, 'error' => 'Existe uma versão mais nova desta proposta. Abra o link mais recente que você recebeu.'];
        }
        if (ProposalRules::isTerminal($proposal['status'])) {
            return ['code' => 409, 'error' => 'Esta proposta já foi finalizada.'];
        }
        return null;
    }

    /**
     * Aceite: exige nome, recusa versão substituída e proposta vencida, grava
     * IP, navegador, nome e versão, e move o card do CRM para a coluna de ganho.
     *
     * @param array $input name, plan, ip, user_agent
     */
    public function accept(array $proposal, array $input): array
    {
        if ($block = $this->blockReason($proposal)) return ['ok' => false] + $block;
        if (ProposalRules::isExpired($proposal['validity_date'] ?? null)) {
            return ['ok' => false, 'code' => 409, 'error' => 'Esta proposta venceu em ' . date('d/m/Y', strtotime($proposal['validity_date'])) . '. Fale com a equipe para receber uma versão atualizada.'];
        }
        $name = ProposalRules::sanitizeAcceptName($input['name'] ?? '');
        if ($name === null) return ['ok' => false, 'code' => 400, 'error' => 'Informe seu nome para registrar o aceite.'];

        // Plano escolhido pelo cliente (quando a proposta oferece mais de uma opção).
        $items = $this->model->getItems($proposal['id']);
        $data = ProposalRules::buildPresentationData($proposal, $items);
        $acceptedPlan = null;
        $planLabel = '';
        if (!empty($data['plan_options'])) {
            $chosen = trim((string)($input['plan'] ?? ''));
            if (!in_array($chosen, array_column($data['plan_options'], 'key'), true)) {
                return ['ok' => false, 'code' => 400, 'error' => 'Selecione o plano que deseja aceitar.'];
            }
            $acceptedPlan = $chosen;
            foreach ($data['plan_options'] as $opt) {
                if ($opt['key'] === $chosen) { $planLabel = $opt['label']; break; }
            }
        }

        $respondedAt = date('Y-m-d H:i:s');
        $revision = (int)($proposal['revision_no'] ?? 1);
        $this->markSentForResponse($proposal);
        $extra = [
            'responded_at' => $respondedAt,
            'accepted_name' => $name,
            'accepted_ip' => mb_substr((string)($input['ip'] ?? ''), 0, 45) ?: null,
            'accepted_user_agent' => mb_substr((string)($input['user_agent'] ?? ''), 0, 255) ?: null,
            'accepted_revision' => $revision,
        ];
        if ($acceptedPlan !== null) $extra['accepted_plan'] = $acceptedPlan;
        if (!$this->model->changeStatus($proposal['id'], ProposalRules::STATUS_ACCEPTED, null, $extra)) {
            return ['ok' => false, 'code' => 409, 'error' => 'Não foi possível registrar o aceite. Tente novamente.'];
        }
        $label = ProposalRules::revisionLabel($revision);
        $msg = "Cliente aceitou a proposta ({$label}) — {$name}, IP " . ($extra['accepted_ip'] ?? '?')
            . ($planLabel !== '' ? ' — ' . $planLabel : '');
        $this->model->addEvent($proposal['id'], null, 'accepted', $msg);

        $card = $this->moveCardToWon($proposal);
        $this->notify($proposal, 'Proposta aceita',
            "O cliente aceitou a proposta \"{$proposal['title']}\" ({$label})." . ($planLabel !== '' ? " Plano: {$planLabel}." : ''));

        return [
            'ok' => true, 'code' => 200, 'accepted_plan' => $acceptedPlan, 'plan_label' => $planLabel,
            'responded_at' => $respondedAt, 'revision' => $label, 'card' => $card,
        ];
    }

    /** Recusa com motivo obrigatório. Volta o card para "Análise interna". */
    public function reject(array $proposal, $reason): array
    {
        if ($block = $this->blockReason($proposal)) return ['ok' => false] + $block;
        $reason = ProposalRules::sanitizeRejectReason($reason);
        if ($reason === null) return ['ok' => false, 'code' => 400, 'error' => 'Informe o motivo da recusa.'];

        $this->markSentForResponse($proposal);
        if (!$this->model->changeStatus($proposal['id'], ProposalRules::STATUS_REJECTED, null, [
            'responded_at' => date('Y-m-d H:i:s'),
            'reject_reason' => $reason,
        ])) {
            return ['ok' => false, 'code' => 409, 'error' => 'Não foi possível registrar a recusa. Tente novamente.'];
        }
        $this->model->addEvent($proposal['id'], null, 'rejected', 'Cliente recusou. Motivo: ' . $reason);
        $this->notify($proposal, 'Proposta recusada', "O cliente recusou a proposta \"{$proposal['title']}\". Motivo: {$reason}");

        // Volta o card do lead para "Análise interna" (refazer a proposta) — não é
        // perda, é retrabalho. Nunca interrompe a resposta ao cliente.
        if (!empty($proposal['contact_id'])) {
            try {
                (new CrmBoard())->moveContactToColumn((int)$proposal['contact_id'], CrmRules::COLUMN_INTERNAL_ANALYSIS);
            } catch (\Throwable $e) { /* não interrompe */ }
        }
        return ['ok' => true, 'code' => 200, 'reason' => $reason];
    }

    /**
     * Move o card ligado à proposta para a coluna de ganho do quadro dele e
     * marca o lead como convertido. Sem coluna de ganho, o card fica onde está
     * e o evento registra isso. Nunca interrompe o aceite.
     *
     * @return array{moved:bool,card_id:?int,column:?string}
     */
    public function moveCardToWon(array $proposal): array
    {
        $out = ['moved' => false, 'card_id' => null, 'column' => null];
        try {
            $board = new CrmBoard();
            $card = null;
            if (!empty($proposal['card_id'])) {
                $card = $board->findCard((int)$proposal['card_id']);
            } elseif (!empty($proposal['contact_id'])) {
                $card = $this->db->fetch("SELECT * FROM crm_cards WHERE contact_id = ? ORDER BY id DESC LIMIT 1", [(int)$proposal['contact_id']]);
            }
            if (!$card) return $out;
            $out['card_id'] = (int)$card['id'];
            $current = $board->findColumn((int)$card['column_id']);
            $won = $current ? CrmRules::findWonColumn($board->getColumns((int)$current['board_id'])) : null;
            if (!$won) {
                $this->model->addEvent($proposal['id'], null, 'crm', 'Card #' . $card['id'] . ' não foi movido: o quadro não tem coluna de ganho.');
                return $out;
            }
            if ((int)$won['id'] !== (int)$card['column_id']) {
                $board->moveCard((int)$card['id'], (int)$won['id'], 0);
            }
            $board->updateCard((int)$card['id'], ['lead_outcome' => 'converted', 'outcome_at' => date('Y-m-d H:i:s')]);
            $board->addActivity((int)$card['id'], null, 'note', 'Proposta aceita pelo cliente (' . ProposalRules::revisionLabel($proposal['revision_no'] ?? 1) . ')');
            $this->model->addEvent($proposal['id'], null, 'crm', 'Card #' . $card['id'] . ' movido para "' . $won['name'] . '".');
            $out['moved'] = true;
            $out['column'] = $won['name'];
        } catch (\Throwable $e) {
            if (class_exists('Logger')) Logger::error('Falha ao mover o card da proposta aceita', ['proposal' => $proposal['id'] ?? null, 'error' => $e->getMessage()]);
        }
        return $out;
    }

    /**
     * O cliente pode responder pelo link mesmo antes do envio formal (link copiado
     * do painel). A proposta passa por "enviada" para que aceite/recusa sejam
     * transições válidas.
     */
    private function markSentForResponse(array $proposal): void
    {
        $id = (int)$proposal['id'];
        if ($proposal['status'] === ProposalRules::STATUS_DRAFT) {
            $this->model->changeStatus($id, ProposalRules::STATUS_READY, null);
            $proposal['status'] = ProposalRules::STATUS_READY;
        }
        if ($proposal['status'] === ProposalRules::STATUS_READY) {
            $extra = empty($proposal['sent_at']) ? ['sent_at' => date('Y-m-d H:i:s')] : [];
            $this->model->changeStatus($id, ProposalRules::STATUS_SENT, null, $extra);
        }
    }

    /** Aviso no sino para o criador da proposta. Nunca interrompe. */
    private function notify(array $proposal, string $title, string $message): void
    {
        if (empty($proposal['created_by'])) return;
        try {
            $this->db->insert('notifications', [
                'user_id' => (int)$proposal['created_by'], 'title' => $title, 'message' => $message, 'type' => 'system',
            ]);
        } catch (\Throwable $e) { /* não interrompe a resposta ao cliente */ }
    }
}
