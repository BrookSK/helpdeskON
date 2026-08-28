<?php

/**
 * Serviço de notificação via grupos de WhatsApp.
 *
 * Reutiliza a conexão de chat existente (instâncias + EvolutionApi) para enviar
 * mensagens de texto a grupos. NÃO altera a integração da Evolution API — apenas
 * consome o método público sendText() já existente.
 */
class WhatsappNotifier
{
    /**
     * Envia uma mensagem de texto para um grupo de WhatsApp pelo seu remote_jid.
     * Localiza a instância do grupo (a mesma usada no chat) e dispara via EvolutionApi.
     * A mensagem enviada também é registrada em whatsapp_messages para aparecer no chat.
     *
     * @return bool sucesso
     */
    public static function sendToGroup($groupJid, $message)
    {
        if (empty($groupJid) || empty($message)) {
            return false;
        }

        $db = Database::getInstance();

        // Localizar o grupo (contato) para descobrir a instância vinculada
        $group = $db->fetch(
            "SELECT * FROM whatsapp_contacts WHERE remote_jid = ? AND is_group = 1 LIMIT 1",
            [$groupJid]
        );

        // Determinar a instância: a do grupo, ou a padrão como fallback
        if ($group && !empty($group['instance_id'])) {
            $instanceId = $group['instance_id'];
            $api = EvolutionApi::fromInstance($instanceId);
        } else {
            $api = EvolutionApi::getDefault();
            $instanceId = null;
        }

        if (!$api) {
            return false;
        }

        try {
            $result = $api->sendText($groupJid, $message);
        } catch (Exception $e) {
            self::log("EXCECAO sendText grupo jid={$groupJid}: " . $e->getMessage());
            return false;
        }

        if (!empty($result['error'])) {
            self::log("FALHA ENVIO grupo jid={$groupJid} http=" . ($result['http_code'] ?? '?')
                . " msg=" . ($result['message'] ?? ''));
            return false;
        }
        if (empty($result['key'])) {
            self::log("ENVIO GRUPO NAO CONFIRMADO (sem key) jid={$groupJid} resp=" . json_encode($result));
            return false;
        }

        // Registrar a mensagem enviada no histórico do chat (se o grupo é conhecido)
        if ($group) {
            try {
                $db->insert('whatsapp_messages', [
                    'instance_id' => $group['instance_id'],
                    'contact_id' => $group['id'],
                    'remote_jid' => $groupJid,
                    'message_id' => $result['key']['id'] ?? uniqid('notif_'),
                    'from_me' => 1,
                    'message_type' => 'text',
                    'message_text' => $message,
                    'sender_name' => 'Sistema',
                    'timestamp' => date('Y-m-d H:i:s'),
                    'is_read' => 1,
                ]);
                $db->update('whatsapp_contacts', ['last_message_at' => date('Y-m-d H:i:s')], 'id = ?', [$group['id']]);
            } catch (Exception $e) {
                // Silencioso — o envio já ocorreu, apenas o log falhou
            }
        }

        return true;
    }

    /**
     * Envia para o grupo padrão configurado nas Settings (grupo da empresa dona do helpdesk).
     */
    public static function sendToDefaultGroup($message)
    {
        if (Config::get('whatsapp_group_notify_enabled') !== '1') {
            return false;
        }
        $defaultJid = Config::get('whatsapp_default_group_jid');
        if (empty($defaultJid)) {
            return false;
        }
        return self::sendToGroup($defaultJid, $message);
    }

    /**
     * Envia uma mensagem de texto para um número individual (contato) e registra
     * no histórico do chat (whatsapp_messages), para aparecer na janela do chat.
     * Cria/atualiza o contato se necessário.
     *
     * @return bool sucesso
     */
    public static function sendToPhone($phone, $message, $contactName = null)
    {
        if (empty($phone) || empty($message)) {
            return false;
        }

        $db = Database::getInstance();

        // Seleciona uma instância REALMENTE conectada. A coluna connection_status
        // pode estar desatualizada (o Baileys derruba a sessão sem avisar o banco),
        // então verificamos o estado ao vivo na Evolution (connectionState) e
        // sincronizamos o banco. Assim evitamos o erro "Connection Closed".
        $instance = self::pickConnectedInstance($db);
        if (!$instance) {
            self::log("NENHUMA INSTANCIA CONECTADA para phone={$phone}. Reconecte o WhatsApp em /whatsapp.");
            return false;
        }

        $api = EvolutionApi::fromInstance($instance['id']);
        if (!$api) {
            self::log("SEM API/INSTANCIA para phone={$phone}");
            return false;
        }

        // Normalizar o número para JID individual
        $jid = $api->normalizeJid($api->normalizeNumber($phone));
        $phoneOnly = $api->extractPhone($jid);

        // Enviar a mensagem
        $result = [];
        try {
            $result = $api->sendText($jid, $message);
        } catch (Exception $e) {
            self::log("EXCECAO sendText phone={$phone} jid={$jid}: " . $e->getMessage());
            return false;
        }

        // Validar a resposta da Evolution: se veio erro, NÃO grava no chat e retorna false.
        // Antes o código gravava a mensagem no chat mesmo com falha de envio, dando a
        // falsa impressão de que a notificação tinha sido entregue.
        if (!empty($result['error'])) {
            self::log("FALHA ENVIO phone={$phone} jid={$jid} instance=" . ($instance['id'] ?? 'global')
                . " http=" . ($result['http_code'] ?? '?')
                . " msg=" . ($result['message'] ?? '')
                . " resp=" . json_encode($result['response'] ?? null));
            return false;
        }

        // Envio aceito exige uma chave de mensagem (result.key). Sem ela, tratamos como
        // não confirmado para não registrar algo que não saiu de fato.
        if (empty($result['key'])) {
            self::log("ENVIO NAO CONFIRMADO (sem key) phone={$phone} jid={$jid} resp=" . json_encode($result));
            return false;
        }

        // Usar o JID real retornado pela Evolution (pode diferir do normalizado por causa do 9º dígito)
        $realJid = $result['key']['remoteJid'] ?? $jid;
        if (strpos($realJid, '@') === false) {
            $realJid = $api->normalizeJid($realJid);
        }
        $realPhone = $api->extractPhone($realJid);

        // Registrar no chat apenas quando o envio foi confirmado pela Evolution,
        // usando os mesmos models do fluxo de mensagens recebidas.
        if ($instance) {
            try {
                $contactModel = new WhatsappContact();
                $messageModel = new WhatsappMessage();

                // upsert cria o contato se não existir (mesma função usada pelo webhook)
                $contactId = $contactModel->upsert($instance['id'], $realJid, [
                    'phone' => $realPhone,
                    'is_group' => 0,
                    'last_message_at' => date('Y-m-d H:i:s'),
                ], $contactName);

                // Garantir que o contato fique visível no chat (desarquivado) e com nome
                $updateContact = ['is_archived' => 0];
                if (!empty($contactName)) {
                    $existing = $contactModel->findById($contactId);
                    if ($existing && empty($existing['contact_name'])) {
                        $updateContact['contact_name'] = $contactName;
                    }
                }
                $db->update('whatsapp_contacts', $updateContact, 'id = ?', [$contactId]);

                $messageModel->create([
                    'instance_id' => $instance['id'],
                    'contact_id' => $contactId,
                    'remote_jid' => $realJid,
                    'message_id' => $result['key']['id'] ?? uniqid('notif_'),
                    'from_me' => 1,
                    'message_type' => 'text',
                    'message_text' => $message,
                    'sender_name' => 'Sistema',
                    'timestamp' => date('Y-m-d H:i:s'),
                    'is_read' => 1,
                ]);

                $contactModel->updateLastMessage($contactId, date('Y-m-d H:i:s'));

                // Diagnóstico: linha real do contato + quantos o chat enxerga nesta instância
                $row = $db->fetch("SELECT id, instance_id, remote_jid, phone, contact_name, is_group, is_archived, service_status FROM whatsapp_contacts WHERE id = ?", [$contactId]);
                $visible = $db->fetch("SELECT COUNT(*) as t FROM whatsapp_contacts WHERE instance_id = ? AND is_group = 0 AND is_archived = 0", [$instance['id']]);
                self::log("OK phone={$phone} jid={$realJid} instance={$instance['id']} contact={$contactId} row=" . json_encode($row) . " visiveis={$visible['t']}");
            } catch (Exception $e) {
                self::log("ERRO persistencia phone={$phone} jid={$realJid}: " . $e->getMessage());
            }
        } else {
            self::log("SEM INSTANCIA para phone={$phone}");
        }

        return true;
    }

    /**
     * Escolhe uma instância compartilhada (sem vínculo de usuário) que esteja
     * REALMENTE conectada, verificando o estado ao vivo na Evolution API e
     * sincronizando a coluna connection_status no banco.
     *
     * Ordem de preferência dos candidatos:
     *  1) Padrão + sem vínculo de usuário;
     *  2) Sem vínculo de usuário;
     *  3) Padrão;
     *  4) Qualquer.
     *
     * @return array|null linha da instância conectada, ou null se nenhuma estiver.
     */
    private static function pickConnectedInstance($db)
    {
        $candidates = $db->fetchAll(
            "SELECT * FROM whatsapp_instances
             ORDER BY (is_default = 1 AND user_id IS NULL) DESC,
                      (user_id IS NULL) DESC,
                      is_default DESC,
                      id ASC"
        );

        foreach ($candidates as $inst) {
            if (self::isInstanceConnected($db, $inst)) {
                return $inst;
            }
        }
        return null;
    }

    /**
     * Consulta o estado real da instância na Evolution API e atualiza a coluna
     * connection_status. Retorna true se estiver 'open'/'connected'.
     */
    private static function isInstanceConnected($db, $instance)
    {
        try {
            $api = EvolutionApi::fromInstance($instance['id']);
            if (!$api) return false;

            $result = $api->connectionState();
            $state = $result['instance']['state'] ?? $result['state'] ?? 'close';

            // Sincroniza o banco se o estado divergir do registrado
            if (($instance['connection_status'] ?? null) !== $state) {
                try {
                    $db->update('whatsapp_instances', ['connection_status' => $state], 'id = ?', [$instance['id']]);
                } catch (\Throwable $e) { /* ignora */ }
            }

            return in_array($state, ['open', 'connected'], true);
        } catch (\Throwable $e) {
            self::log("ERRO connectionState instancia={$instance['id']}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Log de diagnóstico. Escreve em dois destinos:
     *  1) Logger centralizado -> error_log() do PHP, capturado pelo painel de
     *     logs do servidor (Plesk / valueserver).
     *  2) Arquivo próprio (public/uploads/whatsapp_notifier.log) para histórico.
     */
    private static function log($msg)
    {
        // 1) Painel de logs do servidor (Plesk). Prefixo facilita filtrar.
        try {
            if (class_exists('Logger')) {
                Logger::error('[WhatsappNotifier] ' . $msg);
            } else {
                error_log('[WhatsappNotifier] ' . $msg);
            }
        } catch (\Throwable $e) {
            // ignora
        }

        // 2) Arquivo próprio da aplicação
        try {
            $file = PUBLIC_PATH . '/uploads/whatsapp_notifier.log';
            file_put_contents($file, '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
        } catch (\Throwable $e) {
            // ignora
        }
    }
}
