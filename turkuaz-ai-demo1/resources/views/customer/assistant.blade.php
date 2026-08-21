<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Cera · {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --brand-900: #04415f;
            --brand-700: #095a82;
            --brand-500: #0e7fb3;
            --brand-100: #e7f2f8;
            --ink: #17242c;
            --muted: #6b7d88;
        }

        body {
            min-height: 100vh;
            color: var(--ink);
            /* Two soft brand-tinted pools instead of a flat grey, so the card
               reads as floating rather than pasted onto the page. */
            background:
                radial-gradient(1100px 520px at 12% -8%, #d9ebf5 0%, rgba(217,235,245,0) 60%),
                radial-gradient(900px 480px at 92% 6%, #e4f0f6 0%, rgba(228,240,246,0) 55%),
                #f2f5f7;
            background-attachment: fixed;
        }

        .chat-wrap { max-width: 820px; margin: 0 auto; }

        .main-card {
            border: 0;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 45px -25px rgba(4, 65, 95, .55), 0 2px 6px rgba(4, 65, 95, .06);
        }

        .chat-header {
            background: linear-gradient(135deg, var(--brand-900), var(--brand-700) 55%, var(--brand-500));
            color: #fff;
            padding: 1.1rem 1.35rem;
        }

        .brand-mark {
            width: 44px; height: 44px;
            display: grid; place-items: center;
            border-radius: 14px;
            font-size: 1.35rem;
            background: rgba(255, 255, 255, .16);
            border: 1px solid rgba(255, 255, 255, .28);
        }

        .header-pill {
            display: inline-flex; align-items: center; gap: .4rem;
            padding: .3rem .7rem;
            border-radius: 999px;
            font-size: .8rem;
            line-height: 1.2;
            text-decoration: none;
            color: #fff;
            background: rgba(255, 255, 255, .14);
            border: 1px solid rgba(255, 255, 255, .25);
            transition: background .15s ease;
        }
        .header-pill:hover { background: rgba(255, 255, 255, .26); color: #fff; }

        .status-dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: #4ade80;
            box-shadow: 0 0 0 3px rgba(74, 222, 128, .25);
        }
        .status-dot.off { background: #fbbf24; box-shadow: 0 0 0 3px rgba(251, 191, 36, .25); }

        .chat-box {
            height: 62vh;
            min-height: 340px;
            overflow-y: auto;
            padding: 1.25rem;
            background: #fff;
            scroll-behavior: smooth;
        }
        .chat-box::-webkit-scrollbar { width: 9px; }
        .chat-box::-webkit-scrollbar-thumb { background: #d5dfe5; border-radius: 99px; }
        .chat-box::-webkit-scrollbar-thumb:hover { background: #bfced7; }

        /* One message = avatar + bubble, so both sides align on the same grid. */
        .msg { display: flex; gap: .6rem; margin-bottom: .85rem; align-items: flex-end; }
        .msg.user { flex-direction: row-reverse; }

        .msg-avatar {
            flex: 0 0 32px;
            width: 32px; height: 32px;
            border-radius: 50%;
            display: grid; place-items: center;
            font-size: .9rem;
            color: #fff;
            background: linear-gradient(135deg, var(--brand-700), var(--brand-500));
        }
        .msg.user .msg-avatar { background: #cfd9e0; color: #46565f; }

        .bubble {
            max-width: 78%;
            padding: .7rem .95rem;
            border-radius: 16px;
            white-space: pre-wrap;
            word-wrap: break-word;
            line-height: 1.5;
            animation: rise .22s ease-out;
        }
        .bubble.user {
            background: linear-gradient(135deg, var(--brand-700), var(--brand-500));
            color: #fff;
            border-bottom-right-radius: 5px;
        }
        .bubble.assistant {
            background: #f1f5f8;
            border: 1px solid #e4ebf0;
            border-bottom-left-radius: 5px;
        }

        @keyframes rise {
            from { opacity: 0; transform: translateY(6px); }
            to   { opacity: 1; transform: none; }
        }

        /* Three dots that breathe, rather than the words "Cera is typing". */
        .typing-dots { display: inline-flex; gap: .28rem; padding: .15rem 0; }
        .typing-dots span {
            width: 7px; height: 7px; border-radius: 50%;
            background: var(--muted);
            animation: blink 1.1s infinite ease-in-out;
        }
        .typing-dots span:nth-child(2) { animation-delay: .18s; }
        .typing-dots span:nth-child(3) { animation-delay: .36s; }

        @keyframes blink {
            0%, 60%, 100% { opacity: .25; transform: translateY(0); }
            30%           { opacity: 1;   transform: translateY(-3px); }
        }

        /* Product cards sit under the answer they belong to, indented to line
           up with the assistant bubble above them. */
        .product-strip { margin: -.35rem 0 .9rem 2.6rem; }

        .product-card-mini {
            width: 156px;
            border: 1px solid #e4ebf0;
            border-radius: 12px;
            overflow: hidden;
            background: #fff;
            text-decoration: none;
            color: inherit;
            transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
        }
        .product-card-mini:hover,
        .product-card-mini:focus-visible {
            transform: translateY(-2px);
            border-color: var(--brand-500);
            box-shadow: 0 10px 20px -12px rgba(4, 65, 95, .45);
            color: inherit;
        }
        .product-card-mini img { height: 104px; width: 100%; object-fit: cover; background: #f1f5f8; }

        /* Dealer-only line; absent entirely for everyone else. */
        .product-code {
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: .68rem;
            color: var(--brand-700);
            background: var(--brand-100);
            border-radius: 5px;
            padding: .1rem .3rem;
            margin-top: .25rem;
        }

        .product-open {
            font-size: .7rem;
            color: var(--brand-500);
            margin-top: .3rem;
            opacity: 0;
            transition: opacity .15s ease;
        }
        .product-card-mini:hover .product-open,
        .product-card-mini:focus-visible .product-open { opacity: 1; }

        /* Escape hatch under every answer: the assistant is a guess, the
           catalog search is not. */
        .search-hint {
            display: flex; align-items: center; gap: .4rem;
            margin: -.4rem 0 .9rem 2.6rem;
            padding: .45rem .7rem;
            border: 1px dashed #cfdde6;
            border-radius: 10px;
            background: #fbfdfe;
            font-size: .78rem;
            color: var(--muted);
            max-width: 78%;
        }
        .search-hint a {
            color: var(--brand-700);
            font-weight: 500;
            text-decoration: none;
            white-space: nowrap;
        }
        .search-hint a:hover { text-decoration: underline; }

        .feedback-bar {
            display: flex; align-items: center; gap: .15rem;
            margin: -.5rem 0 .9rem 2.6rem;
            font-size: .8rem;
            color: var(--muted);
        }
        .feedback-bar button {
            border: 1px solid #e4ebf0;
            background: #fff;
            border-radius: 8px;
            padding: .1rem .4rem;
            margin-left: .25rem;
            cursor: pointer;
            line-height: 1.4;
            transition: transform .12s ease, border-color .12s ease;
        }
        .feedback-bar button:hover { transform: translateY(-1px); border-color: var(--brand-500); }
        .feedback-bar button:disabled { opacity: .45; transform: none; }
        .feedback-note { max-width: 78%; margin-left: 2.6rem; }

        /* Starter questions — the fastest way past a blank first turn. */
        .chip {
            border: 1px solid #d8e3ea;
            background: #fff;
            color: var(--brand-900);
            border-radius: 999px;
            padding: .35rem .8rem;
            font-size: .82rem;
            cursor: pointer;
            transition: background .15s ease, border-color .15s ease, transform .12s ease;
        }
        .chip:hover {
            background: var(--brand-100);
            border-color: var(--brand-500);
            transform: translateY(-1px);
        }

        .composer {
            background: #fbfcfd;
            border-top: 1px solid #e9eef2;
            padding: .9rem 1.1rem 1rem;
        }
        .composer .input-shell {
            display: flex; align-items: center; gap: .5rem;
            background: #fff;
            border: 1px solid #dbe4ea;
            border-radius: 999px;
            padding: .3rem .3rem .3rem 1rem;
            transition: border-color .15s ease, box-shadow .15s ease;
        }
        .composer .input-shell:focus-within {
            border-color: var(--brand-500);
            box-shadow: 0 0 0 .2rem rgba(14, 127, 179, .15);
        }
        .composer input {
            flex: 1 1 auto;
            border: 0; outline: 0;
            padding: .45rem 0;
            background: transparent;
            font-size: .95rem;
        }
        .btn-send {
            flex: 0 0 auto;
            width: 40px; height: 40px;
            border: 0; border-radius: 50%;
            display: grid; place-items: center;
            color: #fff;
            background: linear-gradient(135deg, var(--brand-700), var(--brand-500));
            transition: opacity .15s ease, transform .12s ease;
        }
        .btn-send:hover:not(:disabled) { transform: scale(1.06); }
        .btn-send:disabled { opacity: .5; }

        @media (max-width: 575.98px) {
            .chat-wrap { padding-left: .25rem; padding-right: .25rem; }
            .main-card { border-radius: 14px; }
            .chat-box { height: 66vh; padding: 1rem .85rem; }
            .bubble { max-width: 86%; }
            .product-strip, .feedback-bar, .feedback-note { margin-left: 0; }
        }
    </style>
</head>
<body>
    <div class="container py-4 py-md-5 chat-wrap">
        <div class="card main-card">
            <div class="chat-header">
                <div class="d-flex justify-content-between align-items-center gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="brand-mark"><i class="bi bi-robot"></i></div>
                        <div>
                            <h1 class="h5 mb-0 fw-semibold">Cera · Ürün Asistanı</h1>
                            <div class="d-flex align-items-center gap-2 small" style="opacity:.85">
                                <span class="status-dot {{ $assistantEnabled ? '' : 'off' }}"></span>
                                {{ $assistantEnabled ? 'Çevrim içi' : 'Şu anda devre dışı' }}
                            </div>
                        </div>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        {{-- Always offered: the assistant guesses intent and can
                             guess wrong, so the deterministic catalog is never
                             more than one click away. --}}
                        <a href="{{ route('search.index') }}" class="header-pill">
                            <i class="bi bi-search"></i>
                            <span class="d-none d-sm-inline">Katalogda ara</span>
                        </a>
                        @auth
                            {{-- /dashboard sends administrators to the admin panel and
                                 everyone else back here, so one link covers both. --}}
                            <a href="{{ route('dashboard') }}" class="header-pill">
                                <i class="bi bi-speedometer2"></i>
                                <span class="d-none d-sm-inline">Panel</span>
                            </a>
                            <span class="header-pill">
                                <i class="bi bi-person-circle"></i>
                                <span class="d-none d-sm-inline">{{ auth()->user()->name }}</span>
                            </span>
                        @else
                            <a href="{{ route('login') }}" class="header-pill">
                                <i class="bi bi-box-arrow-in-right"></i> Giriş yap
                            </a>
                        @endauth
                    </div>
                </div>
            </div>

            @if (!$assistantEnabled)
                <div class="card-body p-4">
                    <div class="alert alert-warning d-flex align-items-center gap-2 mb-0">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span>Asistan şu anda devre dışı. Lütfen daha sonra tekrar deneyin.</span>
                    </div>
                </div>
            @else
                <div id="chat" class="chat-box">
                    <div class="msg assistant">
                        <div class="msg-avatar"><i class="bi bi-robot"></i></div>
                        <div class="bubble assistant">Merhaba! Ben Cera 👋 Ürünlerimiz hakkında sorularınızı yanıtlayabilirim.</div>
                    </div>

                    <div id="suggestions" class="d-flex flex-wrap gap-2 ms-0 ms-sm-5 mb-2">
                        @foreach ([
                            'İbiza serisinde hangi lavabolar var?',
                            'Gömme rezervuar montaj kılavuzu',
                            '60 cm mat siyah lavabo önerir misin?',
                            'Asma klozet ölçüleri nelerdir?',
                        ] as $suggestion)
                            <button type="button" class="chip js-suggestion">{{ $suggestion }}</button>
                        @endforeach
                    </div>
                </div>

                <div class="composer">
                    <form id="chat-form">
                        <div class="input-shell">
                            <i class="bi bi-chat-dots" style="color: var(--muted)"></i>
                            <input type="text" id="chat-input" placeholder="Sorunuzu yazın..."
                                   maxlength="1000" autocomplete="off" required>
                            <button type="submit" id="chat-send" class="btn-send" title="Gönder">
                                <i class="bi bi-send-fill"></i>
                            </button>
                        </div>
                    </form>
                    <div class="small text-center mt-2" style="color: var(--muted)">
                        <i class="bi bi-info-circle"></i>
                        Cera yapay zekâ ile çalışır; ürün bilgilerini satış ekibimizle doğrulayın.
                    </div>
                </div>
            @endif
        </div>
    </div>

    <script>
        const chatBox = document.getElementById('chat');
        const form = document.getElementById('chat-form');
        const input = document.getElementById('chat-input');
        const sendBtn = document.getElementById('chat-send');
        const suggestions = document.getElementById('suggestions');
        const csrf = document.querySelector('meta[name="csrf-token"]').content;

        // Session token survives page reloads so the conversation continues.
        let sessionToken = localStorage.getItem('cera_session_token') || null;

        function scrollDown() {
            chatBox.scrollTop = chatBox.scrollHeight;
        }

        // Returns the whole row, so callers can still remove() a message and take
        // its avatar with it.
        function addBubble(text, who) {
            const row = document.createElement('div');
            row.className = 'msg ' + (who === 'user' ? 'user' : 'assistant');

            const avatar = document.createElement('div');
            avatar.className = 'msg-avatar';
            avatar.innerHTML = who === 'user'
                ? '<i class="bi bi-person-fill"></i>'
                : '<i class="bi bi-robot"></i>';

            const bubble = document.createElement('div');
            bubble.className = 'bubble ' + (who === 'user' ? 'user' : 'assistant');
            bubble.textContent = text;

            row.append(avatar, bubble);
            chatBox.appendChild(row);
            scrollDown();
            return row;
        }

        function addTyping() {
            const row = addBubble('', 'assistant');
            row.querySelector('.bubble').innerHTML =
                '<span class="typing-dots"><span></span><span></span><span></span></span>';
            scrollDown();
            return row;
        }

        // Escaped rather than interpolated: product names are catalogue data,
        // and a stray quote would otherwise break out of the title attribute.
        function esc(value) {
            const div = document.createElement('div');
            div.textContent = value ?? '';
            return div.innerHTML;
        }

        function addProducts(products) {
            if (!products || products.length === 0) return;
            const wrap = document.createElement('div');
            wrap.className = 'product-strip d-flex flex-wrap gap-2';
            products.forEach(p => {
                // A link, not a div: middle-click and "open in new tab" work,
                // and the whole card becomes one keyboard-reachable target.
                const card = document.createElement('a');
                card.className = 'card product-card-mini';
                card.href = p.url || '#';
                card.target = '_blank';
                card.rel = 'noopener';
                card.title = p.name || '';

                const meta = [p.series, p.dimensions].filter(Boolean).join(' · ');

                card.innerHTML =
                    (p.image ? '<img src="' + esc(p.image) + '" alt="" loading="lazy">' : '') +
                    '<div class="card-body p-2">' +
                    '<div class="small fw-semibold text-truncate">' + esc(p.name) + '</div>' +
                    (meta ? '<div class="small text-muted text-truncate">' + esc(meta) + '</div>' : '') +
                    // Dealer-only; the endpoint sends null for everyone else.
                    (p.code ? '<div class="product-code text-truncate">' + esc(p.code) + '</div>' : '') +
                    '<div class="product-open"><i class="bi bi-box-arrow-up-right"></i> Detaylar</div>' +
                    '</div>';

                wrap.appendChild(card);
            });
            chatBox.appendChild(wrap);
            scrollDown();
        }

        const SEARCH_URL = @json(route('search.index'));

        // Offered under every answer, not only empty ones: a confident-looking
        // reply can still be the wrong reading of the question, and the search
        // page is the deterministic way to check.
        function addSearchHint(question) {
            const hint = document.createElement('div');
            hint.className = 'search-hint';

            const link = document.createElement('a');
            link.href = SEARCH_URL + '?q=' + encodeURIComponent(question || '');
            link.target = '_blank';
            link.rel = 'noopener';
            link.innerHTML = '<i class="bi bi-search"></i> Katalogda ara';

            const label = document.createElement('span');
            label.className = 'flex-grow-1';
            label.textContent = 'Aradığınızı bulamadınız mı?';

            hint.append(label, link);
            chatBox.appendChild(hint);
            scrollDown();
        }

        // Admin toggle (Settings -> Answer Feedback). The /send response repeats
        // it, so switching it off takes effect without a page reload.
        let feedbackEnabled = @json($feedbackEnabled ?? true);

        function sendFeedback(messageId, rating, note) {
            return fetch('{{ route('assistant.feedback') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    message_id: messageId,
                    session_token: sessionToken,
                    rating: rating,
                    note: note || null,
                }),
            }).catch(() => null);   // Feedback must never break the chat itself.
        }

        function addFeedbackBar(messageId) {
            const bar = document.createElement('div');
            bar.className = 'feedback-bar';

            const label = document.createElement('span');
            label.textContent = 'Bu yanıt yardımcı oldu mu?';

            const up = document.createElement('button');
            up.type = 'button';
            up.textContent = '👍';
            up.title = 'Evet';

            const down = document.createElement('button');
            down.type = 'button';
            down.textContent = '👎';
            down.title = 'Hayır';

            bar.append(label, up, down);
            chatBox.appendChild(bar);
            scrollDown();

            function thanks() {
                bar.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Geri bildiriminiz için teşekkürler.';
            }

            up.addEventListener('click', function () {
                up.disabled = down.disabled = true;
                sendFeedback(messageId, 1, null);
                thanks();
            });

            down.addEventListener('click', function () {
                up.disabled = down.disabled = true;

                // Record the down-vote immediately, so the signal survives even
                // if the user never fills in the note below.
                sendFeedback(messageId, -1, null);

                const wrap = document.createElement('div');
                wrap.className = 'feedback-note mb-3';

                const box = document.createElement('textarea');
                box.className = 'form-control form-control-sm mb-1';
                box.rows = 2;
                box.maxLength = 1000;
                box.placeholder = 'Yanıtın nesi eksikti? (isteğe bağlı)';

                const send = document.createElement('button');
                send.type = 'button';
                send.className = 'btn btn-sm btn-outline-secondary';
                send.textContent = 'Gönder';

                wrap.append(box, send);
                chatBox.appendChild(wrap);
                scrollDown();
                box.focus();

                send.addEventListener('click', function () {
                    send.disabled = true;
                    // Same message, now with the note — the endpoint overwrites.
                    sendFeedback(messageId, -1, box.value.trim());
                    wrap.remove();
                    thanks();
                });
            });
        }

        // A starter question is just a pre-filled first turn; once the
        // conversation is under way the chips have nothing left to offer.
        suggestions?.addEventListener('click', function (event) {
            const chip = event.target.closest('.js-suggestion');
            if (!chip) return;
            input.value = chip.textContent.trim();
            form.requestSubmit();
        });

        form?.addEventListener('submit', async function (e) {
            e.preventDefault();
            const message = input.value.trim();
            if (!message) return;

            suggestions?.remove();
            addBubble(message, 'user');
            input.value = '';
            sendBtn.disabled = true;
            const typing = addTyping();

            try {
                const res = await fetch('{{ route('assistant.send') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ message: message, session_token: sessionToken }),
                });

                const data = await res.json();
                typing.remove();

                if (!res.ok) {
                    addBubble(data.reply || 'Bir hata oluştu. Lütfen tekrar deneyin.', 'assistant');
                    return;
                }

                if (data.session_token) {
                    sessionToken = data.session_token;
                    localStorage.setItem('cera_session_token', sessionToken);
                }

                addBubble(data.reply, 'assistant');
                addProducts(data.products);
                addSearchHint(message);

                if (typeof data.feedback_enabled === 'boolean') {
                    feedbackEnabled = data.feedback_enabled;
                }

                if (feedbackEnabled && data.message_id) {
                    addFeedbackBar(data.message_id);
                }
            } catch (err) {
                typing.remove();
                addBubble('Bağlantı hatası. Lütfen tekrar deneyin.', 'assistant');
            } finally {
                sendBtn.disabled = false;
                input.focus();
            }
        });

        input?.focus();
    </script>
</body>
</html>
