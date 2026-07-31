<?php

class PagesController extends Controller
{
    // Termos de Uso (público, sem login)
    public function termos()
    {
        $appName = Config::get('app_name') ?: 'ON Solutions Helpdesk';
        $faviconUrl = Config::get('app_favicon');
        $logoUrl = Config::get('app_logo');
        $base = baseUrl('');
        require APP_PATH . '/views/pages/termos.php';
        exit;
    }

    // Política de Privacidade (público, sem login)
    public function privacidade()
    {
        $appName = Config::get('app_name') ?: 'ON Solutions Helpdesk';
        $faviconUrl = Config::get('app_favicon');
        $logoUrl = Config::get('app_logo');
        $base = baseUrl('');
        require APP_PATH . '/views/pages/privacidade.php';
        exit;
    }
}
