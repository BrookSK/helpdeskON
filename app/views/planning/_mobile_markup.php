<?php
/**
 * Planejamento no celular/tablet (< 992px) — estrutura e estilos.
 *
 * Layout só com rolagem vertical (modelo do protótipo planejamento-mobile):
 * cabeçalho fixo (menu, título, calendário, filtros + abas de status com
 * contagem), busca, atalhos (Minhas/Atrasadas/Hoje), filtros ativos, lista de
 * cards agrupada por status, botão "Novo card", seleção em lote e gavetas
 * (filtros, mover). O detalhe e a criação reaproveitam os modais da página,
 * estilizados como gaveta no celular. A lógica fica em _mobile_script.php.
 *
 * No desktop (>= 992px) nada disto aparece: o Kanban segue igual.
 */
?>
<script>
// Liga o layout do celular antes de pintar a página (evita piscar o Kanban).
document.body.classList.toggle('pm-on', window.matchMedia('(max-width: 991.98px)').matches);
</script>

<div id="pm" class="pm" aria-label="Planejamento (celular)">
    <header class="pm-top" id="pm-top">
        <div class="pm-bar">
            <button type="button" class="pm-ib" id="pm-menu" aria-label="Menu"><i class="bi bi-list"></i></button>
            <h1>Planejamento<small id="pm-sub"></small></h1>
            <button type="button" class="pm-ib" id="pm-cal" aria-label="Ver calendário" title="Calendário"><i class="bi bi-calendar3"></i></button>
            <button type="button" class="pm-ib" id="pm-filter" aria-label="Filtros"><i class="bi bi-funnel"></i><span class="pm-badge" id="pm-fbadge" hidden></span></button>
        </div>
        <nav class="pm-tabs" id="pm-tabs" role="tablist" aria-label="Status"></nav>
    </header>

    <div class="pm-scroll" id="pm-list">
        <label class="pm-search" id="pm-search">
            <i class="bi bi-search"></i>
            <input id="pm-q" type="search" placeholder="Buscar número, título ou empresa" autocomplete="off" enterkeyhint="search" aria-label="Buscar cards">
            <button class="pm-clr" id="pm-qclr" aria-label="Limpar busca" type="button"><i class="bi bi-x-lg"></i></button>
        </label>
        <div class="pm-quick" id="pm-quick"></div>
        <div class="pm-actives" id="pm-actives"></div>
        <div class="pm-tools">
            <span class="pm-res" id="pm-res"></span>
            <button type="button" class="pm-tl" id="pm-btn-sel"><i class="bi bi-check2-square"></i>Selecionar</button>
            <button type="button" class="pm-tl" id="pm-btn-cmp"><i class="bi bi-list"></i>Compacto</button>
        </div>
        <div id="pm-cards"></div>
    </div>

    <button type="button" class="pm-up" id="pm-up" aria-label="Voltar ao topo"><i class="bi bi-chevron-up"></i></button>
    <button type="button" class="pm-fab" id="pm-fab"><i class="bi bi-plus-lg"></i>Novo card</button>

    <div class="pm-selbar" id="pm-selbar">
        <div class="pm-sr1">
            <button type="button" class="pm-sbtn pm-s" id="pm-sel-cancel">Cancelar</button>
            <span class="pm-cnt" id="pm-sel-cnt"></span>
            <button type="button" class="pm-sbtn pm-s" id="pm-sel-all">Marcar todos</button>
        </div>
        <div class="pm-sr2">
            <button type="button" class="pm-sbtn" id="pm-sel-move">Mover</button>
            <button type="button" class="pm-sbtn" id="pm-sel-prio">Prioridade</button>
            <button type="button" class="pm-sbtn" id="pm-sel-who">Atribuir</button>
        </div>
    </div>

    <div class="pm-veil" id="pm-veil"></div>
    <section class="pm-sheet" id="pm-sheet" role="dialog" aria-modal="true" aria-hidden="true"></section>
    <div class="pm-toast" id="pm-toast" role="status" aria-live="polite"></div>
</div>

<style>
/* ===================== Planejamento mobile (< 992px) ===================== */
#pm { display: none; }
#pm {
    --pm-bg:#f5f6fa; --pm-surface:#fff; --pm-ink:#1f2937; --pm-muted:#667085; --pm-line:#e4e7ee; --pm-head:#eceff3;
    --pm-accent:var(--primary, #00bfa6); --pm-accent-soft:#dcf7f1; --pm-late:#d6336c;
    --pm-shadow:0 1px 2px rgba(31,41,55,.05),0 6px 18px rgba(31,41,55,.06);
    --pm-top-h:110px;
    color: var(--pm-ink);
}
@media (max-width: 991.98px) {
    body.pm-on { background: #f5f6fa; }
    body.pm-on #pm { display: block; }
    body.pm-on .mobile-topbar,
    body.pm-on .pm-desktop-only,
    body.pm-on #kanban-view { display: none !important; }
    body.pm-on .main-content { padding: 0 !important; }
    body.pm-on:not(.pm-cal) #calendar-view { display: none !important; }
    body.pm-on.pm-cal #calendar-view { display: block !important; padding: 10px; }
    body.pm-on.pm-cal #pm-tabs,
    body.pm-on.pm-cal #pm-list,
    body.pm-on.pm-cal .pm-selbar,
    body.pm-on.pm-cal .pm-up { display: none !important; }
    body.pm-sheet-open { overflow: hidden; }

    #pm button { font: inherit; color: inherit; background: none; border: 0; padding: 0; cursor: pointer; text-align: inherit; -webkit-tap-highlight-color: transparent; }
    #pm button:focus-visible, #pm input:focus-visible { outline: 2px solid var(--pm-accent); outline-offset: 2px; }
    #pm .bi { line-height: 1; }

    /* Cabeçalho fixo */
    #pm .pm-top { position: sticky; top: 0; z-index: 1020; background: var(--pm-surface); border-bottom: 1px solid var(--pm-line); padding-top: env(safe-area-inset-top, 0px); }
    #pm .pm-bar { display: flex; align-items: center; gap: 8px; padding: 8px 12px; }
    #pm .pm-ib { width: 44px; height: 44px; border-radius: 12px; border: 1px solid var(--pm-line); display: grid; place-items: center; position: relative; background: var(--pm-surface); flex: none; font-size: 20px; }
    #pm .pm-ib.on { background: var(--pm-accent-soft); border-color: var(--pm-accent); color: var(--pm-accent); }
    #pm .pm-bar h1 { font-size: 18px; margin: 0; font-weight: 800; flex: 1; line-height: 1.15; letter-spacing: -.01em; min-width: 0; }
    #pm .pm-bar h1 small { display: block; font-size: 12px; font-weight: 600; color: var(--pm-muted); letter-spacing: 0; }
    #pm .pm-badge { position: absolute; top: -6px; right: -6px; min-width: 19px; height: 19px; padding: 0 5px; border-radius: 10px; background: var(--pm-accent); color: #fff; font-size: 11px; font-weight: 800; display: grid; place-items: center; }
    #pm .pm-badge[hidden] { display: none; }
    #pm .pm-tabs { display: flex; overflow-x: auto; overflow-y: hidden; scrollbar-width: none; border-top: 1px solid var(--pm-line); }
    #pm .pm-tabs::-webkit-scrollbar { display: none; }
    #pm .pm-tab { flex: 0 0 20%; min-width: 68px; padding: 8px 2px 10px; display: flex; flex-direction: column; align-items: center; color: var(--pm-muted); font-weight: 600; font-size: 11.5px; min-height: 54px; position: relative; white-space: nowrap; text-align: center; }
    #pm .pm-tab .n { font-size: 18px; font-weight: 800; font-variant-numeric: tabular-nums; line-height: 1.2; color: var(--pm-ink); }
    #pm .pm-tab[aria-selected="true"], #pm .pm-tab[aria-selected="true"] .n { color: var(--c); }
    #pm .pm-tab[aria-selected="true"]::after { content: ""; position: absolute; left: 10%; right: 10%; bottom: 0; height: 3px; background: var(--c); border-radius: 3px 3px 0 0; }

    /* Área da lista */
    #pm .pm-scroll { padding: 0 12px 130px; }
    #pm .pm-search { display: flex; align-items: center; gap: 8px; margin: 12px 0 10px; padding: 0 12px; height: 46px; border-radius: 12px; background: var(--pm-surface); border: 1px solid var(--pm-line); color: var(--pm-muted); }
    #pm .pm-search input { flex: 1; border: 0; background: none; font: inherit; font-size: 16px; color: var(--pm-ink); height: 100%; width: 100%; min-width: 0; outline: none; }
    #pm .pm-search input::-webkit-search-cancel-button { display: none; }
    #pm .pm-search:focus-within { border-color: var(--pm-accent); }
    #pm .pm-clr { display: none; width: 30px; height: 30px; place-items: center; }
    #pm .pm-search.has .pm-clr { display: grid; }
    #pm .pm-quick { display: grid; grid-template-columns: repeat(auto-fit, minmax(0, 1fr)); gap: 8px; margin-bottom: 10px; }
    #pm .pm-qb { height: 42px; border-radius: 12px; border: 1px solid var(--pm-line); background: var(--pm-surface); font-weight: 600; font-size: 13px; display: flex; align-items: center; justify-content: center; gap: 6px; padding: 0 6px; white-space: nowrap; }
    #pm .pm-qb b { font-weight: 800; font-variant-numeric: tabular-nums; }
    #pm .pm-qb .dot { width: 8px; height: 8px; border-radius: 50%; background: #d6293e; flex: none; }
    #pm .pm-qb.on { background: var(--pm-ink); color: #fff; border-color: var(--pm-ink); }
    #pm .pm-actives { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 10px; }
    #pm .pm-actives:empty { display: none; }
    #pm .pm-ac { height: 32px; padding: 0 6px 0 11px; border-radius: 16px; background: var(--pm-accent-soft); color: var(--pm-accent); font-weight: 700; font-size: 12.5px; display: flex; align-items: center; gap: 4px; max-width: 100%; }
    #pm .pm-ac span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    #pm .pm-ac.clear { background: none; border: 1px solid var(--pm-line); color: var(--pm-muted); padding: 0 12px; }
    #pm .pm-tools { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; }
    #pm .pm-res { flex: 1; font-size: 13px; font-weight: 600; color: var(--pm-muted); min-width: 0; }
    #pm .pm-tl { height: 36px; padding: 0 12px; border-radius: 10px; border: 1px solid var(--pm-line); background: var(--pm-surface); font-weight: 600; font-size: 12.5px; display: flex; align-items: center; gap: 6px; white-space: nowrap; }
    #pm .pm-tl.on { background: var(--pm-accent-soft); border-color: var(--pm-accent); color: var(--pm-accent); }

    /* Grupos por status (aba Todos) */
    #pm .pm-gh { position: sticky; top: var(--pm-top-h); z-index: 2; background: var(--pm-bg); display: flex; align-items: center; gap: 8px; padding: 10px 2px 8px; font-weight: 800; font-size: 15px; color: var(--c); width: 100%; }
    #pm .pm-gh .cnt { font-size: 12px; padding: 2px 9px; border-radius: 11px; background: var(--c); color: #fff; font-weight: 800; }
    #pm .pm-gh .chev { margin-left: auto; color: var(--pm-muted); transition: transform .2s; }
    #pm .pm-gh.shut .chev { transform: rotate(-90deg); }
    #pm .pm-gl { display: flex; flex-direction: column; gap: 12px; margin-bottom: 6px; }
    #pm .pm-more { height: 42px; border-radius: 11px; border: 1px dashed var(--pm-line) !important; color: var(--pm-muted); font-weight: 700; font-size: 13.5px; display: flex; align-items: center; justify-content: center; gap: 6px; width: 100%; background: var(--pm-surface) !important; }

    /* Card */
    #pm .pm-card { background: var(--pm-surface); border-radius: 14px; box-shadow: var(--pm-shadow); padding: 13px 14px 12px; position: relative; border: 2px solid transparent; user-select: none; -webkit-user-select: none; -webkit-touch-callout: none; cursor: pointer; }
    #pm .pm-card.late { border-color: var(--pm-late); }
    #pm .pm-card.sel { border-color: var(--pm-accent); background: var(--pm-accent-soft); }
    #pm .pm-card .c1 { display: flex; align-items: center; justify-content: space-between; font-size: 13px; font-weight: 600; }
    #pm .pm-card .c1 .id { color: var(--pm-muted); }
    #pm .pm-card .c1 .pr { font-weight: 700; color: var(--pc); }
    #pm .pm-card h3 { margin: 6px 0 8px; font-size: 16px; line-height: 1.3; font-weight: 700; color: #111827; letter-spacing: -.005em; overflow-wrap: anywhere; }
    #pm .pm-tags { display: flex; flex-wrap: wrap; gap: 5px; margin: 0 0 9px; }
    #pm .pm-tag { display: inline-block; font-size: 12px; font-weight: 700; padding: 3px 10px; border-radius: 7px; background: #5b6573; color: #fff; }
    #pm .pm-card .mrow { display: flex; align-items: flex-end; justify-content: space-between; gap: 10px; }
    #pm .pm-card .meta { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 5px; color: #4b5563; font-size: 13.5px; font-weight: 500; flex: 1; min-width: 0; }
    #pm .pm-card .meta li { display: flex; align-items: center; gap: 8px; min-width: 0; }
    #pm .pm-card .meta .bi { font-size: 15px; color: var(--pm-muted); flex: none; }
    #pm .pm-card .meta li span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    #pm .pm-card .due { display: flex; align-items: center; gap: 5px; font-size: 13.5px; font-weight: 700; color: var(--pm-muted); white-space: nowrap; font-variant-numeric: tabular-nums; flex: none; }
    #pm .pm-card.late .due { color: var(--pm-late); }
    #pm .pm-card .acts { display: flex; gap: 8px; margin-top: 11px; }
    #pm .pm-card .go { flex: 1; height: 42px; border-radius: 11px; background: var(--pm-accent-soft); color: var(--pm-accent); font-weight: 700; font-size: 14px; display: flex; align-items: center; justify-content: center; gap: 6px; }
    #pm .pm-card .mv { width: 46px; height: 42px; border-radius: 11px; background: var(--pm-bg); color: var(--pm-muted); display: grid; place-items: center; border: 1px solid var(--pm-line); font-size: 18px; }
    #pm .pm-check { position: absolute; top: 11px; right: 12px; width: 24px; height: 24px; border-radius: 50%; border: 2px solid var(--pm-line); display: none; place-items: center; background: var(--pm-surface); color: #fff; font-size: 14px; }
    #pm.selmode .pm-check { display: grid; }
    #pm.selmode .pm-card .c1 .pr { margin-right: 34px; }
    #pm .pm-card.sel .pm-check { background: var(--pm-accent); border-color: var(--pm-accent); }
    #pm.selmode .pm-card .acts { display: none; }
    /* Modo compacto */
    #pm.compact .pm-card { padding: 10px 12px; }
    #pm.compact .pm-card h3 { font-size: 15px; margin: 4px 0 6px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    #pm.compact .pm-tags, #pm.compact .pm-card .meta li.m-emp, #pm.compact .pm-card .meta li.m-sol { display: none; }
    #pm.compact .pm-card .acts { margin-top: 8px; }
    #pm.compact .pm-card .go, #pm.compact .pm-card .mv { height: 38px; }
    #pm.compact .pm-card .mrow { align-items: center; }
    #pm .pm-empty { margin: 36px 8px; text-align: center; color: var(--pm-muted); line-height: 1.5; }
    #pm .pm-empty b { display: block; color: var(--pm-ink); font-size: 16px; margin-bottom: 4px; }
    #pm .pm-hint { margin: 4px 0 14px; font-size: 13px; color: var(--pm-muted); text-align: center; }
    #pm .pm-hint button { color: var(--pm-accent); font-weight: 700; }

    /* Botões flutuantes */
    #pm .pm-fab { position: fixed; right: 16px; bottom: calc(16px + env(safe-area-inset-bottom, 0px)); height: 54px; padding: 0 20px 0 16px; border-radius: 27px; background: var(--pm-accent); color: #fff; font-weight: 800; display: flex; align-items: center; gap: 8px; box-shadow: 0 8px 22px rgba(0,191,159,.4); z-index: 1030; font-size: 15px; }
    #pm .pm-fab .bi { font-size: 20px; }
    #pm .pm-up { position: fixed; left: 16px; bottom: calc(20px + env(safe-area-inset-bottom, 0px)); width: 46px; height: 46px; border-radius: 50%; background: var(--pm-surface); border: 1px solid var(--pm-line); box-shadow: var(--pm-shadow); display: none; place-items: center; z-index: 1030; font-size: 18px; }
    #pm .pm-up.show { display: grid; }
    #pm.selmode .pm-fab, #pm.selmode .pm-up { display: none; }

    /* Barra de seleção em lote */
    #pm .pm-selbar { position: fixed; left: 0; right: 0; bottom: 0; background: var(--pm-ink); color: #fff; padding: 10px 12px calc(10px + env(safe-area-inset-bottom, 0px)); display: none; flex-direction: column; gap: 8px; z-index: 1031; }
    #pm.selmode .pm-selbar { display: flex; }
    #pm .pm-sr1 { display: flex; align-items: center; gap: 8px; }
    #pm .pm-sr1 .pm-cnt { flex: 1; font-weight: 800; font-size: 14.5px; text-align: center; }
    #pm .pm-sr2 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
    #pm .pm-sbtn { height: 44px; border-radius: 11px; background: rgba(128,128,128,.28) !important; font-weight: 700; font-size: 13.5px; display: flex; align-items: center; justify-content: center; padding: 0 8px !important; text-align: center; color: #fff !important; }
    #pm .pm-sbtn.pm-s { height: 36px; padding: 0 12px !important; }

    /* Gavetas (filtros / mover / opções) */
    #pm .pm-veil { position: fixed; inset: 0; background: rgba(10,15,25,.5); opacity: 0; pointer-events: none; transition: opacity .2s; z-index: 1044; }
    #pm .pm-veil.open { opacity: 1; pointer-events: auto; }
    #pm .pm-sheet { position: fixed; left: 0; right: 0; bottom: 0; max-height: 94%; background: var(--pm-surface); border-radius: 20px 20px 0 0; transform: translateY(105%); transition: transform .28s cubic-bezier(.2,.8,.2,1); z-index: 1045; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 -10px 40px rgba(0,0,0,.25); visibility: hidden; }
    #pm .pm-sheet.tall { height: 94%; }
    #pm .pm-sheet.open { transform: none; visibility: visible; }
    #pm .pm-grab { width: 40px; height: 5px; border-radius: 3px; background: var(--pm-line); margin: 8px auto 4px; flex: none; }
    #pm .pm-sh { display: flex; align-items: center; gap: 10px; padding: 8px 14px 12px; flex: none; }
    #pm .pm-sh h2 { margin: 0; font-size: 18px; font-weight: 800; flex: 1; line-height: 1.25; overflow-wrap: anywhere; }
    #pm .pm-sh .lnk { color: var(--pm-accent); font-weight: 700; font-size: 14px; height: 40px; padding: 0 6px; }
    #pm .pm-sbody { flex: 1; overflow-y: auto; overflow-x: hidden; -webkit-overflow-scrolling: touch; }
    #pm .pm-sf { display: flex; gap: 10px; padding: 10px 14px calc(12px + env(safe-area-inset-bottom, 0px)); border-top: 1px solid var(--pm-line); flex: none; background: var(--pm-surface); }
    #pm .pm-sf .pm-apply { flex: 1; height: 50px; border-radius: 12px; font-weight: 800; font-size: 15px; background: var(--pm-accent); color: #fff; display: flex; align-items: center; justify-content: center; gap: 8px; text-align: center; }
    #pm .pm-fs { border-bottom: 1px solid var(--pm-line); }
    #pm .pm-fh { width: 100%; display: flex; align-items: center; gap: 10px; padding: 0 16px; min-height: 58px; }
    #pm .pm-fh .t { font-weight: 700; font-size: 15.5px; }
    #pm .pm-fh .s { flex: 1; text-align: right; font-size: 13px; font-weight: 600; color: var(--pm-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; min-width: 0; }
    #pm .pm-fh .s.on { color: var(--pm-accent); }
    #pm .pm-fh .chev { color: var(--pm-muted); transition: transform .2s; }
    #pm .pm-fs.open .pm-fh .chev { transform: rotate(180deg); }
    #pm .pm-fo { display: none; padding: 0 16px 16px; flex-wrap: wrap; gap: 8px; }
    #pm .pm-fs.open .pm-fo { display: flex; }
    #pm .pm-fc { height: 44px; padding: 0 14px !important; border-radius: 22px; border: 1.5px solid var(--pm-line) !important; background: var(--pm-surface) !important; font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 8px; max-width: 100%; }
    #pm .pm-fc > span:not(.dot):not(.pm-av) { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    #pm .pm-fc .dot { width: 10px; height: 10px; border-radius: 50%; background: var(--c); flex: none; }
    #pm .pm-fc.on { background: var(--pm-accent-soft) !important; border-color: var(--pm-accent) !important; color: var(--pm-accent); }
    #pm .pm-av { width: 24px; height: 24px; border-radius: 50%; display: grid; place-items: center; font-size: 10px; font-weight: 800; color: #fff; flex: none; }
    #pm .pm-opts { display: flex; flex-direction: column; padding: 2px 10px 10px; }
    #pm .pm-opt { height: 56px; padding: 0 12px !important; border-radius: 12px; display: flex; align-items: center; gap: 12px; font-weight: 700; font-size: 15.5px; width: 100%; }
    #pm .pm-opt:active { background: var(--pm-bg) !important; }
    #pm .pm-opt .dot { width: 12px; height: 12px; border-radius: 50%; background: var(--c); flex: none; }
    #pm .pm-opt .ck { margin-left: auto; color: var(--pm-accent); font-size: 20px; }
    #pm .pm-ib-close { width: 44px; height: 44px; border-radius: 12px; border: 1px solid var(--pm-line) !important; display: grid; place-items: center; font-size: 18px; flex: none; }

    /* Aviso rápido (com Desfazer) */
    #pm .pm-toast { position: fixed; left: 50%; bottom: calc(88px + env(safe-area-inset-bottom, 0px)); transform: translate(-50%, 20px); background: var(--pm-ink); color: #fff; padding: 11px 16px; border-radius: 12px; font-weight: 700; font-size: 13.5px; opacity: 0; pointer-events: none; transition: .25s; z-index: 1090; display: flex; gap: 14px; align-items: center; width: max-content; max-width: 92%; }
    #pm .pm-toast.show { opacity: 1; transform: translate(-50%, 0); pointer-events: auto; }
    #pm .pm-toast button { color: var(--pm-accent); font-weight: 800; flex: none; }
    #pm.selmode .pm-toast { bottom: calc(130px + env(safe-area-inset-bottom, 0px)); }

    /* ===== Botões grandes de Status/Prioridade (detalhe e criação) ===== */
    .pm-seg { display: grid; gap: 8px; }
    .pm-seg.pm-seg-c2 { grid-template-columns: 1fr 1fr; }
    .pm-seg.pm-seg-c4 { grid-template-columns: repeat(4, 1fr); }
    .pm-seg button { height: 46px; border-radius: 12px; border: 1.5px solid #e4e7ee; font-weight: 700; font-size: 13.5px; background: #fff; color: #667085; display: flex; align-items: center; justify-content: center; gap: 7px; padding: 0 6px; line-height: 1.15; text-align: center; }
    .pm-seg button .dot { width: 9px; height: 9px; border-radius: 50%; background: var(--c); flex: none; }
    .pm-seg button[aria-pressed="true"] { background: var(--c); border-color: var(--c); color: #fff; }
    .pm-seg button[aria-pressed="true"] .dot { background: #fff; }

    /* ===== Detalhe do card como gaveta (bottom sheet) ===== */
    #cardDetailModal .modal-dialog,
    #createCardModal .modal-dialog { position: fixed; left: 0; right: 0; bottom: 0; top: auto; margin: 0; width: 100%; max-width: none; }
    #cardDetailModal .modal-dialog { height: 94%; height: 94dvh; }
    #cardDetailModal.fade .modal-dialog,
    #createCardModal.fade .modal-dialog { transform: translateY(100%); transition: transform .28s cubic-bezier(.2,.8,.2,1); }
    #cardDetailModal.show .modal-dialog,
    #createCardModal.show .modal-dialog { transform: none; }
    #cardDetailModal .modal-dialog .modal-content,
    #createCardModal .modal-dialog .modal-content { height: 100%; border: 0; border-radius: 20px 20px 0 0; overflow: hidden; box-shadow: 0 -10px 40px rgba(0,0,0,.25); }
    #cardDetailModal .modal-header.card-detail-header { position: relative; background: #eceff3; flex-wrap: nowrap; align-items: flex-start; gap: 8px; padding: 20px 14px 12px; border-bottom: 1px solid #e4e7ee !important; }
    #cardDetailModal .card-detail-header::before,
    #createCardModal .modal-header::before { content: ""; position: absolute; top: 8px; left: 50%; width: 40px; height: 5px; margin-left: -20px; border-radius: 3px; background: #d5d9e0; }
    #cardDetailModal .modal-header .card-detail-title-wrap { flex: 1 1 0; min-width: 0; align-items: flex-start !important; }
    #cardDetailModal #detail-id-badge { background: #5b6573 !important; color: #fff; font-weight: 800; font-size: 14px; border-radius: 9px; padding: 5px 9px; margin-top: 2px; flex: none; }
    #cardDetailModal #detail-title { font-size: 18px; line-height: 1.25; font-weight: 800; padding-top: 2px; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; overflow-wrap: anywhere; }
    #cardDetailModal .card-detail-badges { flex-direction: column; align-items: flex-end !important; gap: 5px !important; flex: none; }
    #cardDetailModal .card-detail-badges .badge { font-size: 12px; font-weight: 800; padding: 4px 11px; border-radius: 13px; }
    #cardDetailModal .modal-header .card-detail-close { width: 40px; height: 40px; padding: 0; margin: -4px -6px 0 0; flex: none; background-size: 14px; }
    #cardDetailModal .card-detail-body { flex: 1 1 auto; overflow-y: auto; -webkit-overflow-scrolling: touch; }
    #cardDetailModal .card-detail-body > .row { height: auto !important; }
    /* Abas em grade de 3 colunas (como no protótipo) */
    #cardDetailModal #cardTabs { display: grid; grid-template-columns: repeat(3, 1fr); padding: 0 !important; border-bottom: 1px solid #e4e7ee !important; }
    #cardDetailModal #cardTabs .nav-item { min-width: 0; }
    #cardDetailModal #cardTabs .nav-link { height: 46px; width: 100%; display: flex; align-items: center; justify-content: center; gap: 5px; font-weight: 600; font-size: 13px !important; color: #667085; border: 0; border-bottom: 3px solid transparent; border-radius: 0; padding: 0 2px !important; white-space: nowrap; }
    #cardDetailModal #cardTabs .nav-link.active { color: var(--primary, #00bfa6); border-bottom-color: var(--primary, #00bfa6); font-weight: 600; }
    #cardDetailModal #cardTabs .nav-link .badge { font-size: 11px !important; font-weight: 800; background: #5b6573 !important; border-radius: 7px; padding: 2px 7px; margin-left: 0 !important; }
    #cardDetailModal #cardTabs #tab-demanda-badge { background: var(--primary, #00bfa6) !important; }
    #cardDetailModal .tab-content { padding: 14px !important; }
    #cardDetailModal #quill-editor { min-height: 140px !important; }
    #cardDetailModal .ql-toolbar.ql-snow { border-radius: 12px 12px 0 0; }
    #cardDetailModal .ql-container.ql-snow { border-radius: 0 0 12px 12px; }
    /* Propriedades */
    #cardDetailModal #card-detail-props { background: #f5f6fa !important; margin-top: 14px; }
    #cardDetailModal #card-detail-props > .p-3 { padding: 16px 14px 24px !important; }
    #cardDetailModal .card-detail-props-title { font-size: 15px !important; font-weight: 800; color: #1f2937 !important; letter-spacing: .02em; }
    #cardDetailModal #card-detail-props .form-label { font-size: 13px !important; font-weight: 600; color: #667085 !important; margin-bottom: 6px; }
    #cardDetailModal #card-detail-props .mb-3 { margin-bottom: 16px !important; }
    #cardDetailModal .form-control,
    #cardDetailModal .form-select,
    #createCardModal .form-control,
    #createCardModal .form-select { min-height: 50px; border-radius: 12px; border-color: #e4e7ee; font-size: 16px; padding-left: 12px; }
    #cardDetailModal textarea.form-control,
    #createCardModal textarea.form-control { min-height: 50px; padding-top: 12px; line-height: 1.45; }
    #cardDetailModal .input-group > .btn { border-color: #e4e7ee; }
    #cardDetailModal .input-group-sm > .form-control { min-height: 50px; }
    #cardDetailModal .ql-editor { font-size: 16px; }
    #cardDetailModal #detail-comments,
    #cardDetailModal #detail-history,
    #cardDetailModal #ticket-messages-list,
    #cardDetailModal #ticket-internal-notes-list { max-height: none !important; overflow: visible !important; }
    #cardDetailModal .btn-sm { padding: 8px 12px; }
    /* Rodapé fixo: Excluir + Salvar */
    #cardDetailModal .card-detail-mobilebar,
    #createCardModal .modal-footer { flex-wrap: nowrap; gap: 10px; padding: 10px 14px calc(12px + env(safe-area-inset-bottom, 0px)); border-top: 1px solid #e4e7ee; background: #fff; }
    #cardDetailModal .card-detail-mobilebar > *,
    #createCardModal .modal-footer > * { margin: 0; }
    #cardDetailModal .pm-btn { height: 50px; border-radius: 12px; font-weight: 800; font-size: 15px; display: flex; align-items: center; justify-content: center; gap: 8px; }
    #cardDetailModal .pm-btn-pri { flex: 1; background: var(--primary, #00bfa6); border-color: var(--primary, #00bfa6); color: #fff; }
    #cardDetailModal .pm-btn-pri:hover, #cardDetailModal .pm-btn-pri:focus { background: var(--primary-dark, #00997d); color: #fff; }
    #cardDetailModal .pm-btn-danger { flex: 0 0 auto; padding: 0 18px; color: #d6293e; border: 1px solid #e4e7ee; background: #fff; font-size: 18px; }

    /* ===== Novo card como gaveta ===== */
    #createCardModal .modal-dialog .modal-content { height: auto; max-height: 94vh; max-height: 94dvh; display: flex; flex-direction: column; }
    #createCardModal form { display: flex; flex-direction: column; min-height: 0; flex: 1 1 auto; }
    #createCardModal .modal-header { position: relative; padding: 22px 14px 12px; border-bottom: 0; }
    #createCardModal .modal-title { font-size: 18px; font-weight: 800; }
    #createCardModal .modal-body { overflow-y: auto; -webkit-overflow-scrolling: touch; background: #f5f6fa; padding: 16px 14px; }
    #createCardModal .modal-body .form-label { font-size: 13px; font-weight: 600; color: #667085; margin-bottom: 6px; }
    #createCardModal .modal-footer .btn-outline-secondary { display: none; }
    #createCardModal .modal-footer .btn-primary { flex: 1; height: 50px; border-radius: 12px; font-weight: 800; font-size: 15px; }
}
/* Celular estreito: etiquetas (prioridade/status) descem para baixo do título,
   para o título não ficar espremido; abas do detalhe sem ícone. */
@media (max-width: 399.98px) {
    #cardDetailModal .modal-header.card-detail-header { flex-wrap: wrap; }
    #cardDetailModal .modal-header .card-detail-title-wrap { order: 1; }
    #cardDetailModal .modal-header .card-detail-close { order: 2; }
    #cardDetailModal .card-detail-badges { order: 3; flex: 1 1 100%; flex-direction: row; flex-wrap: wrap; justify-content: flex-start; align-items: center !important; gap: 6px !important; }
    #cardDetailModal #detail-title { overflow-wrap: break-word; }
    #cardDetailModal #cardTabs .nav-link > .bi { display: none; }
    #cardDetailModal #cardTabs .nav-link { font-size: 12.5px !important; }
}
@media (max-width: 359.98px) {
    #pm .pm-tl { padding: 0 9px; }
    #pm .pm-tl .bi { display: none; }
    .pm-seg.pm-seg-c4 { grid-template-columns: 1fr 1fr; }
}
@media (min-width: 360px) and (max-width: 399.98px) {
    .pm-seg.pm-seg-c4 button { font-size: 12.5px; gap: 5px; }
}
</style>
