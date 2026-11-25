<?php
/**
 * Formatação de mensagens de WhatsApp: barra com negrito, itálico, tachado,
 * monoespaçado, listas (tópicos e numerada), citação e emojis de destaque, mais
 * a prévia de como a mensagem aparece no WhatsApp.
 *
 * O WhatsApp formata pelos marcadores no próprio texto (*negrito*, _itálico_,
 * ~tachado~, ```mono```). Então o que é salvo continua sendo texto puro e vai
 * para o lead do jeito que está. O WhatsApp NÃO tem cor nem tamanho de letra:
 * para destacar, a barra oferece emojis coloridos.
 *
 * Inclua uma vez na página. Uso:
 *   WaEditor.attach(textarea, { toolbar: elDaBarra, preview: elDaPrevia }) → { refresh() }
 *   WaFormat.toHtml(texto)      HTML da prévia (escapado; variáveis com dados de exemplo)
 *   WaFormat.strip(texto)       texto sem marcadores (resumos/cartões)
 *   WaFormat.fromHtml(html)     HTML do e-mail → texto com marcadores do WhatsApp
 *   WaFormat.toEmailHtml(texto) texto do WhatsApp → HTML para o editor de e-mail
 */
?>
<style>
.wa-toolbar { display:flex; flex-wrap:wrap; gap:2px; align-items:center; border:1px solid #dee2e6; border-bottom:0; border-radius:6px 6px 0 0; background:#fafbfc; padding:3px 4px; }
.wa-toolbar .wa-btn { padding:2px 7px; font-size:0.9rem; line-height:1.4; border:0; border-radius:4px; background:transparent; color:#444; }
.wa-toolbar .wa-btn:hover, .wa-toolbar .wa-btn:focus-visible { background:#e9ecef; color:#000; }
.wa-toolbar .wa-sep { width:1px; height:18px; background:#dee2e6; margin:0 3px; }
.wa-toolbar:not([hidden]) + textarea { border-top-left-radius:0; border-top-right-radius:0; }
.wa-emoji-menu { min-width:0; width:230px; padding:6px; z-index:2200; }
.wa-emoji-menu .wa-emoji-grid { display:grid; grid-template-columns:repeat(6, 1fr); gap:2px; }
.wa-emoji-menu .wa-emoji { border:0; background:transparent; font-size:1.15rem; line-height:1.6; border-radius:4px; padding:0; }
.wa-emoji-menu .wa-emoji:hover { background:#f0f2f5; }
.wa-emoji-menu .wa-emoji-note { font-size:0.72rem; color:#6c757d; margin-top:4px; line-height:1.3; }
.wa-preview-box { background:#efeae2; border-radius:8px; padding:10px; }
.wa-bubble { background:#d9fdd3; border-radius:8px 0 8px 8px; padding:6px 9px 3px; margin-left:auto; width:fit-content; max-width:100%; min-width:70px;
    font-size:0.88rem; line-height:1.42; color:#111b21; box-shadow:0 1px .5px rgba(11,20,26,.13); overflow-wrap:anywhere; word-break:break-word; }
.wa-bubble .wa-line { min-height:1.2em; white-space:pre-wrap; }
.wa-bubble .wa-li { display:flex; gap:6px; white-space:pre-wrap; }
.wa-bubble .wa-li > .wa-mark { flex-shrink:0; }
.wa-bubble .wa-quote { border-left:3px solid #06cf9c; padding:1px 0 1px 8px; color:#54656f; white-space:pre-wrap; }
.wa-bubble pre { font-family:Consolas, 'Courier New', monospace; font-size:0.82rem; background:transparent; margin:0; white-space:pre-wrap; color:#111b21; }
.wa-bubble code { font-family:Consolas, 'Courier New', monospace; font-size:0.82rem; background:rgba(0,0,0,.06); border-radius:3px; padding:0 3px; color:#111b21; }
.wa-bubble .wa-var { background:rgba(0,153,125,.12); border-radius:3px; }
.wa-bubble .wa-time { text-align:right; font-size:0.68rem; color:#667781; margin-top:1px; }
.wa-bubble .wa-empty { color:#8696a0; font-style:italic; }
</style>
<script>
(function () {
    // Dados de exemplo da prévia (mesmos do preview de e-mail: MessageTemplate::sampleContact)
    const SAMPLE = {
        nome: 'Maria Souza', primeiro_nome: 'Maria', email: 'maria@empresaexemplo.com.br', telefone: '(41) 99999-0000',
        empresa: 'Empresa Exemplo', cargo: 'Diretora Comercial', cidade: 'Curitiba', estado: 'PR', setor: 'Tecnologia',
        linkedin: 'https://www.linkedin.com/in/maria-souza'
    };
    const esc = (s) => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    // Marcador só vale encostado no texto (*texto*), igual ao WhatsApp.
    const inlineRe = (m) => {
        const c = '\\' + m;
        return new RegExp('(^|[^0-9A-Za-zÀ-ÿ' + c + '])' + c + '(?=\\S)([^' + c + '\\n]*?\\S)' + c + '(?=[^0-9A-Za-zÀ-ÿ' + c + ']|$)', 'g');
    };
    const RE_BOLD = inlineRe('*'), RE_ITAL = inlineRe('_'), RE_STRK = inlineRe('~');
    const RX = { bullet: /^\s*[•\-*]\s+/, number: /^\s*(\d+)[.)]\s+/, quote: /^>\s?/ };

    // Formatação de linha já escapada (marcadores * _ ~ ` não são afetados pelo escape)
    function inline(s, tags) {
        tags = tags || { b: 'strong', i: 'em', s: 's' };
        s = s.replace(/`([^`\n]+)`/g, '<code>$1</code>');
        for (let k = 0; k < 2; k++) { // 2 passadas: permite *_negrito e itálico_*
            s = s.replace(RE_BOLD, '$1<' + tags.b + '>$2</' + tags.b + '>')
                 .replace(RE_ITAL, '$1<' + tags.i + '>$2</' + tags.i + '>')
                 .replace(RE_STRK, '$1<' + tags.s + '>$2</' + tags.s + '>');
        }
        return s;
    }
    // Troca as variáveis ANTES de formatar (é o que acontece no envio real). Usa
    // marcadores neutros (sem "_") para o nome da variável não virar itálico.
    const KEYS = Object.keys(SAMPLE);
    function sampleText(raw) {
        return String(raw || '').replace(/\{\{\s*([a-z_]+)\s*\}\}/gi, (m, k) => {
            const i = KEYS.indexOf(k.toLowerCase());
            return i >= 0 ? '\u0001' + i + '\u0002' : m;
        });
    }
    function unsample(html) {
        return html.replace(/\u0001(\d+)\u0002/g, (m, i) => '<span class="wa-var">' + esc(SAMPLE[KEYS[+i]]) + '</span>');
    }

    window.WaFormat = {
        toHtml(text) {
            const src = sampleText(String(text || '').replace(/\r\n?/g, '\n'));
            if (!src.trim()) return '';
            const parts = src.split('```');
            let html = '';
            parts.forEach((part, i) => {
                const isMono = i % 2 === 1 && i < parts.length - 1;
                if (isMono) { html += '<pre>' + esc(part.replace(/^\n/, '').replace(/\n$/, '')) + '</pre>'; return; }
                if (i % 2 === 1) part = '```' + part; // ``` sem fechamento: fica como texto
                const lines = part.split('\n');
                // As quebras ao redor do bloco ``` pertencem a ele
                if (i > 0 && lines.length > 1 && lines[0] === '') lines.shift();
                if (i < parts.length - 1 && lines.length > 1 && lines[lines.length - 1] === '') lines.pop();
                lines.forEach(line => {
                    let m;
                    if ((m = line.match(RX.quote))) {
                        html += '<div class="wa-quote">' + inline(esc(line.slice(m[0].length))) + '</div>';
                    } else if ((m = line.match(RX.bullet))) {
                        html += '<div class="wa-li"><span class="wa-mark">•</span><span>' + inline(esc(line.slice(m[0].length))) + '</span></div>';
                    } else if ((m = line.match(RX.number))) {
                        html += '<div class="wa-li"><span class="wa-mark">' + m[1] + '.</span><span>' + inline(esc(line.slice(m[0].length))) + '</span></div>';
                    } else {
                        html += '<div class="wa-line">' + inline(esc(line)) + '</div>';
                    }
                });
            });
            return unsample(html);
        },
        strip(text) {
            let s = String(text || '').replace(/```/g, '');
            for (let k = 0; k < 2; k++) s = s.replace(RE_BOLD, '$1$2').replace(RE_ITAL, '$1$2').replace(RE_STRK, '$1$2');
            return s.replace(/`([^`\n]+)`/g, '$1');
        },
        // E-mail → WhatsApp: mantém negrito/itálico/tachado/listas/citação como marcadores
        fromHtml(html) {
            const root = document.createElement('div');
            root.innerHTML = String(html || '');
            const wrap = (inner, m) => {
                const t = inner.match(/^(\s*)([\s\S]*?)(\s*)$/);
                return t[2] ? t[1] + m + t[2] + m + t[3] : inner;
            };
            const walk = (node) => {
                if (node.nodeType === 3) return node.nodeValue.replace(/\s*\n\s*/g, ' ');
                if (node.nodeType !== 1) return '';
                const tag = node.tagName.toLowerCase();
                const kids = () => Array.from(node.childNodes).map(walk).join('');
                switch (tag) {
                    case 'br': return '\n';
                    case 'strong': case 'b': return wrap(kids(), '*');
                    case 'em': case 'i': return wrap(kids(), '_');
                    case 's': case 'strike': case 'del': return wrap(kids(), '~');
                    case 'h1': case 'h2': case 'h3': case 'h4': return wrap(kids(), '*') + '\n';
                    case 'a': {
                        const txt = kids(), href = node.getAttribute('href') || '';
                        return href && href !== txt && !/^mailto:/i.test(href) ? txt + ' (' + href + ')' : txt;
                    }
                    case 'ul': case 'ol': {
                        let n = 0;
                        return Array.from(node.children).filter(li => li.tagName.toLowerCase() === 'li').map(li => {
                            const ordered = tag === 'ol' && li.getAttribute('data-list') !== 'bullet';
                            n++;
                            return (ordered ? n + '. ' : '• ') + Array.from(li.childNodes).map(walk).join('').trim();
                        }).join('\n') + '\n';
                    }
                    case 'blockquote': return kids().split('\n').filter(l => l.trim()).map(l => '> ' + l.trim()).join('\n') + '\n';
                    case 'p': case 'div': return kids() + '\n';
                    default: return kids();
                }
            };
            return walk(root).replace(/[ \t]+\n/g, '\n').replace(/\n{3,}/g, '\n\n').trim();
        },
        // WhatsApp → e-mail: marcadores viram negrito/itálico/tachado; listas e citação viram blocos
        toEmailHtml(text) {
            const lines = String(text || '').replace(/\r\n?/g, '\n').replace(/```/g, '').split('\n');
            const tags = { b: 'strong', i: 'em', s: 's' };
            let html = '', list = null;
            const close = () => { if (list) { html += '</' + list + '>'; list = null; } };
            lines.forEach(line => {
                let m;
                if ((m = line.match(RX.bullet)) || (m = line.match(RX.number))) {
                    const kind = RX.bullet.test(line) ? 'ul' : 'ol';
                    if (list !== kind) { close(); html += '<' + kind + '>'; list = kind; }
                    html += '<li>' + inline(esc(line.slice(m[0].length)), tags) + '</li>';
                    return;
                }
                close();
                if ((m = line.match(RX.quote))) html += '<blockquote>' + inline(esc(line.slice(m[0].length)), tags) + '</blockquote>';
                else html += line.trim() ? '<p>' + inline(esc(line), tags) + '</p>' : '<p><br></p>';
            });
            close();
            return html;
        }
    };

    const EMOJIS = ['🔴', '🟠', '🟡', '🟢', '🔵', '🟣', '✅', '❌', '⚠️', '❗', '👉', '📌', '⭐', '🔥', '💡', '🚀', '🎯', '📅', '⏰', '📞', '💬', '📈', '💰', '🤝', '👋', '🙂', '😉', '👏', '🙏', '➡️'];

    // Troca um trecho do campo preservando o Ctrl+Z (insertText) e avisa a mudança
    function replaceRange(ta, start, end, text, selStart, selEnd) {
        ta.focus();
        ta.setSelectionRange(start, end);
        let ok = false;
        try {
            ok = (text === '' && start !== end) ? document.execCommand('delete') : document.execCommand('insertText', false, text);
        } catch (e) { ok = false; }
        if (!ok) {
            ta.value = ta.value.slice(0, start) + text + ta.value.slice(end);
            ta.dispatchEvent(new Event('input', { bubbles: true }));
        }
        ta.setSelectionRange(selStart, selEnd === undefined ? selStart : selEnd);
    }

    function toggleInline(ta, kind) {
        const m = { bold: '*', italic: '_', strike: '~', mono: '```' }[kind];
        const v = ta.value, s = ta.selectionStart, e = ta.selectionEnd;
        if (s === e) { replaceRange(ta, s, e, m + m, s + m.length); return; }
        // Já formatado? (marcadores logo fora ou dentro da seleção) → tira
        if (s >= m.length && v.slice(s - m.length, s) === m && v.slice(e, e + m.length) === m) {
            replaceRange(ta, s - m.length, e + m.length, v.slice(s, e), s - m.length, e - m.length);
            return;
        }
        const sel = v.slice(s, e);
        if (sel.length > 2 * m.length && sel.startsWith(m) && sel.endsWith(m)) {
            const inner = sel.slice(m.length, -m.length);
            replaceRange(ta, s, e, inner, s, s + inner.length);
            return;
        }
        // Espaços ficam fora do marcador (senão o WhatsApp não formata)
        const wrapOne = (t) => { const x = t.match(/^(\s*)([\s\S]*?)(\s*)$/); return x[2] ? x[1] + m + x[2] + m + x[3] : t; };
        const out = kind === 'mono' ? wrapOne(sel) : sel.split('\n').map(wrapOne).join('\n');
        replaceRange(ta, s, e, out, s, s + out.length);
    }

    function toggleLines(ta, kind) {
        const v = ta.value, s = ta.selectionStart, e = ta.selectionEnd;
        const ls = v.lastIndexOf('\n', s - 1) + 1;
        let le = v.indexOf('\n', (e > s && v[e - 1] === '\n') ? e - 1 : e);
        if (le < 0) le = v.length;
        const lines = v.slice(ls, le).split('\n');
        const rx = RX[kind];
        const filled = lines.filter(l => l.trim());
        const all = filled.length > 0 && filled.every(l => rx.test(l));
        let n = 0;
        const prefix = () => kind === 'bullet' ? '• ' : kind === 'number' ? (++n) + '. ' : '> ';
        let out;
        if (!filled.length) out = [prefix() + lines[0]].concat(lines.slice(1));
        else out = lines.map(l => {
            if (!l.trim()) return l;
            if (all) return l.replace(rx, '');
            if (kind === 'quote') return rx.test(l) ? l : '> ' + l;
            return prefix() + l.replace(RX.bullet, '').replace(RX.number, '');
        });
        const txt = out.join('\n');
        if (s === e) replaceRange(ta, ls, le, txt, ls + txt.length);
        else replaceRange(ta, ls, le, txt, ls, ls + txt.length);
    }

    // Enter numa linha de lista/citação continua a lista; Enter numa linha só com o marcador encerra
    function onEnter(ta, ev) {
        if (ev.shiftKey || ev.ctrlKey || ev.metaKey || ev.altKey || ev.isComposing) return;
        const v = ta.value, s = ta.selectionStart;
        if (s !== ta.selectionEnd) return;
        const ls = v.lastIndexOf('\n', s - 1) + 1;
        const before = v.slice(ls, s);
        let m, next;
        if ((m = before.match(/^(\s*)([•\-*])\s+/))) next = m[1] + m[2] + ' ';
        else if ((m = before.match(/^(\s*)(\d+)([.)])\s+/))) next = m[1] + (parseInt(m[2], 10) + 1) + m[3] + ' ';
        else if ((m = before.match(/^>\s?/))) next = '> ';
        else return;
        ev.preventDefault();
        if (before.trim() === m[0].trim() && v.slice(s, (v.indexOf('\n', s) + 1 || v.length + 1) - 1).trim() === '') {
            replaceRange(ta, ls, s, '', ls); // linha vazia de lista: sai da lista
            return;
        }
        replaceRange(ta, s, s, '\n' + next, s + 1 + next.length);
    }

    function toolbarHtml() {
        const b = (act, icon, title) => `<button type="button" class="wa-btn" data-wa="${act}" title="${title}" aria-label="${title}"><i class="bi bi-${icon}"></i></button>`;
        return b('bold', 'type-bold', 'Negrito (Ctrl+B)') + b('italic', 'type-italic', 'Itálico (Ctrl+I)')
            + b('strike', 'type-strikethrough', 'Tachado') + b('mono', 'code', 'Monoespaçado')
            + '<span class="wa-sep"></span>'
            + b('bullet', 'list-ul', 'Lista com tópicos') + b('number', 'list-ol', 'Lista numerada') + b('quote', 'quote', 'Citação')
            + '<span class="wa-sep"></span>'
            + `<div class="dropdown d-inline-block">
                <button type="button" class="wa-btn dropdown-toggle" data-bs-toggle="dropdown" data-wa-emoji-toggle aria-expanded="false" title="Emojis para destacar (o WhatsApp não tem cor de letra)"><i class="bi bi-palette"></i> <small>Destaque</small></button>
                <div class="dropdown-menu wa-emoji-menu">
                    <div class="wa-emoji-grid">${EMOJIS.map(x => `<button type="button" class="wa-emoji" data-emoji="${x}" title="Inserir ${x}">${x}</button>`).join('')}</div>
                    <div class="wa-emoji-note">O WhatsApp não aceita cor de letra. Use estes emojis para destacar.</div>
                </div></div>`;
    }

    function previewHtml(text) {
        const body = WaFormat.toHtml(text);
        const now = new Date();
        const hh = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
        return '<div class="wa-preview-box"><div class="wa-bubble" data-wa-bubble>'
            + (body || '<div class="wa-empty">A mensagem aparece aqui.</div>')
            + '<div class="wa-time">' + hh + ' <i class="bi bi-check2-all" style="color:#53bdeb;"></i></div></div></div>';
    }

    window.WaEditor = {
        attach(ta, opts) {
            opts = opts || {};
            if (!ta) return null;
            if (ta._wa) { ta._wa.refresh(); return ta._wa; }
            const api = {
                refresh() { if (opts.preview) opts.preview.innerHTML = previewHtml(ta.value); },
                run(act) {
                    if (['bold', 'italic', 'strike', 'mono'].includes(act)) toggleInline(ta, act);
                    else toggleLines(ta, act);
                }
            };
            if (opts.toolbar) {
                const tb = opts.toolbar;
                tb.classList.add('wa-toolbar');
                tb.setAttribute('role', 'toolbar');
                tb.setAttribute('aria-label', 'Formatação do WhatsApp');
                tb.innerHTML = toolbarHtml();
                // mousedown sem foco: a seleção do campo continua valendo para o botão
                tb.addEventListener('mousedown', (ev) => { if (ev.target.closest('.wa-btn:not([data-wa-emoji-toggle]), .wa-emoji')) ev.preventDefault(); });
                tb.addEventListener('click', (ev) => {
                    const btn = ev.target.closest('[data-wa]');
                    if (btn) { api.run(btn.dataset.wa); return; }
                    const em = ev.target.closest('[data-emoji]');
                    if (em) {
                        const s = ta.selectionStart ?? ta.value.length, e = ta.selectionEnd ?? s;
                        replaceRange(ta, s, e, em.dataset.emoji, s + em.dataset.emoji.length);
                    }
                });
            }
            ta.addEventListener('keydown', (ev) => {
                if (ev.key === 'Enter') { onEnter(ta, ev); return; }
                if ((ev.ctrlKey || ev.metaKey) && !ev.altKey && !ev.shiftKey) {
                    const k = ev.key.toLowerCase();
                    if (k === 'b' || k === 'i') { ev.preventDefault(); api.run(k === 'b' ? 'bold' : 'italic'); }
                }
            });
            ta.addEventListener('input', () => api.refresh());
            ta._wa = api;
            api.refresh();
            return api;
        }
    };
})();
</script>
