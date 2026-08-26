<?php
// Filtros da busca de EMPRESAS (Organization Search) — seções recolhíveis.
// Reutiliza os helpers capSection/capSectionEnd definidos em _capture_people_filters.php.
$orgEmployeeRanges = [
    '1,10' => '1–10', '11,20' => '11–20', '21,50' => '21–50', '51,100' => '51–100',
    '101,200' => '101–200', '201,500' => '201–500', '501,1000' => '501–1.000',
    '1001,2000' => '1.001–2.000', '2001,5000' => '2.001–5.000',
    '5001,10000' => '5.001–10.000', '10001,1000000' => '10.001+',
];
$orgRevenueOptions = [
    '' => '—', '100000' => '100 mil', '500000' => '500 mil', '1000000' => '1 mi',
    '5000000' => '5 mi', '10000000' => '10 mi', '25000000' => '25 mi', '50000000' => '50 mi',
    '100000000' => '100 mi', '500000000' => '500 mi', '1000000000' => '1 bi',
];
$fundingOptions = [
    '' => '—', '500000' => '500 mil', '1000000' => '1 mi', '5000000' => '5 mi',
    '15000000' => '15 mi', '50000000' => '50 mi', '100000000' => '100 mi',
    '250000000' => '250 mi', '500000000' => '500 mi',
];
?>

<!-- Identificação -->
<?php capSection('org-ident', 'bi-building', 'Empresa', true); ?>
    <div class="mb-2">
        <label class="cap-label">Nome da empresa</label>
        <input type="text" class="form-control form-control-sm f-orgs" data-key="q_organization_name" placeholder="ex: apollo">
    </div>
    <div class="mb-2">
        <label class="cap-label">Palavras-chave / setor</label>
        <input type="text" class="form-control form-control-sm f-orgs" data-key="q_organization_keyword_tags" placeholder="ex: mining, consulting">
    </div>
    <div>
        <label class="cap-label">Domínios</label>
        <input type="text" class="form-control form-control-sm f-orgs" data-key="q_organization_domains_list" placeholder="ex: apollo.io, microsoft.com">
    </div>
<?php capSectionEnd(); ?>

<!-- Porte -->
<?php capSection('org-porte', 'bi-people', 'Porte & Receita'); ?>
    <div class="mb-2">
        <label class="cap-label">Nº de funcionários</label>
        <div class="cap-chips">
            <?php foreach ($orgEmployeeRanges as $val => $lbl): ?>
            <label class="cap-chip">
                <input type="checkbox" class="f-orgs-multi" data-key="organization_num_employees_ranges" value="<?= $val ?>" onchange="syncChip(this)">
                <span><?= $lbl ?></span>
            </label>
            <?php endforeach; ?>
        </div>
    </div>
    <div>
        <label class="cap-label">Receita anual (US$)</label>
        <div class="cap-range">
            <select class="form-select form-select-sm f-orgs-raw" data-key="revenue_min">
                <?php foreach ($orgRevenueOptions as $v => $l): ?><option value="<?= $v ?>"><?= $l ?></option><?php endforeach; ?>
            </select>
            <span>até</span>
            <select class="form-select form-select-sm f-orgs-raw" data-key="revenue_max">
                <?php foreach ($orgRevenueOptions as $v => $l): ?><option value="<?= $v ?>"><?= $l ?></option><?php endforeach; ?>
            </select>
        </div>
    </div>
<?php capSectionEnd(); ?>

<!-- Localização -->
<?php capSection('org-local', 'bi-geo-alt', 'Localização'); ?>
    <label class="cap-label">Localização (HQ)</label>
    <div class="cap-range mb-1">
        <select class="form-select form-select-sm cap-state-select" data-target="organization_locations_org" onchange="onStateChange(this)">
            <option value="">Carregando estados…</option>
        </select>
        <select class="form-select form-select-sm cap-city-select" data-target="organization_locations_org" onchange="onCityChange(this)">
            <option value="">Cidade…</option>
        </select>
    </div>
    <div class="cap-chips mb-1" id="chips-organization_locations_org"></div>

    <hr class="my-2">
    <label class="cap-label">Excluir localização</label>
    <div class="cap-range mb-1">
        <select class="form-select form-select-sm cap-state-select" data-target="organization_not_locations_org" onchange="onStateChange(this)">
            <option value="">Carregando estados…</option>
        </select>
        <select class="form-select form-select-sm cap-city-select" data-target="organization_not_locations_org" onchange="onCityChange(this)">
            <option value="">Cidade…</option>
        </select>
    </div>
    <div class="cap-chips mb-1" id="chips-organization_not_locations_org"></div>
<?php capSectionEnd(); ?>

<!-- Tecnologias -->
<?php capSection('org-tech', 'bi-cpu', 'Tecnologias (usa qualquer uma)'); ?>
    <label class="cap-label">Buscar tecnologia</label>
    <input type="text" class="form-control form-control-sm mb-2 cap-tech-search" data-scope="orgs" placeholder="filtrar lista..." oninput="filterTech(this)">
    <?php foreach ($apolloTech as $cat => $techs): ?>
    <div class="cap-tech-group" data-scope="orgs">
        <div class="cap-label mt-1" style="color:#889;"><?= escape($cat) ?></div>
        <div class="cap-chips">
            <?php foreach ($techs as $uid => $lbl): ?>
            <label class="cap-chip cap-tech-chip" data-scope="orgs" data-label="<?= escape(mb_strtolower($lbl)) ?>">
                <input type="checkbox" class="cap-tech-cb" data-scope="orgs" value="<?= $uid ?>" onchange="syncChip(this)">
                <span><?= escape($lbl) ?></span>
            </label>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <input type="hidden" class="f-orgs" data-key="currently_using_any_of_technology_uids" id="val-orgs-tech-any">
<?php capSectionEnd(); ?>

<!-- Funding -->
<?php capSection('org-funding', 'bi-cash-coin', 'Investimento (funding)'); ?>
    <div class="mb-2">
        <label class="cap-label">Último funding (US$)</label>
        <div class="cap-range">
            <select class="form-select form-select-sm f-orgs-raw" data-key="latest_funding_min">
                <?php foreach ($fundingOptions as $v => $l): ?><option value="<?= $v ?>"><?= $l ?></option><?php endforeach; ?>
            </select>
            <span>até</span>
            <select class="form-select form-select-sm f-orgs-raw" data-key="latest_funding_max">
                <?php foreach ($fundingOptions as $v => $l): ?><option value="<?= $v ?>"><?= $l ?></option><?php endforeach; ?>
            </select>
        </div>
    </div>
    <div>
        <label class="cap-label">Funding total (US$)</label>
        <div class="cap-range">
            <select class="form-select form-select-sm f-orgs-raw" data-key="total_funding_min">
                <?php foreach ($fundingOptions as $v => $l): ?><option value="<?= $v ?>"><?= $l ?></option><?php endforeach; ?>
            </select>
            <span>até</span>
            <select class="form-select form-select-sm f-orgs-raw" data-key="total_funding_max">
                <?php foreach ($fundingOptions as $v => $l): ?><option value="<?= $v ?>"><?= $l ?></option><?php endforeach; ?>
            </select>
        </div>
    </div>
<?php capSectionEnd(); ?>

<!-- Vagas ativas -->
<?php capSection('org-vagas', 'bi-briefcase', 'Vagas ativas'); ?>
    <div class="mb-2">
        <label class="cap-label">Cargos das vagas</label>
        <input type="text" class="form-control form-control-sm f-orgs" data-key="q_organization_job_titles" placeholder="ex: research analyst">
    </div>
    <div class="mb-2">
        <label class="cap-label">Locais das vagas</label>
        <input type="text" class="form-control form-control-sm f-orgs" data-key="organization_job_locations" placeholder="ex: japan">
    </div>
    <div>
        <label class="cap-label">Nº de vagas</label>
        <div class="cap-range">
            <input type="number" class="form-control form-control-sm f-orgs-raw" data-key="num_jobs_min" placeholder="mín" min="0">
            <span>até</span>
            <input type="number" class="form-control form-control-sm f-orgs-raw" data-key="num_jobs_max" placeholder="máx" min="0">
        </div>
    </div>
<?php capSectionEnd(); ?>
