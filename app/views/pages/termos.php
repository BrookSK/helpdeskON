<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Termos de Uso - <?= htmlspecialchars($appName) ?></title>
    <?php if ($faviconUrl): ?>
    <link rel="icon" href="<?= $base . $faviconUrl ?>">
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
        .legal-card p, .legal-card li { font-size: 0.9rem; color: #555; line-height: 1.7; }
        .legal-card ul { padding-left: 20px; }
        .legal-card ul li { margin-bottom: 6px; }
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
            <img src="<?= $base . $logoUrl ?>" alt="Logo">
        </div>
        <?php else: ?>
        <h3 style="color:#00BFA6;font-weight:700;">ON Solutions</h3>
        <?php endif; ?>
    </div>

    <div class="legal-card">
        <h1>Termos de Uso</h1>
        <p style="color:#999;font-size:0.82rem;">Última atualização: Julho de 2026</p>

        <h2>1. Aceitação dos Termos</h2>
        <p>Ao acessar e utilizar a plataforma <?= htmlspecialchars($appName) ?> ("Plataforma"), você concorda integralmente com os presentes Termos de Uso. Caso não concorde com alguma disposição, solicitamos que não utilize o sistema.</p>

        <h2>2. Descrição do Serviço</h2>
        <p>A Plataforma é um sistema de helpdesk e gestão de demandas que permite:</p>
        <ul>
            <li>Abertura, acompanhamento e resolução de chamados/demandas;</li>
            <li>Comunicação entre clientes e equipe de suporte via chat interno;</li>
            <li>Gestão de documentos compartilhados;</li>
            <li>Planejamento e organização de tarefas internas;</li>
            <li>Notificações por email e outros canais.</li>
        </ul>

        <h2>3. Cadastro e Conta</h2>
        <p>Para utilizar a Plataforma, é necessário possuir uma conta de acesso fornecida pelo administrador do sistema. O usuário é responsável por:</p>
        <ul>
            <li>Manter suas credenciais de acesso em sigilo;</li>
            <li>Notificar imediatamente o administrador em caso de uso não autorizado;</li>
            <li>Fornecer informações verdadeiras e atualizadas.</li>
        </ul>

        <h2>4. Uso Adequado</h2>
        <p>O usuário compromete-se a utilizar a Plataforma de forma ética e em conformidade com a legislação vigente, sendo vedado:</p>
        <ul>
            <li>Utilizar o sistema para fins ilícitos ou que violem direitos de terceiros;</li>
            <li>Tentar acessar áreas restritas ou informações de outros usuários sem autorização;</li>
            <li>Transmitir conteúdo malicioso, vírus ou código prejudicial;</li>
            <li>Realizar engenharia reversa ou tentar comprometer a segurança do sistema;</li>
            <li>Compartilhar credenciais de acesso com terceiros.</li>
        </ul>

        <h2>5. Propriedade Intelectual</h2>
        <p>Todo o conteúdo da Plataforma, incluindo design, código-fonte, marca, logotipos e funcionalidades, é de propriedade exclusiva da ON Solutions ou de seus licenciantes, protegido pela legislação de propriedade intelectual.</p>

        <h2>6. Disponibilidade do Serviço</h2>
        <p>Empreendemos esforços para manter a Plataforma disponível de forma contínua, porém não garantimos disponibilidade ininterrupta. Manutenções programadas ou emergenciais podem causar indisponibilidade temporária, sem que isso gere direito a indenização.</p>

        <h2>7. Limitação de Responsabilidade</h2>
        <p>A ON Solutions não se responsabiliza por:</p>
        <ul>
            <li>Danos decorrentes de uso inadequado da Plataforma pelo usuário;</li>
            <li>Perdas resultantes de falhas de conexão à internet;</li>
            <li>Conteúdo inserido por usuários na Plataforma;</li>
            <li>Indisponibilidade causada por fatores externos ou de força maior.</li>
        </ul>

        <h2>8. Modificações nos Termos</h2>
        <p>Reservamo-nos o direito de alterar estes Termos a qualquer momento. As alterações entrarão em vigor a partir de sua publicação na Plataforma. O uso continuado após as modificações implica aceitação dos novos termos.</p>

        <h2>9. Rescisão</h2>
        <p>O acesso à Plataforma pode ser suspenso ou encerrado a qualquer momento pelo administrador, especialmente em caso de violação destes Termos de Uso.</p>

        <h2>10. Legislação Aplicável</h2>
        <p>Estes Termos são regidos pela legislação brasileira. Fica eleito o foro da comarca de São Paulo/SP para dirimir eventuais controvérsias, com renúncia a qualquer outro, por mais privilegiado que seja.</p>

        <h2>11. Contato</h2>
        <p>Para dúvidas sobre estes Termos de Uso, entre em contato através do email disponível nas configurações do sistema ou pelo canal de suporte da Plataforma.</p>

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
