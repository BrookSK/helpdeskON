<?php
$appName = htmlspecialchars((string) Config::get('app_name') ?: 'ON Solutions', ENT_QUOTES);
$logo = Config::get('app_logo');
$emblem = asset('on-emblem.png') . '?v=2';
$token = htmlspecialchars($proposal['public_token'], ENT_QUOTES);
$total = (float)$proposal['total'];
$isDraft = in_array($proposal['status'], ['draft', 'ready'], true);

$brl = function ($v) {
    return 'R$ ' . number_format((float)$v, 2, ',', '.');
};
$fmtNum = function ($v) {
    if ($v === null || $v === '') return '—';
    $s = number_format((float)$v, 2, ',', '.');
    return rtrim(rtrim($s, '0'), ',');
};
$brDate = function ($d) {
    if (!$d) return '';
    $t = strtotime((string)$d);
    return $t ? date('d/m/Y', $t) : '';
};
$para = function ($text) {
    $safe = htmlspecialchars((string)$text, ENT_QUOTES);
    return nl2br($safe);
};

$before = [];
$after = [];
foreach ($blocks as $b) {
    if (($b['placement'] ?? 'before') === 'after') $after[] = $b;
    else $before[] = $b;
}

$unitOf = function (array $it) use ($fmtNum) {
    $qty = (float)($it['quantity'] ?? 1);
    if ($qty <= 0) $qty = 1;
    $hours = ($it['hours'] !== null && $it['hours'] !== '') ? (float)$it['hours'] : 0;
    $rate = ($it['hourly_rate'] !== null && $it['hourly_rate'] !== '') ? (float)$it['hourly_rate'] : 0;
    if ($hours > 0 && $rate > 0) return [$rate, true];
    return [((float)$it['amount']) / $qty, false];
};
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= htmlspecialchars($proposal['title'], ENT_QUOTES) ?> · Proposta</title>
    <?php if ($fav = Config::get('app_favicon')): ?><link rel="icon" href="<?= baseUrl($fav) ?>"><?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg:#07141b; --teal:#00bfa6; --mint:#5eead4; --ink:#0b1a24; --text:#3d5158;
            --muted:#6b7d84; --line:#e3ecec; --paper:#eef3f3; --track:#e7f4f2;
        }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--paper); color:var(--ink); font-family:'Inter',system-ui,sans-serif; line-height:1.6; }
        .doc { max-width:920px; margin:0 auto; padding:28px 16px 72px; }
        .draft { background:#fff6d8; color:#7a5b00; text-align:center; padding:10px 16px; font-size:.88rem; border-radius:12px; margin-bottom:14px; }
        .sheet { background:#fff; border-radius:18px; overflow:hidden; box-shadow:0 18px 50px rgba(7,20,27,.08); margin-bottom:16px; }
        .cover { position:relative; min-height:560px; padding:52px 52px 40px; color:#fff; display:flex; flex-direction:column;
            background:
                radial-gradient(ellipse 55% 45% at 50% 0%, rgba(94,234,212,.16), transparent 70%),
                radial-gradient(ellipse 60% 50% at 100% 100%, rgba(17,140,118,.45), transparent 70%),
                linear-gradient(165deg, #07141b 0%, #0b1f27 42%, #0e3d38 100%); }
        .cover::before { content:''; position:absolute; inset:0; pointer-events:none;
            background-image:linear-gradient(rgba(94,234,212,.05) 1px, transparent 1px), linear-gradient(90deg, rgba(94,234,212,.05) 1px, transparent 1px);
            background-size:56px 56px; mask-image:radial-gradient(ellipse 80% 70% at 50% 40%, #000, transparent 80%); }
        .cover > * { position:relative; }
        .brand { display:flex; align-items:center; gap:14px; }
        .brand img.emblem { width:52px; height:auto; filter:drop-shadow(0 0 18px rgba(0,191,166,.45)); }
        .brand img.logo { max-height:42px; }
        .brand strong { font-size:1.05rem; letter-spacing:-.02em; }
        .kicker { display:inline-block; margin:36px 0 16px; padding:7px 14px; border:1px solid rgba(94,234,212,.35); border-radius:999px;
            font-family:'JetBrains Mono',monospace; font-size:.75rem; letter-spacing:.14em; text-transform:uppercase; color:var(--mint); }
        .cover h1 { margin:0; font-size:clamp(2rem, 4.4vw, 3.1rem); line-height:1.08; font-weight:800; letter-spacing:-.035em; max-width:16ch; }
        .for { margin-top:auto; padding-top:36px; display:flex; justify-content:space-between; gap:24px; flex-wrap:wrap; align-items:flex-end; }
        .for .who { font-size:.78rem; letter-spacing:.12em; text-transform:uppercase; color:var(--mint); font-family:'JetBrains Mono',monospace; }
        .for .name { font-size:1.35rem; font-weight:700; margin-top:4px; }
        .for .contact { color:#c5d6da; font-size:.92rem; }
        .meta { display:flex; gap:22px; flex-wrap:wrap; font-size:.88rem; color:#d5e4e6; }
        .meta b { display:block; font-size:.68rem; letter-spacing:.12em; text-transform:uppercase; color:var(--mint); font-weight:600; margin-bottom:2px; }
        .bd { padding:42px 48px 36px; }
        .part { display:inline-flex; align-items:center; gap:10px; margin-bottom:14px; font-family:'JetBrains Mono',monospace; font-size:.72rem; font-weight:700; letter-spacing:.16em; text-transform:uppercase; color:#0a8f7d; }
        .part::before { content:''; width:28px; height:2px; background:linear-gradient(90deg, var(--teal), var(--mint)); }
        .bd h2 { margin:0 0 14px; font-size:1.7rem; letter-spacing:-.03em; line-height:1.15; }
        .prose { color:var(--text); font-size:1.02rem; max-width:68ch; }
        .prose p { margin:0 0 12px; }
        .svc { border:1px solid var(--line); border-radius:14px; padding:18px 20px; margin-top:12px; border-left:3px solid var(--teal); }
        .svc strong { display:block; font-size:1.05rem; margin-bottom:4px; }
        .svc p { margin:0; color:var(--text); white-space:pre-wrap; }
        table { width:100%; border-collapse:collapse; margin-top:8px; }
        th { text-align:left; padding:12px 10px; font-family:'JetBrains Mono',monospace; font-size:.68rem; letter-spacing:.1em; text-transform:uppercase; color:#0a8f7d; border-bottom:1px solid rgba(0,191,166,.35); }
        td { padding:14px 10px; border-top:1px solid var(--line); vertical-align:top; }
        td.num, th.num { text-align:right; white-space:nowrap; }
        tfoot td { font-weight:800; font-size:1.05rem; border-top:2px solid var(--ink); }
        .period { margin:0 0 18px; color:var(--text); }
        .tl { display:grid; grid-template-columns:minmax(140px, 220px) 1fr; gap:8px 16px; align-items:center; margin-top:8px; }
        .tl .nm { font-weight:600; font-size:.92rem; }
        .tl .when { display:block; font-weight:400; color:var(--muted); font-size:.78rem; }
        .track { position:relative; height:14px; border-radius:999px; background:var(--track); }
        .bar { position:absolute; top:0; bottom:0; border-radius:999px; background:linear-gradient(90deg, #00bfa6, #5eead4); }
        .pending { color:var(--muted); font-size:.85rem; }
        .actions { display:flex; gap:10px; flex-wrap:wrap; margin-top:8px; }
        .btn { border:none; border-radius:10px; padding:12px 18px; font-size:.95rem; cursor:pointer; font-family:inherit; font-weight:600; }
        .btn-ok { background:#00bfa6; color:#fff; }
        .btn-no { background:#fff; color:#b4332a; border:1px solid #b4332a; }
        .banner { padding:12px 14px; border-radius:10px; margin-bottom:16px; }
        .banner.ok { background:#e7f8f4; color:#0b8c7a; }
        .banner.no { background:#fdecec; color:#b4332a; }
        #reject-box { display:none; margin-top:14px; }
        textarea { width:100%; border:1px solid #cfd8da; border-radius:10px; padding:10px; font-family:inherit; }
        .ft { text-align:center; color:var(--muted); font-size:.78rem; padding:8px 0 0; }
        @media (max-width:720px) {
            .cover, .bd { padding:28px 22px; }
            .cover { min-height:0; }
            .tl { grid-template-columns:1fr; }
            th:nth-child(2), td:nth-child(2), th:nth-child(3), td:nth-child(3) { display:none; }
        }
        @media print {
            body { background:#fff; }
            .doc { max-width:none; padding:0; }
            .sheet { box-shadow:none; break-inside:avoid; }
            .actions, #reject-box, .draft { display:none; }
        }
    </style>
</head>
<body>
<div class="doc">
    <?php if ($isDraft): ?>
    <div class="draft">Rascunho interno. O cliente vê este mesmo link quando a proposta é enviada.</div>
    <?php endif; ?>

    <section class="sheet cover">
        <div class="brand">
            <img class="emblem" src="<?= htmlspecialchars($emblem, ENT_QUOTES) ?>" alt="">
            <?php if ($logo): ?>
                <img class="logo" src="<?= baseUrl($logo) ?>" alt="<?= $appName ?>">
            <?php else: ?>
                <strong><?= $appName ?></strong>
            <?php endif; ?>
        </div>
        <div class="kicker">Proposta comercial</div>
        <h1><?= htmlspecialchars($proposal['title'], ENT_QUOTES) ?></h1>
        <div class="for">
            <div>
                <?php if (!empty($proposal['client_name'])): ?>
                <div class="who">Preparado para</div>
                <div class="name"><?= htmlspecialchars($proposal['client_name'], ENT_QUOTES) ?></div>
                <div class="contact">
                    <?= htmlspecialchars(trim((string)($proposal['client_email'] ?? '')), ENT_QUOTES) ?>
                    <?php if (!empty($proposal['client_email']) && !empty($proposal['client_phone'])): ?> · <?php endif; ?>
                    <?= htmlspecialchars(trim((string)($proposal['client_phone'] ?? '')), ENT_QUOTES) ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="meta">
                <div><b>Emitida</b><?= $brDate($proposal['created_at'] ?? date('Y-m-d')) ?></div>
                <?php if (!empty($proposal['validity_date'])): ?><div><b>Válida até</b><?= $brDate($proposal['validity_date']) ?></div><?php endif; ?>
                <?php if (!empty($timeline['start']) && !empty($timeline['end'])): ?>
                <div><b>Desenvolvimento</b><?= $brDate($timeline['start']) ?> — <?= $brDate($timeline['end']) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php foreach ($before as $b): ?>
    <section class="sheet"><div class="bd">
        <div class="part">A proposta</div>
        <h2><?= htmlspecialchars($b['title'], ENT_QUOTES) ?></h2>
        <div class="prose"><?= $para($b['body'] ?? '') ?></div>
    </div></section>
    <?php endforeach; ?>

    <section class="sheet"><div class="bd">
        <div class="part">Escopo</div>
        <h2>Serviços desta contratação</h2>
        <?php if (empty($items)): ?>
            <p class="pending">Nenhum serviço selecionado.</p>
        <?php else: foreach ($items as $it): ?>
            <article class="svc">
                <strong><?= htmlspecialchars($it['description'], ENT_QUOTES) ?></strong>
                <?php if (!empty($it['scope'])): ?><p><?= $para($it['scope']) ?></p><?php endif; ?>
            </article>
        <?php endforeach; endif; ?>
    </div></section>

    <section class="sheet"><div class="bd">
        <div class="part">Investimento</div>
        <h2>Valores</h2>
        <table>
            <thead>
                <tr>
                    <th>Serviço</th>
                    <th class="num">Qtd</th>
                    <th class="num">Horas</th>
                    <th class="num">Valor</th>
                    <th class="num">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                <tr><td colspan="5" class="pending">Sem itens.</td></tr>
                <?php else: foreach ($items as $it):
                    [$unit, $perHour] = $unitOf($it);
                ?>
                <tr>
                    <td><?= htmlspecialchars($it['description'], ENT_QUOTES) ?></td>
                    <td class="num"><?= $fmtNum($it['quantity'] ?? 1) ?></td>
                    <td class="num"><?= $fmtNum($it['hours']) ?></td>
                    <td class="num"><?= $brl($unit) ?><?= $perHour ? '/h' : '' ?></td>
                    <td class="num"><?= $brl($it['amount']) ?></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
            <tfoot>
                <tr><td colspan="4">Total</td><td class="num"><?= $brl($total) ?></td></tr>
            </tfoot>
        </table>
        <?php if (!empty($proposal['observations'])): ?>
        <div class="prose" style="margin-top:18px;"><?= $para($proposal['observations']) ?></div>
        <?php endif; ?>
    </div></section>

    <section class="sheet"><div class="bd">
        <div class="part">Cronograma</div>
        <h2>Período de desenvolvimento</h2>
        <?php if (!empty($timeline['start']) && !empty($timeline['end'])): ?>
        <p class="period"><?= $brDate($timeline['start']) ?> a <?= $brDate($timeline['end']) ?><?php if (($timeline['days'] ?? 0) > 0): ?> · <?= (int)$timeline['days'] ?> dias<?php endif; ?></p>
        <?php endif; ?>
        <?php if (empty($timeline['rows'])): ?>
            <p class="pending">O cronograma é definido por serviço, junto com o escopo.</p>
        <?php else: ?>
        <div class="tl">
            <?php foreach ($timeline['rows'] as $row): ?>
            <div>
                <span class="nm"><?= htmlspecialchars($row['description'], ENT_QUOTES) ?></span>
                <span class="when">
                    <?php if (!empty($row['start']) || !empty($row['end'])): ?>
                        <?= $brDate($row['start'] ?: $row['end']) ?><?php if (!empty($row['end']) && $row['end'] !== $row['start']): ?> — <?= $brDate($row['end']) ?><?php endif; ?>
                    <?php else: ?>
                        A definir
                    <?php endif; ?>
                </span>
            </div>
            <div>
                <?php if (!empty($row['bar'])): ?>
                <div class="track"><div class="bar" style="left:<?= (float)$row['bar']['left'] ?>%;width:<?= (float)$row['bar']['width'] ?>%;"></div></div>
                <?php else: ?>
                <span class="pending">Sem datas neste serviço</span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div></section>

    <?php foreach ($after as $b): ?>
    <section class="sheet"><div class="bd">
        <div class="part">Condições</div>
        <h2><?= htmlspecialchars($b['title'], ENT_QUOTES) ?></h2>
        <div class="prose"><?= $para($b['body'] ?? '') ?></div>
    </div></section>
    <?php endforeach; ?>

    <section class="sheet"><div class="bd">
        <?php if ($proposal['status'] === 'accepted'): ?>
            <div class="banner ok">Você aceitou esta proposta. Obrigado. Em breve damos sequência.</div>
        <?php elseif ($proposal['status'] === 'rejected'): ?>
            <div class="banner no">Esta proposta foi recusada.</div>
        <?php endif; ?>

        <?php if (!$isTerminal): ?>
        <div class="part">Aceite</div>
        <h2>Confirmar esta proposta</h2>
        <p class="prose">O aceite vale para o escopo, o investimento e o cronograma apresentados neste link.</p>
        <div class="actions">
            <button class="btn btn-ok" onclick="accept()">Aceitar proposta</button>
            <button class="btn btn-no" onclick="document.getElementById('reject-box').style.display='block'">Recusar</button>
        </div>
        <div id="reject-box">
            <label>Informe o motivo da recusa</label>
            <textarea id="reject-reason" rows="3" placeholder="Ex.: valor, prazo ou escopo"></textarea>
            <div class="actions"><button class="btn btn-no" onclick="reject()">Confirmar recusa</button></div>
        </div>
        <?php endif; ?>
    </div></section>

    <div class="ft"><?= $appName ?> · proposta comercial</div>
</div>
<script>
    const BASE = '<?= rtrim(baseUrl(''), '/') ?>';
    const TOKEN = '<?= $token ?>';
    async function accept() {
        if (!confirm('Confirmar o aceite desta proposta?')) return;
        const r = await fetch(`${BASE}/proposal/accept/${TOKEN}`, { method:'POST' }).then(x=>x.json()).catch(()=>({error:'Falha de rede'}));
        if (r.error) { alert(r.error); return; }
        location.reload();
    }
    async function reject() {
        const reason = document.getElementById('reject-reason').value.trim();
        if (!reason) { alert('Informe o motivo da recusa.'); return; }
        const fd = new URLSearchParams(); fd.append('reason', reason);
        const r = await fetch(`${BASE}/proposal/reject/${TOKEN}`, { method:'POST', body: fd }).then(x=>x.json()).catch(()=>({error:'Falha de rede'}));
        if (r.error) { alert(r.error); return; }
        location.reload();
    }
</script>
</body>
</html>
