<?php
$baseUrl = rtrim(baseUrl(''), '/');
$token = htmlspecialchars($link['token'], ENT_QUOTES);
$title = htmlspecialchars($link['title'] ?: 'Agende sua reunião', ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <meta name="theme-color" content="#00BFA6">
    <title>Agendar reunião · ON Solutions Brasil</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root { --brand:#00BFA6; --brand-dark:#009e88; --ink:#1f2d3d; --muted:#6b7a90; }
        * { -webkit-tap-highlight-color: transparent; }
        body { background:#eef1f6; font-family:'Segoe UI',system-ui,Arial,sans-serif; color:var(--ink); }

        .booking-card { max-width:640px; margin:24px auto; background:#fff; border-radius:20px;
            box-shadow:0 10px 40px rgba(16,42,67,.12); overflow:hidden; }

        .booking-hd { position:relative; background:linear-gradient(135deg,#00d4b8,#009e88); color:#fff; padding:28px 26px 26px; }
        .booking-hd::after { content:''; position:absolute; right:-40px; top:-40px; width:160px; height:160px;
            background:rgba(255,255,255,.12); border-radius:50%; }
        .brand { display:inline-flex; align-items:center; gap:8px; font-weight:600; font-size:.82rem;
            letter-spacing:.4px; background:rgba(255,255,255,.18); padding:5px 12px; border-radius:999px; }
        .booking-hd h1 { font-size:1.45rem; font-weight:700; margin:14px 0 6px; line-height:1.25; }
        .booking-hd .sub { opacity:.95; font-size:.9rem; display:flex; align-items:center; gap:6px; }

        .booking-bd { padding:22px 26px 26px; }

        .section { margin-bottom:22px; }
        .section-hd { display:flex; align-items:center; gap:10px; margin-bottom:12px; }
        .section-num { flex:0 0 auto; width:26px; height:26px; border-radius:50%; background:var(--brand);
            color:#fff; font-size:.8rem; font-weight:700; display:flex; align-items:center; justify-content:center; }
        .section-title { font-size:.82rem; font-weight:700; color:var(--ink); text-transform:uppercase; letter-spacing:.6px; }

        .form-label { font-size:.78rem; font-weight:600; color:var(--muted); margin-bottom:4px; }
        .form-control { border-radius:12px; border:1.5px solid #e2e8f0; padding:11px 14px; font-size:.95rem; }
        .form-control:focus { border-color:var(--brand); box-shadow:0 0 0 3px rgba(0,191,166,.15); }
        .form-control:disabled { background:#f4f6f9; }

        .slot-group-label { font-size:.72rem; font-weight:700; color:var(--muted); text-transform:uppercase;
            letter-spacing:.5px; margin:14px 0 8px; display:flex; align-items:center; gap:6px; }
        .slot-group-label:first-child { margin-top:0; }
        .slots-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(74px,1fr)); gap:8px; }
        .slot-btn { border:1.5px solid #dbe3ec; background:#fff; color:var(--ink); border-radius:12px;
            padding:11px 4px; font-size:.92rem; font-weight:600; cursor:pointer; transition:all .12s; text-align:center; }
        .slot-btn:hover { border-color:var(--brand); color:var(--brand-dark); }
        .slot-btn.active { background:var(--brand); color:#fff; border-color:var(--brand); box-shadow:0 4px 12px rgba(0,191,166,.35); }

        .hint { display:flex; align-items:flex-start; gap:8px; background:#f0fbf9; border:1px solid #cbeee7;
            color:#0b7d6d; border-radius:12px; padding:10px 12px; font-size:.8rem; }

        .btn-confirm { background:var(--brand); border:none; color:#fff; border-radius:14px; padding:14px;
            font-size:1rem; font-weight:700; width:100%; box-shadow:0 6px 18px rgba(0,191,166,.35); transition:filter .12s; }
        .btn-confirm:hover:not(:disabled) { filter:brightness(1.05); }
        .btn-confirm:disabled { background:#c3ccd6; box-shadow:none; cursor:not-allowed; }

        .placeholder-msg { color:var(--muted); font-size:.88rem; padding:8px 2px; }

        /* Rodapé fixo com o botão no mobile */
        @media (max-width:575.98px) {
            body { background:#fff; }
            .booking-card { margin:0; border-radius:0; box-shadow:none; min-height:100vh; padding-bottom:88px; }
            .booking-hd { border-radius:0; padding:24px 18px; }
            .booking-hd h1 { font-size:1.25rem; }
            .booking-bd { padding:20px 18px 20px; }
            .confirm-wrap { position:fixed; left:0; right:0; bottom:0; background:#fff; padding:12px 16px calc(12px + env(safe-area-inset-bottom));
                box-shadow:0 -4px 20px rgba(0,0,0,.08); z-index:50; }
            .confirm-note { display:none; }
            .slots-grid { grid-template-columns:repeat(auto-fill,minmax(72px,1fr)); }
        }
    </style>
</head>
<body>
<div class="booking-card">
    <div class="booking-hd">
        <span class="brand"><i class="bi bi-calendar2-check"></i> ON Solutions Brasil</span>
        <h1><?= $title ?></h1>
        <div class="sub"><i class="bi bi-camera-video"></i> Reunião online via Google Meet</div>
    </div>
    <div class="booking-bd">
        <div id="alert-box"></div>

        <div class="section">
            <div class="section-hd"><span class="section-num">1</span><span class="section-title">Seus dados</span></div>
            <div class="row g-3">
                <div class="col-12 col-sm-6">
                    <label class="form-label">Nome</label>
                    <input type="text" id="bk-name" class="form-control" value="<?= htmlspecialchars($prefName, ENT_QUOTES) ?>" autocomplete="name">
                </div>
                <div class="col-12 col-sm-6">
                    <label class="form-label">E-mail</label>
                    <input type="email" id="bk-email" class="form-control" value="<?= htmlspecialchars($prefEmail, ENT_QUOTES) ?>" autocomplete="email" inputmode="email">
                </div>
                <div class="col-12 col-sm-6">
                    <label class="form-label">Telefone / WhatsApp</label>
                    <input type="tel" id="bk-phone" class="form-control" value="<?= htmlspecialchars($prefPhone, ENT_QUOTES) ?>" autocomplete="tel" inputmode="tel">
                </div>
                <?php if (!empty($company)): ?>
                <div class="col-12 col-sm-6">
                    <label class="form-label">Empresa</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($company, ENT_QUOTES) ?>" disabled>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="section">
            <div class="section-hd"><span class="section-num">2</span><span class="section-title">Data</span></div>
            <input type="date" id="bk-date" class="form-control" min="<?= htmlspecialchars($minDate ?? date('Y-m-d'), ENT_QUOTES) ?>" value="<?= htmlspecialchars($minDate ?? date('Y-m-d'), ENT_QUOTES) ?>">
        </div>

        <div class="section">
            <div class="section-hd"><span class="section-num">3</span><span class="section-title">Horário</span></div>
            <div id="slots"><div class="placeholder-msg"><i class="bi bi-clock"></i> Selecione uma data para ver os horários.</div></div>
        </div>

        <div class="hint mb-3">
            <i class="bi bi-info-circle-fill"></i>
            <span>Após confirmar, você recebe o link da reunião e um lembrete por e-mail e WhatsApp.</span>
        </div>

        <div class="confirm-wrap">
            <button class="btn-confirm" id="bk-confirm" onclick="confirmBooking()" disabled>
                <i class="bi bi-check-lg"></i> Confirmar agendamento
            </button>
        </div>
    </div>
</div>

<script>
const BASE = '<?= $baseUrl ?>';
const TOKEN = '<?= $token ?>';
let selectedTime = null;

function alertBox(type, msg) {
    document.getElementById('alert-box').innerHTML =
        `<div class="alert alert-${type} py-2 px-3 small rounded-3">${msg}</div>`;
    document.getElementById('alert-box').scrollIntoView({behavior:'smooth', block:'nearest'});
}

function loadSlots() {
    selectedTime = null;
    document.getElementById('bk-confirm').disabled = true;
    const date = document.getElementById('bk-date').value;
    const box = document.getElementById('slots');
    box.innerHTML = '<div class="placeholder-msg"><span class="spinner-border spinner-border-sm"></span> Carregando horários…</div>';
    fetch(`${BASE}/booking/slots/${TOKEN}?date=${date}`)
        .then(r => r.json())
        .then(d => {
            const slots = d.slots || [];
            if (!slots.length) { box.innerHTML = '<div class="placeholder-msg"><i class="bi bi-calendar-x"></i> Nenhum horário disponível nesta data. Tente outro dia.</div>'; return; }
            renderSlots(box, slots);
        })
        .catch(() => { box.innerHTML = '<div class="placeholder-msg text-danger"><i class="bi bi-exclamation-triangle"></i> Erro ao carregar horários.</div>'; });
}

// Agrupa os horários por período (manhã / tarde / noite) para facilitar a escolha
function renderSlots(box, slots) {
    const groups = { manha: [], tarde: [], noite: [] };
    slots.forEach(s => {
        const h = parseInt(s.split(':')[0], 10);
        if (h < 12) groups.manha.push(s);
        else if (h < 18) groups.tarde.push(s);
        else groups.noite.push(s);
    });
    const labels = { manha: ['bi-sunrise', 'Manhã'], tarde: ['bi-sun', 'Tarde'], noite: ['bi-moon-stars', 'Noite'] };
    let html = '';
    ['manha','tarde','noite'].forEach(g => {
        if (!groups[g].length) return;
        html += `<div class="slot-group-label"><i class="bi ${labels[g][0]}"></i> ${labels[g][1]}</div>`;
        html += '<div class="slots-grid">' + groups[g].map(s =>
            `<button type="button" class="slot-btn" onclick="pickSlot(this,'${s}')">${s}</button>`
        ).join('') + '</div>';
    });
    box.innerHTML = html;
}

function pickSlot(btn, time) {
    document.querySelectorAll('.slot-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    selectedTime = time;
    document.getElementById('bk-confirm').disabled = false;
}

function confirmBooking() {
    const name = document.getElementById('bk-name').value.trim();
    if (!name) { alertBox('warning', 'Informe seu nome.'); return; }
    if (!selectedTime) { alertBox('warning', 'Escolha um horário.'); return; }

    const btn = document.getElementById('bk-confirm');
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Confirmando…';

    const fd = new FormData();
    fd.append('date', document.getElementById('bk-date').value);
    fd.append('time', selectedTime);
    fd.append('name', name);
    fd.append('email', document.getElementById('bk-email').value.trim());
    fd.append('phone', document.getElementById('bk-phone').value.trim());

    fetch(`${BASE}/booking/confirm/${TOKEN}`, { method:'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.error) { alertBox('danger', d.error); btn.disabled=false; btn.innerHTML='<i class="bi bi-check-lg"></i> Confirmar agendamento'; return; }
            const meet = d.meet_link ? `<a href="${d.meet_link}" class="btn-confirm d-inline-block mt-3" style="width:auto;padding:12px 24px;text-decoration:none;" target="_blank"><i class="bi bi-camera-video"></i> Entrar na reunião</a>` : '';
            const wrap = document.querySelector('.confirm-wrap'); if (wrap) wrap.remove();
            document.querySelector('.booking-bd').innerHTML =
                `<div class="text-center py-5">
                    <i class="bi bi-check-circle-fill text-success" style="font-size:3.4rem;"></i>
                    <h4 class="mt-3 mb-2" style="font-weight:700;">Reunião confirmada!</h4>
                    <p class="text-muted mb-1">Enviamos os detalhes e o link por e-mail e WhatsApp.</p>
                    ${meet}
                </div>`;
        })
        .catch(() => { alertBox('danger', 'Erro ao confirmar. Tente novamente.'); btn.disabled=false; btn.innerHTML='<i class="bi bi-check-lg"></i> Confirmar agendamento'; });
}

document.getElementById('bk-date').addEventListener('change', loadSlots);
loadSlots();
</script>
</body>
</html>
