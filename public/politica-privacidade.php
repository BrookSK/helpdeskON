<?php
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
require_once APP_PATH . '/core/helpers.php';
require_once APP_PATH . '/core/Database.php';
require_once APP_PATH . '/core/Config.php';
$appName = Config::get('app_name') ?: 'ON Solutions Helpdesk';
$faviconUrl = Config::get('app_favicon');
$logoUrl = Config::get('app_logo');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Política de Privacidade - <?= htmlspecialchars($appName) ?></title>
    <?php if ($faviconUrl): ?>
    <link rel="icon" href="<?= $faviconUrl ?>">
    <?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f8f9fa; }
        .legal-container { max-width: 800px; margin: 0 auto; padding: 40px 20px; }
        .legal-header { text-align: center; margin-bottom: 40px; }
        .legal-header img { max-height: 45px; margin-bottom: 15px; }
        .legal-card { background: #fff; border-radius: 12px; padding: 40px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
        .legal-card h1 { font-size: 1.6rem; font-weight: 700; color: #1a1a2e; margin-bottom: 8px; }
        .legal-card h2 { font-size: 1.1rem; font-weight: 600; color: #333; margin-top: 28px; margin-bottom: 12px; }
        .legal-card h3 { font-size: 0.95rem; font-weight: 600; color: #444; margin-top: 16px; margin-bottom: 8px; }
        .legal-card p, .legal-card li { font-size: 0.9rem; color: #555; line-height: 1.7; }
        .legal-card ul { padding-left: 20px; }
        .legal-card ul li { margin-bottom: 6px; }
        .legal-card table { width: 100%; border-collapse: collapse; margin: 12px 0; font-size: 0.85rem; }
        .legal-card table th, .legal-card table td { padding: 10px 12px; border: 1px solid #e9ecef; text-align: left; }
        .legal-card table th { background: #f8f9fa; font-weight: 600; color: #333; }
        .legal-date { font-size: 0.8rem; color: #999; text-align: center; margin-top: 30px; }
        .legal-back { text-align: center; margin-top: 20px; }
        .legal-back a { color: #00BFA6; text-decoration: none; font-weight: 500; font-size: 0.9rem; }
        .legal-back a:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="legal-container">
    <div class="legal-header">
        <?php if ($logoUrl): ?>
        <div style="background:#1a1a2e;display:inline-block;padding:12px 20px;border-radius:10px;margin-bottom:10px;">
            <img src="<?= $logoUrl ?>" alt="Logo">
        </div>
        <?php else: ?>
        <h3 style="color:#00BFA6;font-weight:700;">ON Solutions</h3>
        <?php endif; ?>
    </div>

    <div class="legal-card">
        <h1>Política de Privacidade</h1>
        <p style="color:#999;font-size:0.82rem;">Em conformidade com a Lei Geral de Proteção de Dados (LGPD - Lei nº 13.709/2018)<br>Última atualização: Julho de 2026</p>

        <h2>1. Introdução</h2>
        <p>A ON Solutions ("nós", "nosso") tem o compromisso de proteger a privacidade dos dados pessoais dos usuários da plataforma <?= htmlspecialchars($appName) ?>. Esta Política de Privacidade descreve como coletamos, utilizamos, armazenamos e protegemos suas informações pessoais em conformidade com a Lei Geral de Proteção de Dados Pessoais (Lei nº 13.709/2018).</p>

        <h2>2. Dados Pessoais Coletados</h2>
        <p>Coletamos os seguintes dados pessoais durante o uso da Plataforma:</p>
        <table>
            <thead>
                <tr><th>Dado</th><th>Finalidade</th><th>Base Legal (LGPD)</th></tr>
            </thead>
            <tbody>
                <tr><td>Nome completo</td><td>Identificação do usuário</td><td>Execução de contrato</td></tr>
                <tr><td>Endereço de email</td><td>Comunicação e notificações</td><td>Execução de contrato</td></tr>
                <tr><td>Telefone</td><td>Contato e notificações WhatsApp</td><td>Consentimento</td></tr>
                <tr><td>Dados de acesso (IP, navegador)</td><td>Segurança e auditoria</td><td>Legítimo interesse</td></tr>
                <tr><td>Arquivos enviados</td><td>Anexos de demandas e documentos</td><td>Execução de contrato</td></tr>
                <tr><td>Mensagens e comentários</td><td>Comunicação dentro da plataforma</td><td>Execução de contrato</td></tr>
            </tbody>
        </table>

        <h2>3. Finalidades do Tratamento</h2>
        <p>Utilizamos os dados pessoais para:</p>
        <ul>
            <li>Prestar o serviço de helpdesk e gestão de demandas;</li>
            <li>Permitir comunicação entre clientes e equipe de suporte;</li>
            <li>Enviar notificações sobre o andamento das demandas;</li>
            <li>Garantir a segurança e integridade da Plataforma;</li>
            <li>Cumprir obrigações legais e regulatórias;</li>
            <li>Melhorar a experiência do usuário e o funcionamento do sistema.</li>
        </ul>

        <h2>4. Compartilhamento de Dados</h2>
        <p>Seus dados pessoais poderão ser compartilhados nas seguintes hipóteses:</p>
        <ul>
            <li><strong>Dentro da organização:</strong> entre membros da equipe de suporte para atendimento da demanda;</li>
            <li><strong>Prestadores de serviço:</strong> serviços de email (SMTP), infraestrutura em nuvem, sob contratos que garantem a proteção dos dados;</li>
            <li><strong>Obrigação legal:</strong> quando exigido por lei, decisão judicial ou autoridade competente;</li>
            <li><strong>Proteção de direitos:</strong> para proteger nossos direitos, propriedade ou segurança.</li>
        </ul>
        <p>Não comercializamos, vendemos ou alugamos dados pessoais a terceiros.</p>

        <h2>5. Armazenamento e Segurança</h2>
        <p>Os dados são armazenados em servidores seguros com as seguintes medidas de proteção:</p>
        <ul>
            <li>Criptografia de senhas com algoritmos seguros (bcrypt);</li>
            <li>Conexões protegidas por HTTPS/SSL;</li>
            <li>Controle de acesso baseado em papéis (RBAC);</li>
            <li>Backups periódicos;</li>
            <li>Monitoramento de acessos e atividades suspeitas.</li>
        </ul>

        <h2>6. Retenção de Dados</h2>
        <p>Os dados pessoais são retidos enquanto:</p>
        <ul>
            <li>A conta do usuário estiver ativa;</li>
            <li>For necessário para prestação do serviço;</li>
            <li>Houver obrigação legal de retenção;</li>
            <li>For necessário para o exercício regular de direitos.</li>
        </ul>
        <p>Após o encerramento da relação, os dados serão eliminados ou anonimizados, salvo obrigação legal de manutenção.</p>

        <h2>7. Direitos do Titular dos Dados</h2>
        <p>Em conformidade com os artigos 17 a 22 da LGPD, você possui os seguintes direitos:</p>
        <ul>
            <li><strong>Confirmação e acesso:</strong> confirmar se tratamos seus dados e acessá-los;</li>
            <li><strong>Correção:</strong> solicitar a correção de dados incompletos ou desatualizados;</li>
            <li><strong>Anonimização ou eliminação:</strong> solicitar a anonimização ou exclusão de dados desnecessários;</li>
            <li><strong>Portabilidade:</strong> solicitar a transferência dos dados a outro fornecedor;</li>
            <li><strong>Revogação do consentimento:</strong> revogar consentimento previamente concedido;</li>
            <li><strong>Oposição:</strong> opor-se ao tratamento quando realizado com base em legítimo interesse;</li>
            <li><strong>Informação sobre compartilhamento:</strong> saber com quais entidades seus dados foram compartilhados.</li>
        </ul>
        <p>Para exercer seus direitos, entre em contato através dos canais indicados na seção 11 desta política.</p>

        <h2>8. Cookies e Tecnologias de Rastreamento</h2>
        <p>A Plataforma utiliza cookies de sessão estritamente necessários para:</p>
        <ul>
            <li>Manter a sessão do usuário autenticado;</li>
            <li>Garantir o funcionamento correto das funcionalidades.</li>
        </ul>
        <p>Não utilizamos cookies de rastreamento, marketing ou análise comportamental.</p>

        <h2>9. Transferência Internacional de Dados</h2>
        <p>Seus dados podem ser processados em servidores localizados fora do Brasil, sempre em países que proporcionem grau de proteção adequado ou mediante cláusulas contratuais padrão que garantam a segurança dos dados.</p>

        <h2>10. Menores de Idade</h2>
        <p>A Plataforma não é destinada a menores de 18 anos. Não coletamos intencionalmente dados de menores. Caso identifiquemos dados de menor sem o devido consentimento do responsável legal, estes serão prontamente eliminados.</p>

        <h2>11. Encarregado de Proteção de Dados (DPO)</h2>
        <p>Para questões relacionadas à proteção de dados pessoais, dúvidas, solicitações ou reclamações, entre em contato:</p>
        <ul>
            <li><strong>Email:</strong> privacidade@onsolutionsbrasil.com.br</li>
            <li><strong>Canal:</strong> Através do próprio sistema de helpdesk</li>
        </ul>
        <p>Nos comprometemos a responder às solicitações dentro do prazo legal de 15 dias.</p>

        <h2>12. Autoridade Nacional de Proteção de Dados</h2>
        <p>Caso considere que o tratamento de seus dados pessoais viola a LGPD, você tem o direito de peticionar perante a Autoridade Nacional de Proteção de Dados (ANPD) — <a href="https://www.gov.br/anpd" target="_blank" style="color:#00BFA6;">www.gov.br/anpd</a>.</p>

        <h2>13. Alterações nesta Política</h2>
        <p>Esta Política poderá ser atualizada periodicamente. Recomendamos a revisão periódica. Alterações significativas serão comunicadas por meio da Plataforma ou por email.</p>

        <div class="legal-date">
            <p>ON Solutions Tecnologia LTDA<br>CNPJ: Informação disponível sob solicitação</p>
        </div>
    </div>

    <div class="legal-back">
        <a href="javascript:history.back()">← Voltar</a>
    </div>
</div>
</body>
</html>
