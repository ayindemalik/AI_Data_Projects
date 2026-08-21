@php
    // Turkish first, not app()->getLocale(): APP_LOCALE is 'en' while every
    // label on this page is Turkish, and rendering "Matte Black" under
    // "Özellikler" for a customer arriving from a Turkish answer reads as a bug.
    $t = fn (?array $field) => $field['tr'] ?? $field[app()->getLocale()] ?? $field['en'] ?? null;
    $name = $t($product->name);
    $description = $t($product->description);
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $name }} · {{ config('app.name') }}</title>
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

        .sheet {
            border: 0;
            border-radius: 20px;
            overflow: hidden;
            background: #fff;
            box-shadow: 0 20px 45px -25px rgba(4, 65, 95, .5), 0 2px 6px rgba(4, 65, 95, .06);
        }

        /* Gallery: one large frame, thumbnails swap into it. */
        .hero-frame {
            aspect-ratio: 4 / 3;
            background: #f1f5f8;
            border-radius: 14px;
            overflow: hidden;
            display: grid;
            place-items: center;
        }
        .hero-frame img { width: 100%; height: 100%; object-fit: contain; }

        .thumb {
            width: 68px; height: 68px;
            object-fit: cover;
            border-radius: 10px;
            border: 2px solid transparent;
            background: #f1f5f8;
            cursor: pointer;
            transition: border-color .15s ease, transform .12s ease;
        }
        .thumb:hover { transform: translateY(-2px); }
        .thumb.active { border-color: var(--brand-500); }

        .chip {
            display: inline-flex; align-items: center; gap: .35rem;
            background: var(--brand-100);
            color: var(--brand-900);
            border-radius: 999px;
            padding: .28rem .75rem;
            font-size: .82rem;
            font-weight: 500;
        }

        .spec-table th {
            width: 42%;
            font-weight: 500;
            color: var(--muted);
            white-space: nowrap;
        }
        .spec-table td { font-weight: 500; }
        .spec-table th, .spec-table td { padding: .55rem .25rem; border-bottom: 1px solid #eef2f5; }
        .spec-table tr:last-child th, .spec-table tr:last-child td { border-bottom: 0; }

        .section-title {
            font-size: .78rem;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--muted);
            font-weight: 600;
            margin-bottom: .6rem;
        }

        .doc-link {
            display: flex; align-items: center; gap: .65rem;
            padding: .7rem .9rem;
            border: 1px solid #e4ebf0;
            border-radius: 12px;
            text-decoration: none;
            color: var(--ink);
            transition: border-color .15s ease, background .15s ease, transform .12s ease;
        }
        .doc-link:hover {
            border-color: var(--brand-500);
            background: var(--brand-100);
            color: var(--brand-900);
            transform: translateY(-1px);
        }

        .code-pill {
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: .82rem;
            background: #f1f5f8;
            border: 1px solid #e0e8ee;
            border-radius: 8px;
            padding: .2rem .5rem;
        }

        .description-body { line-height: 1.7; }
        .description-body img { max-width: 100%; height: auto; border-radius: 10px; }
        .description-body :is(h1, h2, h3) { font-size: 1.05rem; font-weight: 600; margin-top: 1rem; }
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
            @endauth
        </div>
    </div>

    <div class="container py-4 py-lg-5">
        <div class="card sheet">
            <div class="row g-0">
                {{-- Gallery --}}
                <div class="col-lg-6 p-3 p-lg-4">
                    <div class="hero-frame mb-3">
                        @if ($cover)
                            <img id="hero" src="{{ $cover->url }}" alt="{{ $name }}">
                        @else
                            <i class="bi bi-image text-secondary" style="font-size:3rem;opacity:.4"></i>
                        @endif
                    </div>

                    @if ($gallery->isNotEmpty())
                        <div class="d-flex flex-wrap gap-2">
                            <img src="{{ $cover->url }}" class="thumb active" alt="" loading="lazy">
                            @foreach ($gallery as $image)
                                <img src="{{ $image->url }}" class="thumb" alt="" loading="lazy">
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Details --}}
                <div class="col-lg-6 p-3 p-lg-4 border-start-lg">
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        @if ($product->series)
                            <span class="chip"><i class="bi bi-collection"></i> {{ $t($product->series->name) }}</span>
                        @endif
                        @if ($product->color)
                            <span class="chip"><i class="bi bi-palette"></i> {{ $t($product->color->name) }}</span>
                        @endif
                        @if ($product->dimensions)
                            <span class="chip"><i class="bi bi-rulers"></i> {{ $product->dimensions }}</span>
                        @endif
                    </div>

                    <h1 class="h3 fw-semibold mb-3">{{ $name }}</h1>

                    @if ($product->sku || $product->sku_new)
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                            <span class="small text-muted">Ürün kodu</span>
                            @if ($product->sku_new)
                                <span class="code-pill">{{ $product->sku_new }}</span>
                            @endif
                            @if ($product->sku && $product->sku !== $product->sku_new)
                                <span class="code-pill">{{ $product->sku }}</span>
                            @endif
                        </div>
                    @endif

                    <div class="section-title">Özellikler</div>
                    <table class="table spec-table mb-4">
                        <tbody>
                            @foreach ([
                                'Kategori' => $product->category ? $t($product->category->name) : null,
                                'Alt kategori' => $product->subcategory ? $t($product->subcategory->name) : null,
                                'Ürün tipi' => $product->productType ? $t($product->productType->name) : null,
                                'Seri' => $product->series ? $t($product->series->name) : null,
                                'Renk' => $product->color ? $t($product->color->name) : null,
                                'Ölçüler' => $product->dimensions,
                                'Ağırlık' => $product->kg ? $product->kg . ' kg' : null,
                                'Palet adedi' => $product->palet_adeti,
                            ] as $label => $value)
                                @if (filled($value))
                                    <tr><th>{{ $label }}</th><td>{{ $value }}</td></tr>
                                @endif
                            @endforeach

                            @foreach ($product->measures as $measure)
                                @if (filled($measure->pivot->value))
                                    <tr><th>{{ $t($measure->name) }}</th><td>{{ $measure->pivot->value }}</td></tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>

                    @if ($product->variants->isNotEmpty())
                        <div class="section-title">Varyantlar</div>
                        <ul class="list-unstyled mb-4">
                            @foreach ($product->variants as $variant)
                                <li class="d-flex justify-content-between border-bottom py-2 small">
                                    <span>{{ $t($variant->name ?? []) ?: '—' }}</span>
                                    @if ($variant->variant_sku)
                                        <span class="code-pill">{{ $variant->variant_sku }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($product->documents->isNotEmpty())
                        <div class="section-title">Dokümanlar</div>
                        <div class="d-grid gap-2 mb-4">
                            @foreach ($product->documents as $document)
                                <a class="doc-link" target="_blank" rel="noopener"
                                   href="{{ \Illuminate\Support\Str::startsWith($document->file, ['http://', 'https://'])
                                            ? $document->file
                                            : \Illuminate\Support\Facades\Storage::disk('public')->url($document->file) }}">
                                    <i class="bi bi-file-earmark-arrow-down fs-5"></i>
                                    <span class="flex-grow-1">{{ $t($document->title ?? []) ?: $document->type }}</span>
                                    <i class="bi bi-box-arrow-up-right small"></i>
                                </a>
                            @endforeach
                        </div>
                    @endif

                    <div class="alert alert-light border small mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        Fiyat ve stok bilgisi için lütfen en yakın bayimizle iletişime geçin.
                    </div>
                </div>
            </div>

            @if (filled($description))
                <div class="border-top p-3 p-lg-4">
                    <div class="section-title">Açıklama</div>
                    {{-- Catalogue copy is authored HTML from the admin panel. --}}
                    <div class="description-body">{!! $description !!}</div>
                </div>
            @endif
        </div>

        <div class="text-center mt-4">
            <a href="{{ route('assistant.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-chat-dots me-1"></i> Cera'ya başka bir soru sor
            </a>
        </div>
    </div>

    <script>
        // Thumbnails swap the hero image; no lightbox, the frame is big enough.
        const hero = document.getElementById('hero');

        document.querySelectorAll('.thumb').forEach(thumb => {
            thumb.addEventListener('click', () => {
                if (!hero) return;
                hero.src = thumb.src;
                document.querySelectorAll('.thumb').forEach(t => t.classList.remove('active'));
                thumb.classList.add('active');
            });
        });
    </script>
</body>
</html>
