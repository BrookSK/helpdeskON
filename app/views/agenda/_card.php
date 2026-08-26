<?php
// Card de reunião no Kanban. Espera $m (reunião) e $urgencyMeta, $tempMeta no escopo.
$um = $urgencyMeta[$m['urgency']] ?? ['—', '#888'];
$tm = !empty($m['temperature']) ? ($tempMeta[$m['temperature']] ?? null) : null;
$clientName = $m['crm_contact_name'] ?? $m['client_name'] ?? null;
$when = !empty($m['meeting_at']) ? date('d/m H:i', strtotime($m['meeting_at'])) : 'Sem data';
?>
<?php $isOperational = ($m['meeting_type'] ?? 'comercial') === 'operacional'; ?>
<div class="agenda-card<?= $isOperational ? ' agenda-card-op' : '' ?>"
     draggable="true"
     data-id="<?= $m['id'] ?>"
     data-type="<?= $isOperational ? 'operacional' : 'comercial' ?>"
     onclick="openMeetingModal(<?= $m['id'] ?>)">
    <h6 class="fw-semibold mb-1"><?= escape($m['title']) ?></h6>
    <?php if ($isOperational): ?>
    <div class="small"><span class="agenda-badge" style="background:#455a64;">Operacional</span></div>
    <?php elseif ($clientName): ?>
    <div class="small text-muted"><i class="bi bi-person"></i> <?= escape($clientName) ?></div>
    <?php endif; ?>
    <div class="ac-meta">
        <span><i class="bi bi-calendar-event"></i> <?= escape($when) ?></span>
        <?php if (!empty($m['assigned_name'])): ?><span><i class="bi bi-person-badge"></i> <?= escape($m['assigned_name']) ?></span><?php endif; ?>
    </div>
    <?php if (!$isOperational): ?>
    <div class="mt-2 d-flex flex-wrap gap-1">
        <span class="agenda-badge" style="background:<?= $um[1] ?>"><?= $um[0] ?></span>
        <?php if ($tm): ?><span class="agenda-badge" style="background:<?= $tm[1] ?>"><?= $tm[0] ?></span><?php endif; ?>
    </div>
    <?php endif; ?>
</div>
