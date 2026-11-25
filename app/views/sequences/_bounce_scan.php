<?php
/**
 * "Verificar e-mails devolvidos": revê as últimas mensagens das caixas de
 * e-mail, acha os e-mails que voltaram (endereço não existe) e tira esses leads
 * das sequências. O cron faz isso sozinho com as mensagens novas; o botão serve
 * para conferir na hora e para as devoluções que chegaram antes.
 * Uso: scanBounces(botao, callbackOpcional).
 */
?>
<div class="modal fade" id="bounceScanModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-envelope-x"></i> E-mails devolvidos</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="bounce-scan-body"></div>
            <div class="modal-footer">
                <small class="text-muted me-auto">O sistema também confere sozinho a cada execução das sequências.</small>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>
<style>
#bounce-scan-body .bs-item { border:1px solid #eee; border-radius:8px; padding:8px 10px; margin-bottom:8px; font-size:0.83rem; word-break:break-word; }
#bounce-scan-body .bs-item.hard { border-color:#f5c2c7; background:#fff6f6; }
</style>
<script>
function scanBounces(btn, onDone) {
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Verificando...';
    const body = document.getElementById('bounce-scan-body');
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('bounceScanModal'));
    fetch('<?= baseUrl('sequences/scanBounces') ?>', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json())
        .then(d => {
            btn.disabled = false; btn.innerHTML = orig;
            if (d.error) { body.innerHTML = `<div class="alert alert-warning mb-0">${esc(d.error)}</div>`; modal.show(); return; }
            const list = d.bounces || [];
            const hard = list.filter(b => b.hard);
            const newHard = hard.filter(b => !b.already);
            let h = `<p class="small mb-2">Conferi as últimas ${d.checked || 0} mensagens de ${d.accounts || 0} ${d.accounts === 1 ? 'caixa' : 'caixas'}.</p>`;
            if (!list.length) {
                h += '<div class="alert alert-success py-2 mb-0"><i class="bi bi-check-circle"></i> Nenhum e-mail de prospecção voltou.</div>';
            } else {
                h += `<div class="alert ${newHard.length ? 'alert-danger' : 'alert-light border'} py-2">`
                   + (newHard.length
                        ? `<strong>${newHard.length} ${newHard.length === 1 ? 'lead tirado' : 'leads tirados'} das sequências</strong> porque o e-mail não existe. Novos e-mails para ${newHard.length === 1 ? 'ele ficam' : 'eles ficam'} bloqueados.`
                        : 'Nenhum lead novo para tirar: as devoluções abaixo já tinham sido tratadas ou são temporárias.')
                   + '</div>';
                h += list.map(b => `<div class="bs-item ${b.hard ? 'hard' : ''}">
                        <div><strong>${esc(b.contact_name || b.email)}</strong> <span class="text-muted">${esc(b.email)}</span>
                        ${b.hard ? '<span class="badge bg-danger">E-mail inválido</span>' : '<span class="badge bg-secondary">Temporário</span>'}
                        ${b.already ? '<span class="badge bg-light text-dark border">já tratado antes</span>' : ''}</div>
                        <div class="text-muted">${esc(b.reason)}${b.hard && !b.already && b.stopped ? ` · saiu de ${b.stopped} ${b.stopped === 1 ? 'sequência' : 'sequências'}` : ''}</div>
                    </div>`).join('');
            }
            if ((d.errors || []).length) {
                h += `<div class="alert alert-warning py-2 mt-2 mb-0 small"><strong>Não consegui ler:</strong><br>${d.errors.map(esc).join('<br>')}</div>`;
            }
            body.innerHTML = h;
            modal.show();
            if (typeof onDone === 'function') onDone(d);
        })
        .catch(() => { btn.disabled = false; btn.innerHTML = orig; alert('Não foi possível verificar agora. Tente de novo em instantes.'); });
}
</script>
