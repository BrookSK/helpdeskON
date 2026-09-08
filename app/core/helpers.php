<?php

function baseUrl($path = '')
{
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    $base = rtrim(str_replace('/public', '', $scriptDir), '/');
    return $protocol . '://' . $host . $base . '/' . ltrim($path, '/');
}

function asset($path)
{
    return baseUrl('assets/' . ltrim($path, '/'));
}

function upload($path)
{
    return baseUrl('uploads/' . ltrim($path, '/'));
}

function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function escape($string)
{
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

function flash($key, $message = null)
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
    } else {
        $msg = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $msg;
    }
}

function old($key, $default = '')
{
    return $_SESSION['old'][$key] ?? $default;
}

function timeAgo($datetime)
{
    $time = strtotime($datetime);
    $diff = time() - $time;

    if ($diff < 60) return 'agora';
    if ($diff < 3600) return floor($diff / 60) . ' min atrás';
    if ($diff < 86400) return floor($diff / 3600) . 'h atrás';
    if ($diff < 604800) return floor($diff / 86400) . 'd atrás';
    return date('d/m/Y H:i', $time);
}

function statusLabel($status)
{
    $labels = [
        'open' => 'Aberto',
        'in_progress' => 'Em andamento',
        'em_revisao_interna' => 'Em Revisão Interna',
        'waiting_client' => 'Aguardando',
        'em_homologacao' => 'Em Homologação',
        'aprovado_producao' => 'Aprov. Produção',
        'completed' => 'Concluído',
        'denied' => 'Negado',
        'archived' => 'Arquivado',
    ];
    return $labels[$status] ?? $status;
}

function priorityLabel($priority)
{
    $labels = [
        'low' => 'Baixa',
        'medium' => 'Média',
        'high' => 'Alta',
        'urgent' => 'Urgente',
    ];
    return $labels[$priority] ?? $priority;
}

function roleLabel($role)
{
    $labels = [
        'super_admin' => 'Administrador',
        'attendant' => 'Atendente',
        'client' => 'Cliente',
        'whatsapp_agent' => 'Agente WhatsApp',
        'developer' => 'Desenvolvedor',
        'analyst' => 'Analista',
        'comercial' => 'Comercial',
        'marketing' => 'Marketing',
    ];
    return $labels[$role] ?? $role;
}

/**
 * Papéis internos da equipe (não clientes)
 */
function teamRoles()
{
    return ['super_admin', 'attendant', 'whatsapp_agent', 'developer', 'analyst'];
}

/**
 * Papéis que podem ser responsável técnico de uma demanda
 */
function technicalRoles()
{
    return ['developer', 'analyst', 'attendant'];
}

/**
 * Nome amigável para um controller registrado na auditoria de ações.
 */
function activityModuleLabel($controller)
{
    $labels = [
        'dashboard' => 'Painel',
        'tickets' => 'Demandas',
        'planning' => 'Planejamento',
        'crm' => 'CRM',
        'leadcapture' => 'Captura de Leads',
        'prospection' => 'Prospecção',
        'sequences' => 'Sequências',
        'marketing' => 'Marketing',
        'agenda' => 'Agenda',
        'documents' => 'Documentos',
        'whatsapp' => 'WhatsApp',
        'social' => 'Social',
        'buffer' => 'Buffer',
        'notifications' => 'Notificações',
        'users' => 'Usuários',
        'companies' => 'Empresas',
        'subusers' => 'Sub-usuários',
        'settings' => 'Configurações',
        'account' => 'Conta',
        'tickets_attendants' => 'Demandas',
        'login' => 'Autenticação',
        'password' => 'Senha',
        'api' => 'API',
        'callback' => 'Integrações',
    ];
    $key = strtolower((string)$controller);
    return $labels[$key] ?? ucfirst($key);
}

/**
 * Nome amigável para a ação (método do controller) registrada na auditoria.
 */
function activityActionLabel($action)
{
    $labels = [
        'index' => 'Acessou a listagem',
        'show' => 'Visualizou',
        'showcard' => 'Visualizou card',
        'create' => 'Abriu criação',
        'store' => 'Criou registro',
        'edit' => 'Abriu edição',
        'update' => 'Atualizou',
        'updatestatus' => 'Alterou status',
        'updatepriority' => 'Alterou prioridade',
        'delete' => 'Removeu',
        'deletepermanent' => 'Excluiu permanentemente',
        'assign' => 'Atribuiu atendente',
        'assigntechnical' => 'Atribuiu responsável técnico',
        'sendmessage' => 'Enviou mensagem',
        'comment' => 'Comentou',
        'upload' => 'Enviou anexo',
        'uploadattachment' => 'Enviou anexo',
        'authenticate' => 'Fez login',
        'logout' => 'Saiu',
        'loginas' => 'Entrou como usuário',
        'togglestatus' => 'Alterou status',
        'activity' => 'Consultou auditoria',
    ];
    $key = strtolower((string)$action);
    return $labels[$key] ?? ucfirst($key);
}
