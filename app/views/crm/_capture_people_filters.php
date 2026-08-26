<?php
// Filtros da busca de PESSOAS (People API Search) — todos os parâmetros documentados.
// Campos de lista aceitam múltiplos valores separados por vírgula.
$seniorities = ['owner','founder','c_suite','partner','vp','head','director','manager','senior','entry','intern'];
$emailStatuses = ['verified','unverified','likely_to_engage','unavailable'];
?>
<div class="mb-2">
    <label class="form-label small fw-medium mb-1">Palavras-chave</label>
    <input type="text" class="form-control form-control-sm f-people" data-key="q_keywords" placeholder="ex: growth, fintech">
</div>
<div class="mb-2">
    <label class="form-label small fw-medium mb-1">Cargos (titles)</label>
    <input type="text" class="form-control form-control-sm f-people" data-key="person_titles" placeholder="ex: marketing manager, ceo">
    <div class="form-check form-check-inline mt-1">
        <input class="form-check-input f-people" type="checkbox" data-key="include_similar_titles" id="similar-titles" checked>
        <label class="form-check-label small" for="similar-titles">Incluir cargos similares</label>
    </div>
</div>
<div class="mb-2">
    <label class="form-label small fw-medium mb-1">Senioridade</label>
    <select class="form-select form-select-sm f-people" data-key="person_seniorities" multiple size="4">
        <?php foreach ($seniorities as $s): ?>
        <option value="<?= $s ?>"><?= ucfirst(str_replace('_', ' ', $s)) ?></option>
        <?php endforeach; ?>
    </select>
    <small class="text-muted" style="font-size:0.7rem;">Ctrl/Cmd para múltiplos</small>
</div>
<div class="mb-2">
    <label class="form-label small fw-medium mb-1">Localização da pessoa</label>
    <input type="text" class="form-control form-control-sm f-people" data-key="person_locations" placeholder="ex: são paulo, brazil">
</div>
<div class="mb-2">
    <label class="form-label small fw-medium mb-1">Localização da empresa (HQ)</label>
    <input type="text" class="form-control form-control-sm f-people" data-key="organization_locations" placeholder="ex: chicago, spain">
</div>
<div class="mb-2">
    <label class="form-label small fw-medium mb-1">Domínios da empresa</label>
    <input type="text" class="form-control form-control-sm f-people" data-key="q_organization_domains_list" placeholder="ex: apollo.io, microsoft.com">
</div>
<div class="mb-2">
    <label class="form-label small fw-medium mb-1">Status do e-mail</label>
    <select class="form-select form-select-sm f-people" data-key="contact_email_status" multiple size="4">
        <?php foreach ($emailStatuses as $es): ?>
        <option value="<?= $es ?>"><?= ucfirst(str_replace('_', ' ', $es)) ?></option>
        <?php endforeach; ?>
    </select>
</div>
<div class="mb-2">
    <label class="form-label small fw-medium mb-1">IDs de organização (Apollo)</label>
    <input type="text" class="form-control form-control-sm f-people" data-key="organization_ids" placeholder="ex: 5e66b6381e05b4008c8331b8">
</div>
<div class="mb-2">
    <label class="form-label small fw-medium mb-1">Faixas de nº de funcionários</label>
    <input type="text" class="form-control form-control-sm f-people" data-key="organization_num_employees_ranges" placeholder="ex: 1,10; 250,500">
    <small class="text-muted" style="font-size:0.7rem;">min,max separados por ; </small>
</div>
<div class="row g-2 mb-2">
    <div class="col-6">
        <label class="form-label small fw-medium mb-1">Receita mín.</label>
        <input type="number" class="form-control form-control-sm f-people-raw" data-key="revenue_min" placeholder="500000">
    </div>
    <div class="col-6">
        <label class="form-label small fw-medium mb-1">Receita máx.</label>
        <input type="number" class="form-control form-control-sm f-people-raw" data-key="revenue_max" placeholder="5000000">
    </div>
</div>
<hr class="my-2">
<div class="mb-2">
    <label class="form-label small fw-medium mb-1">Tecnologias usadas (todas)</label>
    <input type="text" class="form-control form-control-sm f-people" data-key="currently_using_all_of_technology_uids" placeholder="ex: salesforce, wordpress_org">
</div>
<div class="mb-2">
    <label class="form-label small fw-medium mb-1">Tecnologias usadas (qualquer)</label>
    <input type="text" class="form-control form-control-sm f-people" data-key="currently_using_any_of_technology_uids" placeholder="ex: google_analytics">
</div>
<div class="mb-2">
    <label class="form-label small fw-medium mb-1">Excluir tecnologias</label>
    <input type="text" class="form-control form-control-sm f-people" data-key="currently_not_using_any_of_technology_uids" placeholder="ex: hubspot">
</div>
<hr class="my-2">
<div class="mb-2">
    <label class="form-label small fw-medium mb-1">Vagas ativas — cargos</label>
    <input type="text" class="form-control form-control-sm f-people" data-key="q_organization_job_titles" placeholder="ex: sales manager">
</div>
<div class="mb-2">
    <label class="form-label small fw-medium mb-1">Vagas ativas — locais</label>
    <input type="text" class="form-control form-control-sm f-people" data-key="organization_job_locations" placeholder="ex: atlanta, japan">
</div>
<div class="row g-2 mb-2">
    <div class="col-6">
        <label class="form-label small fw-medium mb-1">Nº vagas mín.</label>
        <input type="number" class="form-control form-control-sm f-people-raw" data-key="num_jobs_min" placeholder="50">
    </div>
    <div class="col-6">
        <label class="form-label small fw-medium mb-1">Nº vagas máx.</label>
        <input type="number" class="form-control form-control-sm f-people-raw" data-key="num_jobs_max" placeholder="500">
    </div>
</div>
<div class="row g-2 mb-2">
    <div class="col-6">
        <label class="form-label small fw-medium mb-1">Vaga postada de</label>
        <input type="date" class="form-control form-control-sm f-people-raw" data-key="job_posted_min">
    </div>
    <div class="col-6">
        <label class="form-label small fw-medium mb-1">Vaga postada até</label>
        <input type="date" class="form-control form-control-sm f-people-raw" data-key="job_posted_max">
    </div>
</div>
