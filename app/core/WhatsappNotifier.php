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

        // Cada instância é específica (ex.: Prospecção x Atendimento). As
        // notificações do sistema saem SEMPRE pela instância padrão compartilhada
        // (is_default = 1, sem vínculo de usuário). Não trocamos de instância
        // automaticamente para não enviar pela conexão errada.
        $instance = $db->fetch("SELECT * FROM whatsapp_instances WHERE is_default = 1 AND user_id IS NULL LIMIT 1");
        if (!$instance) {
            $instance = $db->fetch("SELECT * FROM whatsapp_instances WHERE is_default = 1 LIMIT 1");
        }
        if (!$instance) {
            self::log("NENHUMA INSTANCIA PADRAO configurada para phone={$phone}. Defina uma instância padrão em /whatsapp.");
            return false;
        }

        $api = EvolutionApi::fromInstance($instance['id']);
        if (!$api) {
            self::log("SEM API para instancia padrao={$instance['id']} phone={$phone}");
            return false;
        }

        // Normalizar o número para JID individual
        $jid = $api->normalizeJid($api->normalizeNumber($phone));

        try {
            $result = $api->sendText($jid, $message);
        } catch (Exception $e) {
            self::log("EXCECAO sendText phone={$phone} jid={$jid} instance={$instance['id']}: " . $e->getMessage());
            return false;
        }

        // Falha reportada pela Evolution (ex.: Connection Closed): NÃO grava no chat.
        // A instância padrão está desconectada/instável — precisa reconectar em /whatsapp.
        if (!empty($result['error'])) {
            self::log("FALHA ENVIO phone={$phone} jid={$jid} instance={$instance['id']}"
                . " http=" . ($result['http_code'] ?? '?')
                . " msg=" . ($result['message'] ?? '')
                . " resp=" . json_encode($result['response'] ?? null));
            return false;
        }

        // Envio aceito exige a chave da mensagem (result.key). Sem ela, não confirma.
        if (empty($result['key'])) {
            self::log("ENVIO NAO CONFIRMADO (sem key) phone={$phone} jid={$jid} instance={$instance['id']} resp=" . json_encode($result));
            return false;
        }

        // JID real retornado pela Evolution (pode diferir por causa do 9º dígito)
        $realJid = $result['key']['remoteJid'] ?? $jid;
        if (strpos($realJid, '@') === false) {
            $realJid = $api->normalizeJid($realJid);
        }
        $realPhone = $api->extractPhone($realJid);

        self::persistChatMessage($db, $instance, $realJid, $realPhone, $message, $result, $contactName, $phone);

        return true;
    }

    /**
     * Registra no histórico do chat a mensagem enviada com sucesso.
     */
    private static function persistChatMessage($db, $instance, $realJid, $realPhone, $message, $result, $contactName, $phone)
    {
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

            self::log("OK phone={$phone} jid={$realJid} instance={$instance['id']} contact={$contactId}");
        } catch (Exception $e) {
            self::log("ERRO persistencia phone={$phone} jid={$realJid}: " . $e->getMessage());
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
