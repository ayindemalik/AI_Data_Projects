<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Katalog Arama · {{ config('app.name') }}</title>
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
            background:
                radial-gradient(1100px 520px at 12% -8%, #d9ebf5 0%, rgba(217,235,245,0) 60%),
                radial-gradient(900px 480px at 92% 6%, #e4f0f6 0%, rgba(228,240,246,0) 55%),
                #f2f5f7;
            background-attachment: fixed;
        }

        .topbar {
            background: linear-gradient(135deg, var(--brand-900), var(--brand-700) 55%, var(--brand-500));
            color: #fff;
            padding: .85rem 0;
        }
        .topbar a { color: rgba(255,255,255,.9); text-decoration: none; }
        .topbar a:hover { color: #fff; }

        .search-shell {
            display: flex; align-items: center; gap: .5rem;
            background: #fff;
            border: 1px solid #dbe4ea;
            border-radius: 999px;
            padding: .45rem .5rem .45rem 1.1rem;
            box-shadow: 0 12px 30px -20px rgba(4, 65, 95, .5);
            transition: border-color .15s ease, box-shadow .15s ease;
        }
        .search-shell:focus-within {
            border-color: var(--brand-500);
            box-shadow: 0 0 0 .2rem rgba(14, 127, 179, .15);
        }
        .search-shell input {
            flex: 1 1 auto;
            border: 0; outline: 0;
            padding: .5rem 0;
            font-size: 1rem;
            background: transparent;
        }

        /* Suggestions hang under the box; the wrapper owns the positioning so
           the dropdown lines up with the input, not the page. */
        .search-wrap { position: relative; }
        .suggestions {
            position: absolute; z-index: 20; left: 0; right: 0; top: calc(100% + .4rem);
            background: #fff;
            border: 1px solid #e4ebf0;
            border-radius: 14px;
            box-shadow: 0 18px 40px -22px rgba(4, 65, 95, .55);
            overflow: hidden;
        }
        .suggestion {
            display: flex; align-items: center; gap: .6rem;
            width: 100%;
            padding: .6rem .9rem;
            border: 0;
            background: none;
            text-align: left;
            font-size: .9rem;
            cursor: pointer;
        }
        .suggestion:hover, .suggestion.active { background: var(--brand-100); }
        .suggestion .badge-type {
            font-size: .68rem;
            padding: .1rem .4rem;
            border-radius: 5px;
            background: var(--brand-100);
            color: var(--brand-700);
            flex: 0 0 auto;
        }

        .filter-card {
            border: 0;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 12px 30px -24px rgba(4, 65, 95, .5), 0 1px 3px rgba(4, 65, 95, .05);
        }

        .product-card {
            display: flex; flex-direction: column;
            height: 100%;
            border: 1px solid #e4ebf0;
            border-radius: 14px;
            overflow: hidden;
            background: #fff;
            text-decoration: none;
            color: inherit;
            transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
        }
        .product-card:hover, .product-card:focus-visible {
            transform: translateY(-3px);
            border-color: var(--brand-500);
            box-shadow: 0 16px 32px -20px rgba(4, 65, 95, .5);
            color: inherit;
        }
        .product-card .frame {
            aspect-ratio: 4 / 3;
            background: #f1f5f8;
            display: grid; place-items: center;
            overflow: hidden;
        }
        .product-card .frame img { width: 100%; height: 100%; object-fit: cover; }

        .code-pill {
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: .7rem;
            color: var(--brand-700);
            background: var(--brand-100);
            border-radius: 5px;
            padding: .1rem .35rem;
        }

        .meta { font-size: .78rem; color: var(--muted); }

        .active-filters .badge {
            background: var(--brand-100) !important;
            color: var(--brand-900);
            font-weight: 500;
            cursor: pointer;
        }
        .active-filters .badge:hover { background: #d4e7f2 !important; }

        .skeleton {
            border-radius: 14px;
            background: linear-gradient(90deg, #eef2f5 25%, #f6f9fb 50%, #eef2f5 75%);
            background-size: 200% 100%;
            animation: shimmer 1.2s infinite;
            height: 250px;
        }
        @keyframes shimmer {
            from { background-position: 200% 0; }
            to   { background-position: -200% 0; }
        }
    </style>
</head>
<body>
    <div class="topbar">
        <div class="container d-flex justify-content-between align-items-center gap-3">
            <a href="{{ route('assistant.index') }}" class="d-flex align-items-center gap-2">
                <i class="bi bi-arrow-left"></i>
                <span class="fw-semibold">Cera · Ürün Asistanı</span>
            </a>
            @auth
                <a href="{{ route('dashboard') }}" class="small">
                    <i class="bi bi-speedometer2"></i>
                    <span class="d-none d-sm-inline">Panel</span>
                </a>
            @else
                <a href="{{ route('login') }}" class="small">
                    <i class="bi bi-box-arrow-in-right"></i>
                    <span class="d-none d-sm-inline">Giriş yap</span>
                </a>
            @endauth
        </div>
    </div>

    <div class="container py-4 py-lg-5">
        <div class="text-center mb-4">
            <h1 class="h3 fw-semibold mb-1">Katalog Arama</h1>
            <p class="text-muted small mb-0">
                {{ number_format($total) }} ürün arasında arayın — ürün adı, kod ya da ölçü yazın.
            </p>
        </div>

        <div class="search-wrap mx-auto mb-3" style="max-width: 680px;">
            <div class="search-shell">
                <i class="bi bi-search" style="color: var(--muted)"></i>
                <input type="text" id="q" autocomplete="off" spellcheck="false"
                       placeholder="Örn. Aqua lavabo, 60 cm, OC007G42U073Y01102"
                       aria-label="Ürün ara">
                <button type="button" id="clear-q" class="btn btn-sm btn-link text-muted d-none"
                        aria-label="Aramayı temizle">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div id="suggestions" class="suggestions d-none" role="listbox"></div>
        </div>

        <div class="card filter-card mb-4">
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-6 col-lg-2">
                        <label class="form-label small text-muted mb-1">Kategori</label>
                        <select id="f-category" class="form-select form-select-sm js-filter" data-key="category_id">
                            <option value="">Tümü</option>
                            @foreach ($categories as $c)
                                <option value="{{ $c->id }}">{{ $c->name['tr'] ?? '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-lg-2">
                        <label class="form-label small text-muted mb-1">Alt kategori</label>
                        <select id="f-subcategory" class="form-select form-select-sm js-filter" data-key="subcategory_id">
                            <option value="">Tümü</option>
                            @foreach ($subcategories as $s)
                                <option value="{{ $s->id }}" data-parent="{{ $s->category_id }}">{{ $s->name['tr'] ?? '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-lg-2">
                        <label class="form-label small text-muted mb-1">Ürün tipi</label>
                        <select id="f-type" class="form-select form-select-sm js-filter" data-key="product_type_id">
                            <option value="">Tümü</option>
                            @foreach ($productTypes as $pt)
                                <option value="{{ $pt->id }}" data-parent="{{ $pt->subcategory_id }}">{{ $pt->name['tr'] ?? '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-lg-2">
                        <label class="form-label small text-muted mb-1">Seri</label>
                        <select id="f-series" class="form-select form-select-sm js-filter" data-key="series_id">
                            <option value="">Tümü</option>
                            @foreach ($series as $s)
                                <option value="{{ $s->id }}">{{ $s->name['tr'] ?? '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-lg-2">
                        <label class="form-label small text-muted mb-1">Renk</label>
                        <select id="f-color" class="form-select form-select-sm js-filter" data-key="color_id">
                            <option value="">Tümü</option>
                            @foreach ($colors as $c)
                                <option value="{{ $c->id }}">{{ $c->name['tr'] ?? '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-lg-2">
                        <label class="form-label small text-muted mb-1">Sıralama</label>
                        <select id="f-sort" class="form-select form-select-sm js-filter" data-key="sort">
                            <option value="name">İsme göre</option>
                            <option value="size">Ölçüye göre</option>
                            <option value="newest">En yeni</option>
                        </select>
                    </div>
                </div>

                <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                    <div id="active-filters" class="active-filters d-flex flex-wrap gap-2"></div>
                    <button type="button" id="reset" class="btn btn-sm btn-outline-secondary ms-auto d-none">
                        <i class="bi bi-x-circle me-1"></i> Filtreleri temizle
                    </button>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <div id="result-count" class="small text-muted"></div>
            <a href="{{ route('assistant.index') }}" class="small text-decoration-none" style="color: var(--brand-700)">
                <i class="bi bi-chat-dots me-1"></i> Cera'ya sor
            </a>
        </div>

        <div id="results" class="row g-3"></div>

        <div id="empty" class="text-center py-5 d-none">
            <i class="bi bi-search display-6 text-secondary" style="opacity:.35"></i>
            <p class="mt-3 mb-1 fw-semibold">Sonuç bulunamadı</p>
            <p class="text-muted small mb-3">Farklı bir kelime deneyin ya da filtreleri gevşetin.</p>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('reset').click()">
                Filtreleri temizle
            </button>
        </div>

        <div class="text-center mt-4">
            <button type="button" id="more" class="btn btn-outline-secondary btn-sm d-none">
                Daha fazla göster
            </button>
        </div>
    </div>

    <script>
        const RESULTS_URL = @json(route('search.results'));
        const SUGGEST_URL = @json(route('search.suggest'));

        const input = document.getElementById('q');
        const clearBtn = document.getElementById('clear-q');
        const suggestBox = document.getElementById('suggestions');
        const results = document.getElementById('results');
        const empty = document.getElementById('empty');
        const countEl = document.getElementById('result-count');
        const moreBtn = document.getElementById('more');
        const resetBtn = document.getElementById('reset');
        const activeWrap = document.getElementById('active-filters');
        const filters = Array.from(document.querySelectorAll('.js-filter'));

        const elCategory = document.getElementById('f-category');
        const elSubcategory = document.getElementById('f-subcategory');
        const elType = document.getElementById('f-type');

        let page = 1;
        let lastPage = 1;
        // Every request carries a sequence number; a slow early response must
        // not overwrite the results of a later, faster one.
        let sequence = 0;

        function esc(value) {
            const div = document.createElement('div');
            div.textContent = value ?? '';
            return div.innerHTML;
        }

        function debounce(fn, wait) {
            let timer;
            return function (...args) {
                clearTimeout(timer);
                timer = setTimeout(() => fn.apply(this, args), wait);
            };
        }

        function currentParams() {
            const params = new URLSearchParams();
            const term = input.value.trim();
            if (term) params.set('q', term);
            filters.forEach(el => {
                if (el.value) params.set(el.dataset.key, el.value);
            });
            return params;
        }

        // --- Cascade: a child filter only offers children of its parent ------
        function cascade(child, parent) {
            const parentId = parent.value;
            Array.from(child.options).forEach(opt => {
                if (!opt.value) return;
                opt.hidden = Boolean(parentId) && opt.dataset.parent !== parentId;
            });
            if (child.selectedOptions[0]?.hidden) child.value = '';
        }

        function refreshCascade() {
            cascade(elSubcategory, elCategory);
            cascade(elType, elSubcategory);
        }

        // --- Active filter chips --------------------------------------------
        function renderActiveFilters() {
            activeWrap.replaceChildren();
            let any = false;

            filters.forEach(el => {
                // Sort always has a value; it is not a narrowing filter.
                if (!el.value || el.dataset.key === 'sort') return;
                any = true;

                const chip = document.createElement('span');
                chip.className = 'badge rounded-pill d-inline-flex align-items-center gap-1';
                chip.innerHTML = esc(el.selectedOptions[0].textContent.trim())
                    + ' <i class="bi bi-x"></i>';
                chip.title = 'Kaldır';
                chip.addEventListener('click', () => {
                    el.value = '';
                    refreshCascade();
                    search(true);
                });
                activeWrap.appendChild(chip);
            });

            resetBtn.classList.toggle('d-none', !any && !input.value.trim());
        }

        // --- Results ---------------------------------------------------------
        function card(p) {
            const col = document.createElement('div');
            col.className = 'col-6 col-md-4 col-lg-3';

            const meta = [p.series, p.subcategory].filter(Boolean).join(' · ');
            const spec = [p.dimensions, p.color].filter(Boolean).join(' · ');

            col.innerHTML =
                '<a class="product-card" href="' + esc(p.url) + '">' +
                '<div class="frame">' +
                (p.image
                    ? '<img src="' + esc(p.image) + '" alt="" loading="lazy">'
                    : '<i class="bi bi-image text-secondary" style="font-size:2rem;opacity:.35"></i>') +
                '</div>' +
                '<div class="p-2 d-flex flex-column gap-1 flex-grow-1">' +
                '<div class="small fw-semibold lh-sm">' + esc(p.name) + '</div>' +
                (meta ? '<div class="meta">' + esc(meta) + '</div>' : '') +
                (spec ? '<div class="meta">' + esc(spec) + '</div>' : '') +
                (p.code ? '<div class="mt-auto pt-1"><span class="code-pill">' + esc(p.code) + '</span></div>' : '') +
                '</div></a>';

            return col;
        }

        function showSkeletons() {
            results.replaceChildren();
            for (let i = 0; i < 8; i++) {
                const col = document.createElement('div');
                col.className = 'col-6 col-md-4 col-lg-3';
                col.innerHTML = '<div class="skeleton"></div>';
                results.appendChild(col);
            }
        }

        async function search(reset) {
            if (reset) page = 1;

            const mine = ++sequence;
            const params = currentParams();
            params.set('page', page);

            if (reset) showSkeletons();
            renderActiveFilters();

            let data;
            try {
                const res = await fetch(RESULTS_URL + '?' + params.toString(), {
                    headers: { 'Accept': 'application/json' },
                });
                data = await res.json();
            } catch (err) {
                if (mine === sequence) {
                    countEl.textContent = 'Bağlantı hatası. Lütfen tekrar deneyin.';
                    results.replaceChildren();
                }
                return;
            }

            if (mine !== sequence) return;   // a newer search already answered

            if (reset) results.replaceChildren();

            data.products.forEach(p => results.appendChild(card(p)));

            lastPage = data.last_page;
            countEl.textContent = data.total === 0
                ? ''
                : data.total + ' ürün bulundu' + (data.last_page > 1 ? ' · sayfa ' + data.page + '/' + data.last_page : '');

            empty.classList.toggle('d-none', data.total !== 0);
            moreBtn.classList.toggle('d-none', data.page >= data.last_page);
        }

        // --- Suggestions -----------------------------------------------------
        function hideSuggestions() {
            suggestBox.classList.add('d-none');
            suggestBox.replaceChildren();
        }

        async function suggest() {
            const term = input.value.trim();
            if (term.length < 2) return hideSuggestions();

            let data;
            try {
                const res = await fetch(SUGGEST_URL + '?q=' + encodeURIComponent(term), {
                    headers: { 'Accept': 'application/json' },
                });
                data = await res.json();
            } catch (err) {
                return hideSuggestions();
            }

            if (!data.suggestions.length) return hideSuggestions();

            suggestBox.replaceChildren();

            const labels = { series: 'Seri', subcategory: 'Kategori', product: 'Ürün' };

            data.suggestions.forEach(s => {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'suggestion';
                item.innerHTML =
                    '<span class="badge-type">' + esc(labels[s.type] || '') + '</span>' +
                    '<span class="flex-grow-1 text-truncate">' + esc(s.label) + '</span>' +
                    (s.code ? '<span class="code-pill">' + esc(s.code) + '</span>' : '');

                item.addEventListener('click', () => {
                    hideSuggestions();

                    // A taxonomy hit sets the matching filter — that is what
                    // makes this "assisted" rather than a plain text search.
                    if (s.type === 'series') {
                        input.value = '';
                        elCategory.value = '';
                        document.getElementById('f-series').value = String(s.id);
                    } else if (s.type === 'subcategory') {
                        input.value = '';
                        elCategory.value = '';
                        refreshCascade();
                        elSubcategory.value = String(s.id);
                    } else {
                        // A product suggestion goes straight to its sheet.
                        window.location.href = s.url;
                        return;
                    }

                    refreshCascade();
                    search(true);
                });

                suggestBox.appendChild(item);
            });

            suggestBox.classList.remove('d-none');
        }

        // --- Wiring ----------------------------------------------------------
        const debouncedSearch = debounce(() => search(true), 250);
        const debouncedSuggest = debounce(suggest, 180);

        input.addEventListener('input', function () {
            clearBtn.classList.toggle('d-none', this.value === '');
            debouncedSearch();
            debouncedSuggest();
        });

        input.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') hideSuggestions();
            if (event.key === 'Enter') {
                event.preventDefault();
                hideSuggestions();
                search(true);
            }
        });

        clearBtn.addEventListener('click', function () {
            input.value = '';
            clearBtn.classList.add('d-none');
            hideSuggestions();
            search(true);
            input.focus();
        });

        document.addEventListener('click', function (event) {
            if (!event.target.closest('.search-wrap')) hideSuggestions();
        });

        filters.forEach(el => el.addEventListener('change', function () {
            refreshCascade();
            search(true);
        }));

        moreBtn.addEventListener('click', function () {
            if (page >= lastPage) return;
            page++;
            search(false);
        });

        resetBtn.addEventListener('click', function () {
            input.value = '';
            clearBtn.classList.add('d-none');
            filters.forEach(el => { el.value = el.dataset.key === 'sort' ? 'name' : ''; });
            refreshCascade();
            search(true);
        });

        // A question carried over from the assistant: /search?q=...
        const initial = new URLSearchParams(window.location.search).get('q');
        if (initial) {
            input.value = initial;
            clearBtn.classList.remove('d-none');
        }

        refreshCascade();
        search(true);
        input.focus();
    </script>
</body>
</html>
