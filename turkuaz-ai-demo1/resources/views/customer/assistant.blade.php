<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Cera · {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    {{-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous"> --}}
    <style>
        body { background: #f4f6f8; }
        .chat-wrap { max-width: 760px; margin: 0 auto; }
        .chat-box { height: 65vh; overflow-y: auto; background: #fff; border: 1px solid #e3e6ea; border-radius: 12px; padding: 1rem; }
        .bubble { max-width: 80%; padding: .6rem .9rem; border-radius: 14px; margin-bottom: .6rem; white-space: pre-wrap; word-wrap: break-word; }
        .bubble.user { background: #0d6efd; color: #fff; margin-left: auto; border-bottom-right-radius: 4px; }
        .bubble.assistant { background: #eef1f4; color: #212529; margin-right: auto; border-bottom-left-radius: 4px; }
        .product-card-mini { width: 150px; }
        .product-card-mini img { height: 90px; object-fit: cover; }
        .typing { font-style: italic; color: #6c757d; }
        .feedback-bar { margin: -.3rem 0 .8rem; font-size: .8rem; color: #6c757d; }
        .feedback-bar button { border: 0; background: none; padding: 0 .25rem; cursor: pointer; line-height: 1; }
        .feedback-bar button:hover { transform: scale(1.15); }
        .feedback-bar button.chosen { transform: scale(1.2); }
        .feedback-note { max-width: 80%; }
    </style>
</head>
<body>
    <div class="container py-4 chat-wrap">
        <div class="card shadow-lg main-card">
            <div class="card-header" style=" background: linear-gradient(135deg, rgb(4, 65, 95), rgb(9, 90, 130));">
                <div class="d-flex justify-content-between align-items-center mb-3 text-white">
                    <h1 class="h4 mb-0"> <i class="bi bi-robot"></i> Cera · Ürün Asistanı</h1>
                    <div class="d-flex gap-2 align-items-center text-white">
                        @auth
                            <span class="text-muted small btn btn-success text-white">{{ auth()->user()->name }}</span>
                        @else
                            <a href="{{ route('login') }}" class="small text-muted small btn btn-success">Giriş yap</a>
                        @endauth
                    </div>
                </div>
            </div>

            <div class="card-body p-3 p-xl-3">
                @if (!$assistantEnabled)
                    <div class="alert alert-warning">Asistan şu anda devre dışı. Lütfen daha sonra tekrar deneyin.</div>
                @else
                    <div id="chat" class="chat-box mb-3">
                        <div class="bubble assistant">Merhaba! Ben Cera 👋 Ürünlerimiz hakkında sorularınızı yanıtlayabilirim. Örneğin: "İbiza serisinde hangi lavabolar var?" ya da "Gömme rezervuar montaj kılavuzu"</div>
                    </div>

                    <form id="chat-form" class="d-flex gap-2">
                        <input type="text" id="chat-input" class="form-control" placeholder="Sorunuzu yazın..." maxlength="1000" autocomplete="off" required>
                        <button type="submit" id="chat-send" class="btn btn-primary">Gönder</button>
                    </form>
                @endif
            </div>
        </div>

        

        
    </div>

    <script>
        const chatBox = document.getElementById('chat');
        const form = document.getElementById('chat-form');
        const input = document.getElementById('chat-input');
        const sendBtn = document.getElementById('chat-send');
        const csrf = document.querySelector('meta[name="csrf-token"]').content;

        // Session token survives page reloads so the conversation continues.
        let sessionToken = localStorage.getItem('cera_session_token') || null;

        function addBubble(text, who) {
            const div = document.createElement('div');
            div.className = 'bubble ' + who;
            div.textContent = text;
            chatBox.appendChild(div);
            chatBox.scrollTop = chatBox.scrollHeight;
            return div;
        }

        function addProducts(products) {
            if (!products || products.length === 0) return;
            const wrap = document.createElement('div');
            wrap.className = 'd-flex flex-wrap gap-2 mb-2';
            products.forEach(p => {
                const card = document.createElement('div');
                card.className = 'card product-card-mini';
                card.innerHTML =
                    (p.image ? '<img src="' + p.image + '" class="card-img-top" alt="">' : '') +
                    '<div class="card-body p-2">' +
                    '<div class="small fw-bold">' + (p.name || '') + '</div>' +
                    '<div class="small text-muted">' + [p.series, p.dimensions].filter(Boolean).join(' · ') + '</div>' +
                    '</div>';
                wrap.appendChild(card);
            });
            chatBox.appendChild(wrap);
            chatBox.scrollTop = chatBox.scrollHeight;
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
            label.textContent = 'Bu yanıt yardımcı oldu mu? ';

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
            chatBox.scrollTop = chatBox.scrollHeight;

            function thanks() {
                bar.textContent = 'Geri bildiriminiz için teşekkürler.';
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
                chatBox.scrollTop = chatBox.scrollHeight;
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

        form?.addEventListener('submit', async function (e) {
            e.preventDefault();
            const message = input.value.trim();
            if (!message) return;

            addBubble(message, 'user');
            input.value = '';
            sendBtn.disabled = true;
            const typing = addBubble('Cera yazıyor...', 'assistant typing');

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
    </script>
</body>
</html>
