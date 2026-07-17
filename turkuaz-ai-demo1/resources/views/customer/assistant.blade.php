<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Cera · {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
    </style>
</head>
<body>
    <div class="container py-4 chat-wrap">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h4 mb-0">Cera · Ürün Asistanı</h1>
            <div>
                @auth
                    <span class="text-muted small">{{ auth()->user()->name }}</span>
                @else
                    <a href="{{ route('login') }}" class="small">Giriş yap</a>
                @endauth
            </div>
        </div>

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
