<?php
// Filtros da busca de PESSOAS (People API Search) — organizados em seções recolhíveis.
// Enumeráveis viram checkbox/dropdown; faixas viram intervalos (min/max).
$seniorities = [
    'owner' => 'Owner', 'founder' => 'Founder', 'c_suite' => 'C-Level',
    'partner' => 'Partner', 'vp' => 'VP', 'head' => 'Head', 'director' => 'Diretor',
    'manager' => 'Gerente', 'senior' => 'Sênior', 'entry' => 'Júnior/Pleno', 'intern' => 'Estagiário',
];
$emailStatuses = [
    'verified' => 'Verificado', 'unverified' => 'Não verificado',
    'likely_to_engage' => 'Provável engajamento', 'unavailable' => 'Indisponível',
];
// Faixas de funcionários (valor no formato "min,max" esperado pelo Apollo)
$employeeRanges = [
    '1,10' => '1–10', '11,20' => '11–20', '21,50' => '21–50', '51,100' => '51–100',
    '101,200' => '101–200', '201,500' => '201–500', '501,1000' => '501–1.000',
    '1001,2000' => '1.001–2.000', '2001,5000' => '2.001–5.000',
    '5001,10000' => '5.001–10.000', '10001,1000000' => '10.001+',
];
// Opções de receita para os selects de intervalo
$revenueOptions = [
    '' => '—', '100000' => '100 mil', '500000' => '500 mil', '1000000' => '1 mi',
    '5000000' => '5 mi', '10000000' => '10 mi', '25000000' => '25 mi', '50000000' => '50 mi',
    '100000000' => '100 mi', '500000000' => '500 mi', '1000000000' => '1 bi',
];

/** Helper para renderizar um cabeçalho de seção recolhível. */
function capSection($id, $icon, $title, $open = false)
{
    $show = $open ? 'show' : '';
    $col = $open ? '' : 'collapsed';
    echo '<div class="cap-section">'
        . '<button class="cap-section-btn ' . $col . '" type="button" data-bs-toggle="collapse" data-bs-target="#' . $id . '">'
        . '<span><i class="bi ' . $icon . '"></i> ' . $title . '</span>'
        . '<i class="bi bi-chevron-down cap-caret"></i>'
        . '</button>'
        . '<div id="' . $id . '" class="collapse ' . $show . '"><div class="cap-section-body">';
}
function capSectionEnd() { echo '</div></div></div>'; }
?>

<style>
/* Visual das seções de filtro */
.cap-section { border-bottom: 1px solid #eef0f2; }
.cap-section-btn {
    width: 100%; background: none; border: none; text-align: left;
    padding: 10px 2px; font-size: 0.82rem; font-weight: 600; color: #334;
    display: flex; align-items: center; justify-content: space-between; cursor: pointer;
}
.cap-section-btn i.bi:first-child { color: var(--primary); margin-right: 6px; }
.cap-section-btn .cap-caret { transition: transform 0.2s; font-size: 0.7rem; color: #999; }
.cap-section-btn.collapsed .cap-caret { transform: rotate(-90deg); }
.cap-section-body { padding: 4px 2px 12px; }
.cap-chips { display: flex; flex-wrap: wrap; gap: 6px; }
.cap-chip {
    display: inline-flex; align-items: center; gap: 5px; border: 1px solid #d9dde1;
    border-radius: 20px; padding: 4px 10px; font-size: 0.76rem; cursor: pointer;
    user-select: none; transition: all 0.15s; margin: 0;
}
.cap-chip input { display: none; }
.cap-chip:hover { border-color: var(--primary); }
.cap-chip.checked { background: var(--primary-50); border-color: var(--primary); color: var(--primary-dark); font-weight: 600; }
.cap-label { font-size: 0.75rem; font-weight: 500; color: #667; margin-bottom: 3px; display: block; }
.cap-range { display: flex; align-items: center; gap: 6px; }
.cap-range .form-select, .cap-range .form-control { font-size: 0.8rem; }
.cap-range span { color: #aaa; font-size: 0.75rem; }
</style>

<!-- Cargo & Senioridade -->
<?php capSection('sec-cargo', 'bi-person-badge', 'Cargo & Senioridade', true); ?>
    <div class="mb-2">
        <label class="cap-label">Cargos</label>
        <input type="text" class="form-control form-control-sm f-people" data-key="person_titles" placeholder="ex: marketing manager, ceo">
        <label class="cap-chip mt-2">
            <input type="checkbox" class="f-people" data-key="include_similar_titles" data-bool="1" checked onchange="syncChip(this)">
            <span>Incluir cargos similares</span>
        </label>
    </div>
    <div>
        <label class="cap-label">Senioridade</label>
        <div class="cap-chips">
            <?php foreach ($seniorities as $val => $lbl): ?>
            <label class="cap-chip">
                <input type="checkbox" class="f-people-multi" data-key="person_seniorities" value="<?= $val ?>" onchange="syncChip(this)">
                <span><?= $lbl ?></span>
            </label>
            <?php endforeach; ?>
        </div>
    </div>
<?php capSectionEnd(); ?>

<!-- Localização -->
<?php capSection('sec-local', 'bi-geo-alt', 'Localização'); ?>
    <div class="mb-2">
        <label class="cap-label">Localização da pessoa</label>
        <input type="text" class="form-control form-control-sm f-people" data-key="person_locations" placeholder="ex: são paulo, brazil">
    </div>
    <div>
        <label class="cap-label">Localização da empresa (HQ)</label>
        <input type="text" class="form-control form-control-sm f-people" data-key="organization_locations" placeholder="ex: chicago, spain">
    </div>
<?php capSectionEnd(); ?>

<!-- Empresa -->
<?php capSection('sec-empresa', 'bi-building', 'Empresa'); ?>
    <div class="mb-2">
        <label class="cap-label">Domínios da empresa</label>
        <input type="text" class="form-control form-control-sm f-people" data-key="q_organization_domains_list" placeholder="ex: apollo.io, microsoft.com">
    </div>
    <div class="mb-2">
        <label class="cap-label">Nº de funcionários</label>
        <div class="cap-chips">
            <?php foreach ($employeeRanges as $val => $lbl): ?>
            <label class="cap-chip">
                <input type="checkbox" class="f-people-multi" data-key="organization_num_employees_ranges" value="<?= $val ?>" onchange="syncChip(this)">
                <span><?= $lbl ?></span>
            </label>
            <?php endforeach; ?>
        </div>
    </div>
    <div>
        <label class="cap-label">Receita anual (US$)</label>
        <div class="cap-range">
            <select class="form-select form-select-sm f-people-raw" data-key="revenue_min">
                <?php foreach ($revenueOptions as $v => $l): ?><option value="<?= $v ?>"><?= $l ?></option><?php endforeach; ?>
            </select>
            <span>até</span>
            <select class="form-select form-select-sm f-people-raw" data-key="revenue_max">
                <?php foreach ($revenueOptions as $v => $l): ?><option value="<?= $v ?>"><?= $l ?></option><?php endforeach; ?>
            </select>
        </div>
    </div>
<?php capSectionEnd(); ?>

<!-- E-mail -->
<?php capSection('sec-email', 'bi-envelope', 'E-mail'); ?>
    <label class="cap-label">Status do e-mail</label>
    <div class="cap-chips">
        <?php foreach ($emailStatuses as $val => $lbl): ?>
        <label class="cap-chip">
            <input type="checkbox" class="f-people-multi" data-key="contact_email_status" value="<?= $val ?>" onchange="syncChip(this)">
            <span><?= $lbl ?></span>
        </label>
        <?php endforeach; ?>
    </div>
<?php capSectionEnd(); ?>

<!-- Tecnologias -->
<?php capSection('sec-tech', 'bi-cpu', 'Tecnologias'); ?>
    <div class="mb-2">
        <label class="cap-label">Usa todas estas</label>
        <input type="text" class="form-control form-control-sm f-people" data-key="currently_using_all_of_technology_uids" placeholder="ex: salesforce, wordpress_org">
    </div>
    <div class="mb-2">
        <label class="cap-label">Usa qualquer uma</label>
        <input type="text" class="form-control form-control-sm f-people" data-key="currently_using_any_of_technology_uids" placeholder="ex: google_analytics">
    </div>
    <div>
        <label class="cap-label">Não usa</label>
        <input type="text" class="form-control form-control-sm f-people" data-key="currently_not_using_any_of_technology_uids" placeholder="ex: hubspot">
    </div>
<?php capSectionEnd(); ?>

<!-- Vagas ativas -->
<?php capSection('sec-vagas', 'bi-briefcase', 'Vagas ativas na empresa'); ?>
    <div class="mb-2">
        <label class="cap-label">Cargos das vagas</label>
        <input type="text" class="form-control form-control-sm f-people" data-key="q_organization_job_titles" placeholder="ex: sales manager">
    </div>
    <div class="mb-2">
        <label class="cap-label">Locais das vagas</label>
        <input type="text" class="form-control form-control-sm f-people" data-key="organization_job_locations" placeholder="ex: atlanta, japan">
    </div>
    <div class="mb-2">
        <label class="cap-label">Nº de vagas</label>
        <div class="cap-range">
            <input type="number" class="form-control form-control-sm f-people-raw" data-key="num_jobs_min" placeholder="mín" min="0">
            <span>até</span>
            <input type="number" class="form-control form-control-sm f-people-raw" data-key="num_jobs_max" placeholder="máx" min="0">
        </div>
    </div>
    <div>
        <label class="cap-label">Vaga postada entre</label>
        <div class="cap-range">
            <input type="date" class="form-control form-control-sm f-people-raw" data-key="job_posted_min">
            <span>e</span>
            <input type="date" class="form-control form-control-sm f-people-raw" data-key="job_posted_max">
        </div>
    </div>
<?php capSectionEnd(); ?>

<!-- Palavras-chave -->
<?php capSection('sec-kw', 'bi-search', 'Palavras-chave'); ?>
    <input type="text" class="form-control form-control-sm f-people" data-key="q_keywords" placeholder="ex: growth, fintech">
<?php capSectionEnd(); ?>
