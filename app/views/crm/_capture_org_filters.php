<?php
// Filtros da busca de EMPRESAS (Organization Search) — todos os parâmetros documentados.
?>
<div class="mb-2">
    <label class="form-label small fw-medium mb-1">Nome da empresa</label>
    <input type="text" class="form-control form-control-sm f-orgs" data-key="q_organization_name" placeholder="ex: apollo">
</div>
<div class="mb-2">
    <label class="form-label small fw-medium mb-1">Palavras-chave (setor)</label>
    <input type="text" class="form-control form-control-sm f-orgs" data-key="q_organization_keyword_tags" placeholder="ex: mining, consulting">
</div>
<div class="mb-2">
    <label class="form-label small fw-medium mb-1">Domínios</label>
    <input type="text" class="form-control form-control-sm f-orgs" data-key="q_organization_domains_list" placeholder="ex: apollo.io, microsoft.com">
</div>
<div class="mb-2">
    <label class="form-label small fw-medium mb-1">Localização (HQ)</label>
    <input type="text" class="form-control form-control-sm f-orgs" data-key="organization_locations" placeholder="ex: texas, tokyo">
</div>
<div class="mb-2">
    <label class="form-label small fw-medium mb-1">Excluir localização</label>
    <input type="text" class="form-control form-control-sm f-orgs" data-key="organization_not_locations" placeholder="ex: ireland, seoul">
</div>
<div class="mb-2">
    <label class="form-label small fw-medium mb-1">Faixas de nº de funcionários</label>
    <input type="text" class="form-control form-control-sm f-orgs" data-key="organization_num_employees_ranges" placeholder="ex: 1,10; 250,500">
</div>
<div class="row g-2 mb-2">
    <div class="col-6">
        <label class="form-label small fw-medium mb-1">Receita mín.</label>
        <input type="number" class="form-control form-control-sm f-orgs-raw" data-key="revenue_min" placeholder="300000">
    </div>
    <div class="col-6">
        <label class="form-label small fw-medium mb-1">Receita máx.</label>
        <input type="number" class="form-control form-control-sm f-orgs-raw" data-key="revenue_max" placeholder="50000000">
    </div>
</div>
<div class="mb-2">
    <label class="form-label small fw-medium mb-1">Tecnologias usadas (qualquer)</label>
    <input type="text" class="form-control form-control-sm f-orgs" data-key="currently_using_any_of_technology_uids" placeholder="ex: salesforce">
</div>
<div class="mb-2">
    <label class="form-label small fw-medium mb-1">IDs de organização</label>
    <input type="text" class="form-control form-control-sm f-orgs" data-key="organization_ids" placeholder="ex: 5e66b6381e05b4008c8331b8">
</div>
<hr class="my-2">
<div class="row g-2 mb-2">
    <div class="col-6">
        <label class="form-label small fw-medium mb-1">Último funding mín.</label>
        <input type="number" class="form-control form-control-sm f-orgs-raw" data-key="latest_funding_min" placeholder="5000000">
    </div>
    <div class="col-6">
        <label class="form-label small fw-medium mb-1">Último funding máx.</label>
        <input type="number" class="form-control form-control-sm f-orgs-raw" data-key="latest_funding_max" placeholder="15000000">
    </div>
</div>
<div class="row g-2 mb-2">
    <div class="col-6">
        <label class="form-label small fw-medium mb-1">Funding total mín.</label>
        <input type="number" class="form-control form-control-sm f-orgs-raw" data-key="total_funding_min" placeholder="50000000">
    </div>
    <div class="col-6">
        <label class="form-label small fw-medium mb-1">Funding total máx.</label>
        <input type="number" class="form-control form-control-sm f-orgs-raw" data-key="total_funding_max" placeholder="350000000">
    </div>
</div>
<hr class="my-2">
<div class="mb-2">
    <label class="form-label small fw-medium mb-1">Vagas ativas — cargos</label>
    <input type="text" class="form-control form-control-sm f-orgs" data-key="q_organization_job_titles" placeholder="ex: research analyst">
</div>
<div class="mb-2">
    <label class="form-label small fw-medium mb-1">Vagas ativas — locais</label>
    <input type="text" class="form-control form-control-sm f-orgs" data-key="organization_job_locations" placeholder="ex: japan">
</div>
<div class="row g-2 mb-2">
    <div class="col-6">
        <label class="form-label small fw-medium mb-1">Nº vagas mín.</label>
        <input type="number" class="form-control form-control-sm f-orgs-raw" data-key="num_jobs_min" placeholder="50">
    </div>
    <div class="col-6">
        <label class="form-label small fw-medium mb-1">Nº vagas máx.</label>
        <input type="number" class="form-control form-control-sm f-orgs-raw" data-key="num_jobs_max" placeholder="500">
    </div>
</div>
