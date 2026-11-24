<?php
/**
 * Editor de texto formatado (negrito, itálico, listas, links...) + pré-visualização
 * do e-mail com o layout do sistema. Reutilizado na tela de Templates, na
 * Prospecção E-mail e no bloco "Enviar e-mail" das sequências.
 *
 * Inclua uma vez na página (depois do _var_picker.php, se usar variáveis):
 *   RichEditor.mount(el, { html, placeholder, onChange(html), chips: elDosChips })
 *     → { get(), set(html), focus(), quill }
 *   openEmailPreview({ subject, body, contact_id })
 */
?>
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<style>
.rich-editor-wrap .ql-toolbar { border-radius: 6px 6px 0 0; background:#fafbfc; }
.rich-editor-wrap .ql-container { border-radius: 0 0 6px 6px; font-size: 0.9rem; background:#fff; }
.rich-editor-wrap .ql-editor { min-height: 180px; line-height: 1.6; }
.rich-editor-wrap .ql-editor p { margin-bottom: 0; }
#emailPreviewModal { z-index: 2100; } /* acima do painel lateral das sequências */
#emailPreviewModal iframe { width:100%; height:65vh; border:0; background:#f5f7fa; border-radius:6px; }
</style>

<!-- Pré-visualização do e-mail (como chega para o lead) -->
<div class="modal fade" id="emailPreviewModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <div style="min-width:0;">
                    <h6 class="modal-title mb-0"><i class="bi bi-eye"></i> Como o e-mail chega</h6>
                    <small class="text-muted d-block text-truncate" id="ep-subject"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-2">
                <div class="small text-muted mb-2" id="ep-note"></div>
                <iframe id="ep-frame" title="Pré-visualização do e-mail" sandbox="allow-same-origin"></iframe>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const RE_BASE = '<?= baseUrl('') ?>';
    const TOOLBAR = [
        [{ header: [1, 2, 3, false] }],
        ['bold', 'italic', 'underline', 'strike'],
        [{ color: [] }, { background: [] }],
        [{ list: 'ordered' }, { list: 'bullet' }],
        [{ align: [] }],
        ['link', 'blockquote'],
        ['clean']
    ];
    const isHtml = (s) => /<\s*(p|br|div|span|strong|b|em|i|u|a|ul|ol|li|h[1-6]|blockquote)\b[^>]*>/i.test(String(s || ''));

    window.RichEditor = {
        mount(el, opts) {
            opts = opts || {};
            const wrap = document.createElement('div');
            wrap.className = 'rich-editor-wrap';
            el.innerHTML = '';
            el.appendChild(wrap);
            const holder = document.createElement('div');
            wrap.appendChild(holder);
            const quill = new Quill(holder, { theme: 'snow', placeholder: opts.placeholder || 'Escreva a mensagem...', modules: { toolbar: TOOLBAR } });
            const api = {
                quill,
                get() {
                    const html = quill.root.innerHTML;
                    return (html === '<p><br></p>') ? '' : html;
                },
                set(html) {
                    html = String(html || '');
                    if (!html) quill.setContents([]);
                    else if (isHtml(html)) quill.clipboard.dangerouslyPasteHTML(html);
                    else quill.setText(html);
                },
                focus() { quill.focus(); },
                insertVar(k) {
                    const range = quill.getSelection(true);
                    const at = range ? range.index : quill.getLength() - 1;
                    quill.insertText(at, '{{' + k + '}}', 'user');
                    quill.setSelection(at + k.length + 4, 0);
                }
            };
            api.set(opts.html || '');
            if (typeof opts.onChange === 'function') quill.on('text-change', () => opts.onChange(api.get()));
            if (opts.chips && window.VAR_LIST) {
                opts.chips.innerHTML = '<div class="d-flex flex-wrap gap-1 mb-1">' + window.VAR_LIST.map(v =>
                    `<button type="button" class="btn btn-sm btn-light border py-0 px-1 re-var" data-k="${v.k}" title="${v.label}" style="font-size:0.72rem;"><code>${v.k}</code></button>`
                ).join('') + '</div>';
                opts.chips.querySelectorAll('.re-var').forEach(b => b.onclick = () => api.insertVar(b.dataset.k));
            }
            el._richEditor = api;
            return api;
        }
    };

    let epModal = null;
    window.openEmailPreview = function (data) {
        data = data || {};
        if (!epModal) epModal = new bootstrap.Modal(document.getElementById('emailPreviewModal'));
        const frame = document.getElementById('ep-frame');
        document.getElementById('ep-subject').textContent = data.subject ? ('Assunto: ' + data.subject) : '';
        document.getElementById('ep-note').textContent = 'Carregando...';
        frame.srcdoc = '';
        epModal.show();
        const fd = new FormData();
        fd.append('subject', data.subject || '');
        fd.append('body', data.body || '');
        if (data.contact_id) fd.append('contact_id', data.contact_id);
        if (data.account_id) fd.append('account_id', data.account_id);
        fetch(RE_BASE + 'prospection/templatePreview', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(d => {
                if (d.error) { document.getElementById('ep-note').textContent = d.error; return; }
                document.getElementById('ep-subject').textContent = 'Assunto: ' + (d.subject || '(sem assunto)');
                document.getElementById('ep-note').textContent = d.note || '';
                frame.srcdoc = d.html || '';
            })
            .catch(() => { document.getElementById('ep-note').textContent = 'Não foi possível gerar a pré-visualização.'; });
    };
})();
</script>
