<?php
$baseUrl = rtrim(baseUrl(''), '/');
$token = htmlspecialchars($link['token'], ENT_QUOTES);
$title = htmlspecialchars($link['title'] ?: 'Agende sua reunião', ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agendar reunião · ON Solutions Brasil</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background:#f5f7fa; font-family:'Segoe UI',Arial,sans-serif; }
        .booking-card { max-width:640px; margin:32px auto; background:#fff; border-radius:14px; box-shadow:0 4px 24px rgba(0,0,0,.08); overflow:hidden; }
        .booking-hd { background:linear-gradient(135deg,#00BFA6,#009e88); color:#fff; padding:24px 28px; }
        .booking-bd { padding:24px 28px; }
        .brand { font-weight:700; letter-spacing:.5px; }
        .slot-btn { min-width:78px; }
        .slot-btn.active { background:#00BFA6; color:#fff; border-color:#00BFA6; }
        .step-label { font-size:.8rem; font-weight:600; color:#667; text-transform:uppercase; letter-spacing:.5px; }
    </style>
</head>
<body>
<div class="booking-card">
    <div class="booking-hd">
        <div class="brand"><i class="bi bi-calendar2-check"></i> ON Solutions Brasil</div>
        <h4 class="mb-1 mt-2"><?= $title ?></h4>
        <div style="opacity:.9;font-size:.9rem;">Escolha o melhor dia e horário. A reunião é online (Google Meet).</div>
    </div>
    <div class="booking-bd">
        <div id="alert-box"></div>

        <div class="mb-3">
            <div class="step-label mb-1">1 · Seus dados</div>
            <div class="row g-2">
                <div class="col-md-6">
                    <label class="form-label small">Nome</label>
                    <input type="text" id="bk-name" class="form-control form-control-sm" value="<?= htmlspecialchars($prefName, ENT_QUOTES) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label small">E-mail</label>
                    <input type="email" id="bk-email" class="form-control form-control-sm" value="<?= htmlspecialchars($prefEmail, ENT_QUOTES) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label small">Telefone / WhatsApp</label>
                    <input type="text" id="bk-phone" class="form-control form-control-sm" value="<?= htmlspecialchars($prefPhone, ENT_QUOTES) ?>" inputmode="numeric">
                </div>
                <?php if (!empty($company)): ?>
                <div class="col-md-6">
                    <label class="form-label small">Empresa</label>
                    <input type="text" class="form-control form-control-sm" value="<?= htmlspecialchars($company, ENT_QUOTES) ?>" disabled>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="mb-3">
            <div class="step-label mb-1">2 · Data</div>
            <input type="date" id="bk-date" class="form-control form-control-sm" min="<?= htmlspecialchars($minDate ?? date('Y-m-d'), ENT_QUOTES) ?>" value="<?= htmlspecialchars($minDate ?? date('Y-m-d'), ENT_QUOTES) ?>">
        </div>

        <div class="mb-3">
            <div class="step-label mb-1">3 · Horário</div>
            <div id="slots" class="d-flex flex-wrap gap-2"><span class="text-muted small">Selecione uma data…</span></div>
        </div>

        <button class="btn btn-success w-100" id="bk-confirm" onclick="confirmBooking()" disabled>
            <i class="bi bi-check-lg"></i> Confirmar agendamento
        </button>
        <p class="text-muted small text-center mt-2 mb-0">Você receberá a confirmação e o link da reunião por e-mail e WhatsApp.</p>
    </div>
</div>

<script>
const BASE = '<?= $baseUrl ?>';
const TOKEN = '<?= $token ?>';
let selectedTime = null;

function alertBox(type, msg) {
    document.getElementById('alert-box').innerHTML =
        `<div class="alert alert-${type} py-2 px-3 small">${msg}</div>`;
}

function loadSlots() {
    selectedTime = null;
    document.getElementById('bk-confirm').disabled = true;
    const date = document.getElementById('bk-date').value;
    const box = document.getElementById('slots');
    box.innerHTML = '<span class="text-muted small">Carregando…</span>';
    fetch(`${BASE}/booking/slots/${TOKEN}?date=${date}`)
        .then(r => r.json())
        .then(d => {
            const slots = d.slots || [];
            if (!slots.length) { box.innerHTML = '<span class="text-muted small">Nenhum horário disponível nesta data.</span>'; return; }
            box.innerHTML = slots.map(s =>
                `<button type="button" class="btn btn-outline-secondary btn-sm slot-btn" onclick="pickSlot(this,'${s}')">${s}</button>`
            ).join('');
        })
        .catch(() => { box.innerHTML = '<span class="text-danger small">Erro ao carregar horários.</span>'; });
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
            const meet = d.meet_link ? `<p class="mt-2"><a href="${d.meet_link}" class="btn btn-success btn-sm" target="_blank"><i class="bi bi-camera-video"></i> Entrar na reunião</a></p>` : '';
            document.querySelector('.booking-bd').innerHTML =
                `<div class="text-center py-4">
                    <i class="bi bi-check-circle-fill text-success" style="font-size:3rem;"></i>
                    <h5 class="mt-3">Reunião confirmada!</h5>
                    <p class="text-muted mb-1">Enviamos os detalhes por e-mail e WhatsApp.</p>
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
