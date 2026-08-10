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
            return false;
        }

        if (isset($result['error']) && $result['error']) {
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
}
