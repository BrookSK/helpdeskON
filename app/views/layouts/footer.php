    <!-- Proteção CSRF (global): injeta o token nos formulários POST e nas
         chamadas fetch same-origin, sem precisar editar cada view. -->
    <script>
    (function () {
        var meta = document.querySelector('meta[name="csrf-token"]');
        var CSRF = meta ? meta.getAttribute('content') : '';
        if (!CSRF) return;
        window.CSRF_TOKEN = CSRF;

        // 1) Formulários POST: garante um <input hidden name="csrf_token">.
        function ensureFormToken(form) {
            if (!form || (form.method || '').toLowerCase() !== 'post') return;
            if (form.querySelector('input[name="csrf_token"]')) return;
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'csrf_token';
            input.value = CSRF;
            form.appendChild(input);
        }
        function scanForms() {
            document.querySelectorAll('form').forEach(ensureFormToken);
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', scanForms);
        } else {
            scanForms();
        }
        // Cobre forms criados dinamicamente e submetidos direto.
        document.addEventListener('submit', function (e) {
            if (e.target && e.target.tagName === 'FORM') ensureFormToken(e.target);
        }, true);

        // 2) fetch(): anexa o header X-CSRF-Token em POST/PUT/PATCH/DELETE
        //    same-origin. Não mexe em GET nem em requisições cross-origin.
        var origFetch = window.fetch;
        if (typeof origFetch === 'function') {
            window.fetch = function (input, init) {
                init = init || {};
                var method = (init.method || (typeof input === 'object' && input && input.method) || 'GET').toUpperCase();
                var url = (typeof input === 'string') ? input : (input && input.url) || '';
                var sameOrigin = true;
                try {
                    if (url) {
                        var u = new URL(url, window.location.origin);
                        sameOrigin = (u.origin === window.location.origin);
                    }
                } catch (err) { sameOrigin = true; }

                if (sameOrigin && ['POST', 'PUT', 'PATCH', 'DELETE'].indexOf(method) !== -1) {
                    var headers = new Headers(init.headers || (typeof input === 'object' && input && input.headers) || {});
                    if (!headers.has('X-CSRF-Token')) headers.set('X-CSRF-Token', CSRF);
                    init.headers = headers;
                }
                return origFetch.call(this, input, init);
            };
        }
    })();
    </script>

    <!-- WhatsApp Flutuante -->
    <?php
    $whatsappEnabled = Config::get('whatsapp_enabled');
    $whatsappNumber = Config::get('whatsapp_number');
    // Botão flutuante do WhatsApp apenas para clientes
    $currentUserRole = $_SESSION['user_role'] ?? '';
    if ($currentUserRole === 'client' && $whatsappEnabled === '1' && !empty($whatsappNumber)):
        $whatsappMessage = urlencode(Config::get('whatsapp_message', 'Olá! Preciso de ajuda.'));
    ?>
    <a href="https://wa.me/<?= escape($whatsappNumber) ?>?text=<?= $whatsappMessage ?>" 
       target="_blank" 
       class="whatsapp-float" 
       title="Fale conosco no WhatsApp"
       aria-label="WhatsApp">
        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="currentColor" viewBox="0 0 16 16">
            <path d="M13.601 2.326A7.85 7.85 0 0 0 7.994 0C3.627 0 .068 3.558.064 7.926c0 1.399.366 2.76 1.057 3.965L0 16l4.204-1.102a7.9 7.9 0 0 0 3.79.965h.004c4.368 0 7.926-3.558 7.93-7.93A7.9 7.9 0 0 0 13.6 2.326zM7.994 14.521a6.6 6.6 0 0 1-3.356-.92l-.24-.144-2.494.654.666-2.433-.156-.251a6.56 6.56 0 0 1-1.007-3.505c0-3.626 2.957-6.584 6.591-6.584a6.56 6.56 0 0 1 4.66 1.931 6.56 6.56 0 0 1 1.928 4.66c-.004 3.639-2.961 6.592-6.592 6.592m3.615-4.934c-.197-.099-1.17-.578-1.353-.646-.182-.065-.315-.099-.445.099-.133.197-.513.646-.627.775-.114.133-.232.148-.43.05-.197-.1-.836-.308-1.592-.985-.59-.525-.985-1.175-1.103-1.372-.114-.198-.011-.304.088-.403.087-.088.197-.232.296-.346.1-.114.133-.198.198-.33.065-.134.034-.248-.015-.347-.05-.099-.445-1.076-.612-1.47-.16-.389-.323-.335-.445-.34-.114-.007-.247-.007-.38-.007a.73.73 0 0 0-.529.247c-.182.198-.691.677-.691 1.654s.71 1.916.81 2.049c.098.133 1.394 2.132 3.383 2.992.47.205.84.326 1.129.418.475.152.904.129 1.246.08.38-.058 1.171-.48 1.338-.943.164-.464.164-.86.114-.943-.049-.084-.182-.133-.38-.232"/>
        </svg>
    </a>
    <style>
        .whatsapp-float {
            position: fixed;
            bottom: 25px;
            right: 25px;
            width: 56px;
            height: 56px;
            background: #25D366;
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(37, 211, 102, 0.4);
            z-index: 9999;
            transition: transform 0.3s, box-shadow 0.3s;
            text-decoration: none;
        }
        .whatsapp-float:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(37, 211, 102, 0.6);
            color: #fff;
        }
        @media (max-width: 575px) {
            .whatsapp-float {
                bottom: 18px;
                right: 18px;
                width: 50px;
                height: 50px;
            }
            .whatsapp-float svg {
                width: 24px;
                height: 24px;
            }
        }
    </style>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Links legais -->
    <div class="text-center py-3" style="font-size:0.75rem;color:#aaa;">
        <a href="<?= baseUrl('pages/termos') ?>" target="_blank" style="color:#888;text-decoration:none;">Termos de Uso</a>
        <span class="mx-1">|</span>
        <a href="<?= baseUrl('pages/privacidade') ?>" target="_blank" style="color:#888;text-decoration:none;">Política de Privacidade</a>
    </div>

    <script>
        // Polling de notificações
        function checkNotifications() {
            fetch('<?= baseUrl("notifications/getUnread") ?>')
                .then(r => r.json())
                .then(data => {
                    document.querySelectorAll('.notification-count-sidebar').forEach(b => {
                        if (data.count > 0) {
                            b.textContent = data.count;
                            b.style.display = 'inline-block';
                        } else {
                            b.style.display = 'none';
                        }
                    });
                })
                .catch(() => {});
        }
        setInterval(checkNotifications, 30000);
        checkNotifications();
    </script>

    <?php
    // Notificações push de WhatsApp — só para roles com acesso ao chat e fora da tela de chat
    $wppNotifRoles = ['super_admin', 'attendant', 'whatsapp_agent', 'comercial'];
    $isOnChatPage = (($currentPage ?? '') === 'whatsapp_chat');
    if (in_array($currentUserRole, $wppNotifRoles) && !$isOnChatPage):
    ?>
    <script>
    (function() {
        const WPP_BASE = '<?= baseUrl("") ?>';
        // Namespace por usuário: notificações dispensadas não devem "vazar" entre contas
        // que compartilham o mesmo navegador.
        const WPP_USER_ID = '<?= (int)($_SESSION["user_id"] ?? 0) ?>';
        const WPP_DISMISS_KEY = 'wppDismissedNotifs_' + WPP_USER_ID;

        let wppLastNotifIds = new Set();
        let wppNotifAudio = null;

        // Notificações que o usuário já fechou (clicou no X). Persistido em localStorage
        // para sobreviver à navegação entre telas (cada tela recarrega a página).
        // Chave = contact_id + '-' + timestamp da última mensagem. Uma mensagem mais nova
        // gera uma chave diferente e volta a notificar normalmente.
        function loadDismissed() {
            try {
                const raw = localStorage.getItem(WPP_DISMISS_KEY);
                return raw ? new Set(JSON.parse(raw)) : new Set();
            } catch (e) { return new Set(); }
        }

        function saveDismissed(set) {
            try {
                // Mantém apenas as 100 chaves mais recentes para não crescer indefinidamente.
                const arr = Array.from(set).slice(-100);
                localStorage.setItem(WPP_DISMISS_KEY, JSON.stringify(arr));
            } catch (e) {}
        }

        let wppDismissed = loadDismissed();

        function dismissNotif(key) {
            wppDismissed.add(key);
            saveDismissed(wppDismissed);
        }

        function checkWhatsappNotifications() {
            fetch(WPP_BASE + 'whatsapp/notifications', { headers: {'X-Requested-With': 'XMLHttpRequest'} })
            .then(r => { if (r.ok) return r.json(); throw new Error(); })
            .then(notifications => {
                if (!notifications || !notifications.length) return;
                notifications.forEach(n => {
                    const key = n.contact_id + '-' + n.timestamp;
                    // Já exibida nesta sessão de página, ou dispensada pelo usuário anteriormente.
                    if (wppLastNotifIds.has(key) || wppDismissed.has(key)) return;
                    wppLastNotifIds.add(key);
                    showWhatsappToast(n, key);
                });
                // Limpar cache antigo (manter últimos 50)
                if (wppLastNotifIds.size > 50) {
                    const arr = Array.from(wppLastNotifIds);
                    wppLastNotifIds = new Set(arr.slice(-30));
                }
            })
            .catch(() => {});
        }

        function showWhatsappToast(n, key) {
            const container = getToastContainer();
            const toast = document.createElement('div');
            toast.className = 'wpp-push-toast';

            const closeBtn = document.createElement('button');
            closeBtn.className = 'btn-close btn-close-sm ms-auto';
            closeBtn.setAttribute('aria-label', 'Fechar notificação');
            // Fechar registra a dispensa para AQUELE usuário, evitando que a mesma
            // notificação volte a pipocar ao navegar entre telas.
            closeBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                dismissNotif(key);
                toast.remove();
            });

            const header = document.createElement('div');
            header.className = 'wpp-push-header';
            header.innerHTML = `<i class="bi bi-whatsapp text-success"></i><strong>${escapeH(n.contact_name)}</strong>`;
            header.appendChild(closeBtn);

            const body = document.createElement('div');
            body.className = 'wpp-push-body';
            body.innerHTML = `
                <span class="wpp-push-msg">${escapeH(n.message)}</span>
                <small class="wpp-push-time">${n.unread_count} não lida${n.unread_count > 1 ? 's' : ''}</small>
            `;
            body.addEventListener('click', function() {
                window.location = WPP_BASE + 'whatsapp/chat/' + n.contact_id;
            });

            toast.appendChild(header);
            toast.appendChild(body);
            container.appendChild(toast);

            // Tocar som suave
            playNotifSound();

            // Auto-hide após 8 segundos: tempo suficiente para quem está na mesma tela
            // perceber a mensagem. Ao sumir, também marcamos como dispensada para que
            // NÃO volte a pipocar ao navegar entre telas — só reaparece se chegar uma
            // mensagem nova daquele contato (timestamp diferente = chave diferente).
            setTimeout(() => {
                dismissNotif(key);
                if (toast.parentElement) toast.remove();
            }, 8000);
        }

        function getToastContainer() {
            let container = document.getElementById('wpp-toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'wpp-toast-container';
                document.body.appendChild(container);
            }
            return container;
        }

        function playNotifSound() {
            try {
                if (!wppNotifAudio) {
                    // Gerar um beep curto via Web Audio API
                    const ctx = new (window.AudioContext || window.webkitAudioContext)();
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.frequency.value = 800;
                    gain.gain.value = 0.1;
                    osc.start();
                    setTimeout(() => { osc.stop(); ctx.close(); }, 150);
                }
            } catch(e) {}
        }

        function escapeH(str) {
            if (!str) return '';
            const d = document.createElement('div');
            d.textContent = str;
            return d.innerHTML;
        }

        // Polling a cada 5 segundos
        setInterval(checkWhatsappNotifications, 5000);
        setTimeout(checkWhatsappNotifications, 2000);
    })();
    </script>
    <style>
    #wpp-toast-container {
        position: fixed;
        top: 16px;
        right: 16px;
        z-index: 99999;
        display: flex;
        flex-direction: column;
        gap: 8px;
        max-width: 340px;
    }
    .wpp-push-toast {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        overflow: hidden;
        animation: wppSlideIn 0.3s ease;
        border-left: 4px solid #25D366;
    }
    .wpp-push-header {
        padding: 8px 12px;
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.82rem;
        background: #f8f9fa;
        border-bottom: 1px solid #eee;
    }
    .wpp-push-header .btn-close-sm {
        width: 12px;
        height: 12px;
        font-size: 0.6rem;
    }
    .wpp-push-body {
        padding: 10px 12px;
        cursor: pointer;
        transition: background 0.15s;
    }
    .wpp-push-body:hover {
        background: #f0faf8;
    }
    .wpp-push-msg {
        font-size: 0.8rem;
        color: #333;
        display: block;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .wpp-push-time {
        font-size: 0.68rem;
        color: #999;
    }
    @keyframes wppSlideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    </style>
    <?php endif; ?>

    <?php
    // Webphone nativo (WebRTC/SIP over WSS) — disponível em todas as telas do CRM e WhatsApp.
    $nvoipTelephonyRoles = ['super_admin', 'comercial'];
    $webphonePages = ['crm_leads', 'crm', 'whatsapp_chat'];
    if (in_array($currentUserRole, $nvoipTelephonyRoles) && in_array($currentPage ?? '', $webphonePages)):
        require APP_PATH . '/views/layouts/_webphone.php';
    endif;
    ?>
</body>
</html>
