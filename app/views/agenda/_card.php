<?php
// Card de reunião no Kanban. Espera $m (reunião) e $urgencyMeta, $tempMeta no escopo.
$um = $urgencyMeta[$m['urgency']] ?? ['—', '#888'];
$tm = !empty($m['temperature']) ? ($tempMeta[$m['temperature']] ?? null) : null;
$clientName = $m['crm_contact_name'] ?? $m['client_name'] ?? null;
$when = !empty($m['meeting_at']) ? date('d/m H:i', strtotime($m['meeting_at'])) : 'Sem data';

$meetingType = $m['meeting_type'] ?? 'comercial';
$isOperational = $meetingType === 'operacional';
$isExternal = $meetingType === 'externo';
$isCommercial = !$isOperational && !$isExternal;

// Convidados externos (para exibir a contagem no card).
$externalGuests = [];
if ($isExternal && !empty($m['external_guests'])) {
    $decoded = json_decode($m['external_guests'], true);
    if (is_array($decoded)) $externalGuests = $decoded;
}
// Tipo enviado ao Kanban para as regras de arraste (operacional/externo usam status simplificado).
$cardType = $isCommercial ? 'comercial' : 'operacional';
?>
<div class="agenda-card<?= $isCommercial ? '' : ' agenda-card-op' ?>"
     draggable="true"
     data-id="<?= $m['id'] ?>"
     data-type="<?= $cardType ?>"
     onclick="openMeetingModal(<?= $m['id'] ?>)">
    <h6 class="fw-semibold mb-1"><?= escape($m['title']) ?></h6>
    <?php if ($isOperational): ?>
    <div class="small"><span class="agenda-badge" style="background:#455a64;">Operacional</span></div>
    <?php elseif ($isExternal): ?>
    <div class="small"><span class="agenda-badge" style="background:#4285F4;"><i class="bi bi-person-plus"></i> Convite externo</span></div>
    <?php if (!empty($externalGuests)): ?>
    <div class="small text-muted mt-1"><i class="bi bi-people"></i> <?= count($externalGuests) ?> convidado(s)</div>
    <?php endif; ?>
    <?php elseif ($clientName): ?>
    <div class="small text-muted"><i class="bi bi-person"></i> <?= escape($clientName) ?></div>
    <?php endif; ?>
    <div class="ac-meta">
        <span><i class="bi bi-calendar-event"></i> <?= escape($when) ?></span>
        <?php if (!empty($m['assigned_name'])): ?><span><i class="bi bi-person-badge"></i> <?= escape($m['assigned_name']) ?></span><?php endif; ?>
    </div>
    <?php if ($isCommercial): ?>
    <div class="mt-2 d-flex flex-wrap gap-1">
        <span class="agenda-badge" style="background:<?= $um[1] ?>"><?= $um[0] ?></span>
        <?php if ($tm): ?><span class="agenda-badge" style="background:<?= $tm[1] ?>"><?= $tm[0] ?></span><?php endif; ?>
    </div>
    <?php endif; ?>
</div>
