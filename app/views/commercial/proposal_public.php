<?php
/**
 * PROPOSTA ONLINE (pública, responsiva). Reproduz em HTML a mesma proposta do
 * PDF, consumindo a MESMA fonte de dados (ProposalRules::buildPresentationData),
 * para nunca divergir do PDF em valores, descontos, recorrência ou cronograma.
 *
 * Contém o aceite embutido (com seleção de plano quando há mais de uma opção),
 * a recusa com motivo e a solicitação de alteração. O PDF fica a um clique.
 */
$appName = htmlspecialchars(ProposalPdf::BRAND, ENT_QUOTES);
$respondedAt = !empty($proposal['responded_at']) ? date('d/m/Y \à\s H:i', strtotime($proposal['responded_at'])) : '';
$emblem = asset('on-emblem.png') . '?v=2';
$token = htmlspecialchars($proposal['public_token'], ENT_QUOTES);
$e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$brl = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');
$num = fn($v) => ($v === null || $v === '') ? '-' : rtrim(rtrim(number_format((float)$v, 2, ',', '.'), '0'), ',');
$brDate = fn($d) => $d ? date('d/m/Y', strtotime((string)$d)) : '';

$items = $data['items'];
$content = $data['content'];
$comm = $data['commercial'];
$timeline = $data['timeline'];
$projection = $data['projection'];
$plans = $data['plans'];
$planOptions = $data['plan_options'];
$discounts = $data['discounts'];
$vars = $data['vars'];
$hasMonthly = $data['has_monthly'];
$hasOnce = $data['has_once'];
$onceItems = array_values(array_filter($items, fn($it) => empty($it['is_monthly'])));
$monthItems = array_values(array_filter($items, fn($it) => !empty($it['is_monthly'])));
$yearDisc = round($comm['year']['gross'] - $comm['year']['net'], 2);
$clientLabel = trim((string)($proposal['client_name'] ?? '')) ?: 'Cliente';

$business = array_filter([
    'Perfil da empresa' => trim((string)($content['business_profile'] ?? '')),
    'Presença e canais atuais' => trim((string)($content['business_channels'] ?? '')),
    'Oportunidades' => trim((string)($content['business_opportunity'] ?? '')),
    'Pontos a validar no diagnóstico' => trim((string)($content['business_diagnosis'] ?? '')),
], fn($v) => $v !== '');

$blockLines = function ($text) {
    $out = [];
    foreach (preg_split('/\r?\n/', trim((string)$text)) as $line) {
        $line = trim((string)preg_replace('/^[-•*]\s*/u', '', trim($line)));
        if ($line !== '') $out[] = $line;
    }
    return $out;
};
$fixedBlock = function (string $key) use ($blocks, $vars) {
    $b = $blocks[$key] ?? null;
    if (!$b) return null;
    return ['title' => (string)$b['title'], 'body' => ProposalRules::applyVars((string)$b['body'], $vars)];
};
$period = (!empty($timeline['start']) && !empty($timeline['end']))
    ? $brDate($timeline['start']) . ' a ' . $brDate($timeline['end']) : 'A definir';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $e($proposal['title']) ?> · Proposta</title>
    <?php if ($fav = Config::get('app_favicon')): ?><link rel="icon" href="<?= baseUrl($fav) ?>"><?php endif; ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; font-family:'Inter',system-ui,sans-serif; color:#eaf2f1; line-height:1.5;
            background: radial-gradient(ellipse 70% 40% at 50% 0%, rgba(94,234,212,.12), transparent 70%), linear-gradient(170deg,#07141b 0%,#0b1f27 45%,#0e2e2b 100%); }
        a { color:#5eead4; }
        .wrap { max-width:860px; margin:0 auto; padding:28px 16px 120px; }
        .brand { display:flex; align-items:center; gap:12px; margin-bottom:22px; }
        .brand img { width:40px; filter:drop-shadow(0 0 16px rgba(0,191,166,.45)); }
        .brand strong { font-size:1.05rem; }
        .pill { display:inline-block; padding:5px 12px; border:1px solid rgba(94,234,212,.35); border-radius:999px; color:#5eead4; font-size:.68rem; letter-spacing:.14em; text-transform:uppercase; }
        h1 { font-size:1.8rem; line-height:1.15; margin:12px 0 4px; letter-spacing:-.02em; }
        h2 { font-size:1.1rem; margin:0 0 12px; letter-spacing:-.01em; color:#fff; }
        .for { color:#aecbce; margin:0 0 22px; }
        .card { background:rgba(255,255,255,.045); border:1px solid rgba(255,255,255,.09); border-radius:16px; padding:20px; margin-bottom:16px; }
        .row { display:flex; justify-content:space-between; gap:12px; padding:7px 0; color:#cfe2e3; border-bottom:1px solid rgba(255,255,255,.05); }
        .row:last-child { border-bottom:none; }
        .row b { color:#fff; font-weight:600; }
        .muted { color:#9fb6b8; }
        .mint { color:#5eead4; }
        .lead-list { margin:0; padding:0; list-style:none; }
        .lead-list li { padding:5px 0 5px 18px; position:relative; color:#cfe2e3; }
        .lead-list li::before { content:'▸'; position:absolute; left:0; color:#5eead4; }
        .svc { padding:12px 0; border-bottom:1px solid rgba(255,255,255,.06); }
        .svc:last-child { border-bottom:none; }
        .svc .top { display:flex; justify-content:space-between; gap:10px; align-items:baseline; }
        .svc .nm { font-weight:600; color:#fff; }
        .svc .tag { font-size:.76rem; color:#9fb6b8; white-space:nowrap; }
        .svc .desc { color:#b7cccd; font-size:.9rem; margin-top:3px; }
        table.vals { width:100%; border-collapse:collapse; }
        table.vals td { padding:7px 0; border-bottom:1px solid rgba(255,255,255,.06); }
        table.vals td.r { text-align:right; white-space:nowrap; }
        table.vals tr.tot td { border-top:2px solid rgba(94,234,212,.4); border-bottom:none; font-weight:700; color:#fff; padding-top:10px; }
        table.vals tr.disc td { color:#5eead4; }
        .plan-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:12px; }
        .plan { background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.12); border-radius:12px; padding:14px; }
        .plan h3 { margin:0 0 6px; font-size:.95rem; color:#fff; }
        .plan .big { font-size:1.3rem; font-weight:700; color:#5eead4; }
        .plan .sm { font-size:.78rem; color:#9fb6b8; }
        .timeline-item { display:flex; justify-content:space-between; gap:10px; padding:8px 0; border-bottom:1px solid rgba(255,255,255,.06); }
        .timeline-item:last-child { border-bottom:none; }
        .btn { display:inline-block; border:none; border-radius:10px; padding:13px 18px; font-size:.95rem; font-weight:700; cursor:pointer; text-decoration:none; font-family:inherit; text-align:center; }
        .btn-pdf { background:rgba(255,255,255,.1); color:#fff; border:1px solid rgba(255,255,255,.2); }
        .btn-ok { background:#00bfa6; color:#06231f; }
        .btn-no { background:transparent; color:#ffb4ad; border:1px solid #ffb4ad; }
        .btn-alt { background:transparent; color:#ffe08a; border:1px solid #ffe08a; }
        .actions { display:flex; gap:10px; margin-top:14px; flex-wrap:wrap; }
        .actions .btn { flex:1 1 auto; }
        .banner { padding:14px; border-radius:12px; margin-bottom:16px; }
        .banner.no { background:rgba(255,120,110,.15); color:#ffc8c2; }
        .done { display:flex; gap:14px; align-items:flex-start; padding:18px; border-radius:14px; margin-bottom:16px; }
        .done.ok { background:rgba(0,191,166,.16); border:1px solid rgba(94,234,212,.45); }
        .done.no { background:rgba(255,120,110,.12); border:1px solid rgba(255,180,173,.4); }
        .done .ic { flex:0 0 40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.3rem; font-weight:800; }
        .done.ok .ic { background:#00bfa6; color:#06231f; }
        .done.no .ic { background:#ffb4ad; color:#06231f; }
        .done strong { display:block; font-size:1.05rem; margin-bottom:4px; }
        .done p { margin:0; color:#d5e4e6; font-size:.92rem; }
        .plan-pick { display:flex; flex-direction:column; gap:8px; margin:12px 0; }
        .plan-pick label { display:flex; align-items:center; gap:10px; padding:11px 14px; border:1px solid rgba(255,255,255,.15); border-radius:10px; cursor:pointer; background:rgba(255,255,255,.03); }
        .plan-pick label.sel { border-color:#5eead4; background:rgba(0,191,166,.12); }
        .plan-pick .lbl { font-weight:600; color:#fff; }
        .plan-pick .pr { margin-left:auto; color:#5eead4; font-weight:700; }
        .modal-box { display:none; margin-top:14px; }
        textarea { width:100%; border-radius:10px; border:1px solid rgba(255,255,255,.2); background:rgba(0,0,0,.25); color:#fff; padding:10px; font-family:inherit; }
        .sticky-cta { position:fixed; left:0; right:0; bottom:0; background:rgba(7,20,27,.95); border-top:1px solid rgba(94,234,212,.25); padding:12px 16px; z-index:20; }
        .sticky-cta .inner { max-width:860px; margin:0 auto; display:flex; gap:10px; }
        .sticky-cta .btn { flex:1; }
        .note { color:#8aa3a5; font-size:.8rem; }
        .ft { text-align:center; color:#7f979d; font-size:.78rem; margin-top:26px; }
        @media (max-width:560px) {
            h1 { font-size:1.45rem; }
            .svc .top { flex-direction:column; }
            .svc .tag { white-space:normal; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="brand"><img src="<?= $e($emblem) ?>" alt=""><strong><?= $appName ?></strong></div>
    <span class="pill">Proposta comercial</span>
    <h1><?= $e($proposal['title']) ?></h1>
    <p class="for">Preparada para <strong><?= $e($clientLabel) ?></strong><?php if (!empty($timeline['start'])): ?> · início previsto <?= $e($brDate($timeline['start'])) ?><?php endif; ?></p>

    <div id="result">
    <?php if ($proposal['status'] === 'accepted'): ?>
        <div class="done ok"><div class="ic">✓</div><div>
            <strong>Proposta aceita</strong>
            <p>Recebemos o seu aceite<?= $respondedAt !== '' ? ' em ' . $e($respondedAt) : '' ?><?php if (!empty($proposal['accepted_plan'])): $pl = ['plan_3'=>'Plano 3 meses','plan_6'=>'Plano 6 meses','plan_12'=>'Plano 12 meses']; ?> (<?= $e($pl[$proposal['accepted_plan']] ?? $proposal['accepted_plan']) ?>)<?php endif; ?>. A equipe da <?= $appName ?> vai seguir com os próximos passos.</p>
        </div></div>
    <?php elseif ($proposal['status'] === 'rejected'): ?>
        <div class="done no"><div class="ic">✕</div><div>
            <strong>Proposta recusada</strong>
            <p>Registramos a sua resposta<?= $respondedAt !== '' ? ' em ' . $e($respondedAt) : '' ?>. Obrigado pelo retorno.</p>
        </div></div>
    <?php elseif ($proposal['status'] === 'cancelled'): ?>
        <div class="banner no">Esta proposta foi cancelada.</div>
    <?php endif; ?>
    </div>

    <!-- Resumo de investimento -->
    <div class="card">
        <h2>Investimento</h2>
        <table class="vals">
            <?php if ($hasOnce): ?>
            <tr><td>Pontual (implantação)</td><td class="r"><?= $brl($comm['once']['net']) ?></td></tr>
            <?php endif; ?>
            <?php if ($hasMonthly): ?>
            <tr><td>Mensalidade</td><td class="r"><?= $brl($comm['monthly']['net']) ?>/mês</td></tr>
            <?php endif; ?>
            <?php if ($yearDisc > 0.004): ?>
            <tr class="disc"><td>Desconto comercial aplicado</td><td class="r">- <?= $brl($yearDisc) ?></td></tr>
            <?php endif; ?>
            <tr class="tot"><td><?= $hasMonthly ? 'Total do 1º ano' : 'Total' ?></td><td class="r"><?= $brl($comm['year']['net']) ?></td></tr>
        </table>
        <?php if (!empty($comm['has_schedule'])): ?>
        <p class="note" style="margin-top:10px;">Mensalidade por mês (desconto por período):
            <?php $mb = $comm['monthly_by_month']; $parts = [];
            for ($mi = 0; $mi < 12; $mi++) $parts[] = 'M' . ($mi+1) . ' ' . $brl($mb[$mi]);
            echo $e(implode(' · ', $parts)); ?>.
        </p>
        <?php endif; ?>
        <?php if (!empty($proposal['validity_date'])): ?>
        <p class="note" style="margin-top:8px;">Proposta válida até <?= $e($brDate($proposal['validity_date'])) ?>. Pagamento: <?= $e($vars['condicao_pagamento']) ?>.</p>
        <?php endif; ?>
    </div>

    <?php if ($business): ?>
    <div class="card">
        <h2>Entendimento do negócio</h2>
        <?php foreach ($business as $label => $text): ?>
        <p class="mint" style="margin:12px 0 4px;font-weight:600;"><?= $e($label) ?></p>
        <ul class="lead-list"><?php foreach ($blockLines($text) as $l): ?><li><?= $e($l) ?></li><?php endforeach; ?></ul>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($items): ?>
    <div class="card">
        <h2>Serviços</h2>
        <?php foreach ($items as $it):
            $meta = [];
            if (($it['hours'] ?? null) !== null && (float)$it['hours'] > 0) $meta[] = $num($it['hours']) . 'h';
            if ((float)($it['quantity'] ?? 1) != 1) $meta[] = 'Qtd ' . $num($it['quantity']);
            $meta[] = !empty($it['is_monthly']) ? 'Mensal' : 'Pontual';
            if (!empty($it['is_monthly']) && !empty($it['renewal']) && $it['renewal'] !== 'none' && !empty($it['renewal_label'])) $meta[] = $it['renewal_label'];
            if (!empty($it['schedule_start'])) $meta[] = empty($it['schedule_end']) ? 'a partir de ' . $brDate($it['schedule_start']) : $brDate($it['schedule_start']) . ' a ' . $brDate($it['schedule_end']);
        ?>
        <div class="svc">
            <div class="top">
                <span class="nm"><?= $e($it['description']) ?></span>
                <span class="tag"><?= $brl($it['amount']) ?><?= !empty($it['is_monthly']) ? '/mês' : '' ?></span>
            </div>
            <?php if (!empty($it['scope'])): ?><div class="desc"><?= nl2br($e($it['scope'])) ?></div><?php endif; ?>
            <div class="tag" style="margin-top:4px;"><?= $e(implode(' · ', $meta)) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($timeline['rows']) && !empty($timeline['start'])): ?>
    <div class="card">
        <h2>Cronograma</h2>
        <?php foreach ($timeline['rows'] as $row):
            $ini = $row['start'] ? $brDate($row['start']) : null;
            $fim = !empty($row['ongoing']) ? null : ($row['end'] ? $brDate($row['end']) : null);
            if ($ini && $fim) $when = $ini . ' a ' . $fim;
            elseif ($ini && !empty($row['ongoing'])) $when = 'A partir de ' . $ini;
            elseif ($ini) $when = $ini;
            else $when = 'A definir';
        ?>
        <div class="timeline-item"><span><?= $e($row['description']) ?></span><span class="muted"><?= $e($when) ?></span></div>
        <?php endforeach; ?>
        <p class="note" style="margin-top:10px;">Período de implantação: <?= $e($period) ?>.</p>
    </div>
    <?php endif; ?>

    <?php if ($hasMonthly && $plans): ?>
    <div class="card">
        <h2>Planos</h2>
        <div class="plan-grid">
            <?php foreach ($plans['items'] as $p): ?>
            <div class="plan">
                <h3><?= $e($p['label']) ?></h3>
                <div class="big"><?= $brl($p['monthly']) ?><span class="sm">/mês</span></div>
                <div class="sm">Implantação <?= $brl($plans['once']) ?></div>
                <?php if ($p['save_month'] > 0): ?><div class="sm mint">Economia de <?= $brl($p['save_month']) ?>/mês</div><?php endif; ?>
                <div class="sm">Prazo mínimo <?= (int)$p['months'] ?> meses</div>
            </div>
            <?php endforeach; ?>
        </div>
        <p class="note" style="margin-top:10px;">A implantação é cobrada uma só vez e é igual em todos os planos. Quanto maior o compromisso, menor a mensalidade.</p>
    </div>
    <?php endif; ?>

    <?php $pb = $fixedBlock('payment'); if ($pb): ?>
    <div class="card">
        <h2><?= $e($pb['title'] !== '' ? $pb['title'] : 'Condições de pagamento e rescisão') ?></h2>
        <?php foreach (preg_split('/\r?\n\s*\r?\n/', trim($pb['body'])) as $chunk):
            $ls = $blockLines($chunk);
            if (!$ls) continue;
            if (preg_match('/^([A-Z])\s+(.{2,80})$/u', $ls[0], $mm)) { array_shift($ls); $t = $mm[2]; }
            else $t = '';
        ?>
            <?php if ($t !== ''): ?><p class="mint" style="margin:12px 0 4px;font-weight:600;"><?= $e($t) ?></p><?php endif; ?>
            <ul class="lead-list"><?php foreach ($ls as $l): ?><li><?= $e($l) ?></li><?php endforeach; ?></ul>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php $nb = $fixedBlock('notes'); if ($nb): ?>
    <div class="card">
        <h2><?= $e($nb['title'] !== '' ? $nb['title'] : 'Notas e condições') ?></h2>
        <?php foreach (preg_split('/\r?\n\s*\r?\n/', trim($nb['body'])) as $chunk):
            $ls = $blockLines($chunk);
            if (!$ls) continue;
            if (preg_match('/^([A-Z])\s+(.{2,80})$/u', $ls[0], $mm)) { array_shift($ls); $t = $mm[2]; }
            else $t = '';
        ?>
            <?php if ($t !== ''): ?><p class="mint" style="margin:12px 0 4px;font-weight:600;"><?= $e($t) ?></p><?php endif; ?>
            <ul class="lead-list"><?php foreach ($ls as $l): ?><li><?= $e($l) ?></li><?php endforeach; ?></ul>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- PDF + aceite -->
    <div class="card" id="respond-card">
        <h2>Documento e resposta</h2>
        <a class="btn btn-pdf" style="width:100%;" href="<?= $e($pdfLink) ?>" target="_blank" rel="noopener">📄 Ver / baixar a proposta em PDF</a>

        <?php if (!$isTerminal): ?>
        <div id="respond-box" style="margin-top:16px;">
            <p class="muted" style="margin:0 0 6px;">O aceite confirma o escopo, o investimento e o cronograma desta proposta.</p>

            <?php if (!empty($planOptions)): ?>
            <p style="font-weight:600;color:#fff;margin:10px 0 4px;">Escolha o plano que deseja aceitar:</p>
            <div class="plan-pick" id="plan-pick">
                <?php foreach ($planOptions as $i => $opt): ?>
                <label data-plan="<?= $e($opt['key']) ?>" class="<?= $i === count($planOptions)-1 ? 'sel' : '' ?>">
                    <input type="radio" name="plan" value="<?= $e($opt['key']) ?>" <?= $i === count($planOptions)-1 ? 'checked' : '' ?>>
                    <span class="lbl"><?= $e($opt['label']) ?></span>
                    <span class="pr"><?= $brl($opt['monthly']) ?>/mês</span>
                </label>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="actions">
                <button class="btn btn-ok" onclick="accept(this)">Aceitar proposta</button>
                <button class="btn btn-alt" onclick="toggle('alt-box')">Solicitar alteração</button>
                <button class="btn btn-no" onclick="toggle('reject-box')">Recusar</button>
            </div>

            <div id="alt-box" class="modal-box">
                <label>O que você gostaria de alterar?</label>
                <textarea id="alt-message" rows="3" placeholder="Descreva o ajuste (valor, prazo, escopo...)"></textarea>
                <div class="actions"><button class="btn btn-alt" onclick="requestChange(this)">Enviar solicitação</button></div>
            </div>
            <div id="reject-box" class="modal-box">
                <label>Informe o motivo da recusa</label>
                <textarea id="reject-reason" rows="3" placeholder="Ex.: valor, prazo ou escopo"></textarea>
                <div class="actions"><button class="btn btn-no" onclick="reject(this)">Confirmar recusa</button></div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="ft"><?= $appName ?> · Documento confidencial</div>
</div>

<?php if (!$isTerminal): ?>
<div class="sticky-cta" id="sticky">
    <div class="inner">
        <a class="btn btn-pdf" href="<?= $e($pdfLink) ?>" target="_blank" rel="noopener">Ver PDF</a>
        <button class="btn btn-ok" onclick="document.getElementById('respond-card').scrollIntoView({behavior:'smooth'});">Aceitar proposta</button>
    </div>
</div>
<?php endif; ?>

<script>
    const BASE = '<?= rtrim(baseUrl(''), '/') ?>';
    const TOKEN = '<?= $token ?>';
    const BRAND = '<?= $appName ?>';
    const HAS_PLANS = <?= !empty($planOptions) ? 'true' : 'false' ?>;

    document.querySelectorAll('#plan-pick label').forEach(l => {
        l.addEventListener('click', () => {
            document.querySelectorAll('#plan-pick label').forEach(x => x.classList.remove('sel'));
            l.classList.add('sel');
            l.querySelector('input').checked = true;
        });
    });
    function toggle(id) {
        const el = document.getElementById(id);
        el.style.display = el.style.display === 'block' ? 'none' : 'block';
    }
    function chosenPlan() {
        const r = document.querySelector('#plan-pick input[name="plan"]:checked');
        return r ? r.value : '';
    }
    function hideActions() {
        const box = document.getElementById('respond-box'); if (box) box.remove();
        const st = document.getElementById('sticky'); if (st) st.remove();
    }
    function showResult(kind, when, planLabel) {
        hideActions();
        let title, text, cls, ic;
        if (kind === 'ok') { title='Proposta aceita'; cls='ok'; ic='✓'; text=`Recebemos o seu aceite${when ? ' em ' + when : ''}${planLabel ? ' ('+planLabel+')' : ''}. A equipe da ${BRAND} vai seguir com os próximos passos.`; }
        else { title='Proposta recusada'; cls='no'; ic='✕'; text=`Registramos a sua resposta${when ? ' em ' + when : ''}. Obrigado pelo retorno.`; }
        document.getElementById('result').innerHTML =
            `<div class="done ${cls}"><div class="ic">${ic}</div><div><strong>${title}</strong><p>${text}</p></div></div>`;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    async function send(url, body, btn) {
        btn.disabled = true; const old = btn.textContent; btn.textContent = 'Enviando...';
        const r = await fetch(url, { method:'POST', body }).then(x => x.json()).catch(() => ({ error: 'Falha de rede. Tente novamente.' }));
        if (r.error) { alert(r.error); btn.disabled = false; btn.textContent = old; return null; }
        return r;
    }
    async function accept(btn) {
        let planLabel = '';
        const fd = new URLSearchParams();
        if (HAS_PLANS) {
            const plan = chosenPlan();
            if (!plan) { alert('Selecione o plano que deseja aceitar.'); return; }
            fd.append('plan', plan);
            const lab = document.querySelector(`#plan-pick label[data-plan="${plan}"] .lbl`);
            planLabel = lab ? lab.textContent : '';
        }
        if (!confirm('Confirmar o aceite desta proposta' + (planLabel ? ' — ' + planLabel : '') + '?')) return;
        const r = await send(`${BASE}/proposal/accept/${TOKEN}`, fd, btn);
        if (r) showResult('ok', r.responded_at || '', planLabel);
    }
    async function reject(btn) {
        const reason = document.getElementById('reject-reason').value.trim();
        if (!reason) { alert('Informe o motivo da recusa.'); return; }
        const fd = new URLSearchParams(); fd.append('reason', reason);
        const r = await send(`${BASE}/proposal/reject/${TOKEN}`, fd, btn);
        if (r) showResult('no', '');
    }
    async function requestChange(btn) {
        const message = document.getElementById('alt-message').value.trim();
        if (!message) { alert('Descreva o que você gostaria de alterar.'); return; }
        const fd = new URLSearchParams(); fd.append('message', message);
        const r = await send(`${BASE}/proposal/change/${TOKEN}`, fd, btn);
        if (r) {
            document.getElementById('alt-box').innerHTML = '<p class="mint" style="margin:0;">✓ Solicitação enviada. A equipe vai avaliar e retornar com uma proposta ajustada.</p>';
        }
    }
</script>
</body>
</html>
