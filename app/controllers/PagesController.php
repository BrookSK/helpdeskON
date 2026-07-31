<?php

class PagesController extends Controller
{
    // Termos de Uso (público, sem login)
    public function termos()
    {
        require PUBLIC_PATH . '/termos-de-uso.php';
        exit;
    }

    // Política de Privacidade (público, sem login)
    public function privacidade()
    {
        require PUBLIC_PATH . '/politica-privacidade.php';
        exit;
    }
}
