<?php
/**
 * Componente reutilizável de inserção de variáveis em campos de texto.
 * Inclua uma vez na página; depois chame attachVarPicker(el) em cada input/textarea.
 * Variáveis disponíveis sempre (independente da fonte: Apollo, manual, import...).
 */
?>
<script>
// Lista canônica de variáveis (rótulo → token). Sempre disponível.
window.VAR_LIST = [
    { k: 'nome',            label: 'Nome completo' },
    { k: 'primeiro_nome',   label: 'Primeiro nome' },
    { k: 'email',           label: 'E-mail' },
    { k: 'telefone',        label: 'Telefone' },
    { k: 'empresa',         label: 'Empresa' },
    { k: 'cargo',           label: 'Cargo' },
    { k: 'cidade',          label: 'Cidade' },
    { k: 'estado',          label: 'Estado' },
    { k: 'setor',           label: 'Setor / Indústria' },
    { k: 'linkedin',        label: 'LinkedIn' },
];

// Insere um token na posição do cursor de um input/textarea
function insertVar(el, token) {
    if (!el) return;
    el.focus();
    const start = el.selectionStart ?? el.value.length;
    const end = el.selectionEnd ?? el.value.length;
    const t = '{{' + token + '}}';
    el.value = el.value.slice(0, start) + t + el.value.slice(end);
    const pos = start + t.length;
    el.setSelectionRange(pos, pos);
    el.dispatchEvent(new Event('input', { bubbles: true }));
}

// Menu flutuante de variáveis (usado pelo botão direito e pelo botão "Variáveis")
let _varMenu = null;
function showVarMenu(x, y, targetEl) {
    hideVarMenu();
    _varMenu = document.createElement('div');
    _varMenu.className = 'var-menu';
    _varMenu.style.cssText = `position:fixed;left:${x}px;top:${y}px;z-index:3000;background:#fff;border:1px solid #dee2e6;
        border-radius:8px;box-shadow:0 6px 20px rgba(0,0,0,.15);padding:4px;min-width:190px;max-height:280px;overflow:auto;font-size:0.82rem;`;
    _varMenu.innerHTML = '<div style="padding:4px 10px;color:#888;font-size:0.7rem;text-transform:uppercase;">Inserir variável</div>' +
        window.VAR_LIST.map(v => `<div class="var-item" data-k="${v.k}" style="padding:6px 10px;cursor:pointer;border-radius:6px;">
            <code style="color:#00997D;">{{${v.k}}}</code> <span class="text-muted">${v.label}</span></div>`).join('');
    document.body.appendChild(_varMenu);
    _varMenu.querySelectorAll('.var-item').forEach(it => {
        it.addEventListener('mouseenter', ()=> it.style.background = '#f0faf8');
        it.addEventListener('mouseleave', ()=> it.style.background = '');
        it.addEventListener('mousedown', (e)=>{ e.preventDefault(); insertVar(targetEl, it.dataset.k); hideVarMenu(); });
    });
    // Ajusta se sair da tela
    const r = _varMenu.getBoundingClientRect();
    if (r.right > window.innerWidth) _varMenu.style.left = (window.innerWidth - r.width - 8) + 'px';
    if (r.bottom > window.innerHeight) _varMenu.style.top = (window.innerHeight - r.height - 8) + 'px';
}
function hideVarMenu() { if (_varMenu) { _varMenu.remove(); _varMenu = null; } }
document.addEventListener('click', (e)=>{ if (_varMenu && !e.target.closest('.var-menu')) hideVarMenu(); });

// Liga o menu de contexto (botão direito) a um campo
function attachVarPicker(el) {
    if (!el || el._varBound) return;
    el._varBound = true;
    el.addEventListener('contextmenu', (e)=>{ e.preventDefault(); showVarMenu(e.clientX, e.clientY, el); });
}

// Renderiza uma barra de "chips" clicáveis que inserem a variável no campo alvo
function varChipsHtml() {
    return '<div class="var-chips d-flex flex-wrap gap-1 mb-1">' +
        window.VAR_LIST.map(v => `<button type="button" class="btn btn-sm btn-light border py-0 px-1 var-chip" data-k="${v.k}" title="${v.label}" style="font-size:0.72rem;"><code>${v.k}</code></button>`).join('') +
        '</div>';
}
// Liga os chips de um container ao campo alvo
function bindVarChips(container, targetEl) {
    if (!container) return;
    container.querySelectorAll('.var-chip').forEach(c => {
        c.onclick = () => insertVar(targetEl, c.dataset.k);
    });
    attachVarPicker(targetEl);
}
</script>
