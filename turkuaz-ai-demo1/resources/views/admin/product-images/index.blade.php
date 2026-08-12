@extends('layouts.admin')

@section('title', 'Assign Images')

@section('content')
    @php
        $isPlaceholder = fn ($image) => str_contains($image->path ?? '', 'placeholder-product');
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-1">Assign Images</h1>
            <p class="text-muted mb-0 small">
                Pick a product on the left and one or more images on the right, then Assign.
                The image <strong>moves</strong> — it leaves the product currently holding it.
            </p>
        </div>
        <a href="{{ route('admin.products.index', ['missing' => 'image']) }}" class="btn btn-sm btn-outline-secondary">
            Products with no photo
        </a>
    </div>

    <form method="POST" action="{{ route('admin.product-images.assign') }}" id="assign-form">
        @csrf
        @method('PUT')

        <div class="row g-3">
            {{-- ── Left: products ───────────────────────────────────────── --}}
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header bg-white">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong>Products</strong>
                            <span class="small text-muted" id="product-count"></span>
                        </div>
                        <input type="search" id="product-search" class="form-control form-control-sm mb-2"
                               placeholder="Search SKU, new code or name…" autocomplete="off">
                        <div class="btn-group btn-group-sm w-100" role="group">
                            <input type="radio" class="btn-check" name="product-filter" id="pf-all" value="" checked>
                            <label class="btn btn-outline-secondary" for="pf-all">All</label>

                            <input type="radio" class="btn-check" name="product-filter" id="pf-none" value="0">
                            <label class="btn btn-outline-danger" for="pf-none">No photo</label>

                            <input type="radio" class="btn-check" name="product-filter" id="pf-has" value="1">
                            <label class="btn btn-outline-success" for="pf-has">Has photo</label>
                        </div>
                    </div>

                    <div class="card-body p-0 overflow-auto" style="max-height:60vh;">
                        <ul class="list-group list-group-flush" id="product-list">
                            @foreach ($products as $product)
                                @php
                                    $real = $product->images->reject($isPlaceholder);
                                    $name = $product->name['tr'] ?? '—';
                                @endphp
                                <li class="list-group-item product-row"
                                    data-search="{{ Str::lower(trim("{$product->sku} {$product->sku_new} {$name}")) }}"
                                    data-has-photo="{{ $real->isNotEmpty() ? 1 : 0 }}">
                                    <div class="form-check d-flex align-items-center gap-2 mb-0">
                                        <input class="form-check-input flex-shrink-0 mt-0" type="radio" name="product_id"
                                               value="{{ $product->id }}" id="prod_{{ $product->id }}"
                                               data-label="{{ $name }}{{ $product->sku ? " [{$product->sku}]" : '' }}"
                                               @checked(old('product_id') == $product->id)>

                                        <label class="form-check-label d-flex align-items-center gap-2 flex-grow-1"
                                               for="prod_{{ $product->id }}" style="cursor:pointer;">
                                            @if ($real->isNotEmpty())
                                                <span class="d-flex gap-1 flex-shrink-0">
                                                    @foreach ($real->take(3) as $image)
                                                        <img src="{{ $image->url }}" alt="" loading="lazy"
                                                             style="width:44px;height:44px;object-fit:cover;border-radius:4px;">
                                                    @endforeach
                                                    @if ($real->count() > 3)
                                                        <span class="badge bg-secondary align-self-center">+{{ $real->count() - 3 }}</span>
                                                    @endif
                                                </span>
                                            @else
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle flex-shrink-0"
                                                      style="width:44px;height:44px;display:flex;align-items:center;justify-content:center;">
                                                    None
                                                </span>
                                            @endif

                                            <span class="flex-grow-1 lh-sm">
                                                {{ $name }}<br>
                                                <span class="small text-muted">
                                                    {{ $product->sku ?: 'no SKU' }}
                                                    @if ($product->category)
                                                        · {{ $product->category->name['tr'] ?? '' }}
                                                    @endif
                                                </span>
                                            </span>
                                        </label>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>

            {{-- ── Right: every real product_images row ──────────────────── --}}
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header bg-white">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong>Image rows</strong>
                            <span class="small text-muted" id="image-count"></span>
                        </div>
                        <input type="search" id="image-search" class="form-control form-control-sm mb-2"
                               placeholder="Search file name or the SKU/name holding it…" autocomplete="off">
                        <div class="btn-group btn-group-sm w-100" role="group">
                            <input type="radio" class="btn-check" name="image-filter" id="if-all" value="" checked>
                            <label class="btn btn-outline-secondary" for="if-all">All</label>

                            <input type="radio" class="btn-check" name="image-filter" id="if-shared" value="1">
                            <label class="btn btn-outline-warning" for="if-shared">Shared photo</label>

                            <input type="radio" class="btn-check" name="image-filter" id="if-sole" value="0">
                            <label class="btn btn-outline-secondary" for="if-sole">Sole copy</label>
                        </div>
                    </div>

                    <div class="card-body p-0 overflow-auto" style="max-height:60vh;">
                        <ul class="list-group list-group-flush" id="image-list">
                            @foreach ($images as $image)
                                @php
                                    $owner = $image->product;
                                    $ownerName = $owner?->name['tr'] ?? 'unknown product';
                                    $ownerLabel = $owner?->sku ? "{$ownerName} [{$owner->sku}]" : $ownerName;
                                    $shared = $sharedPaths->has($image->path);
                                @endphp
                                <li class="list-group-item image-row"
                                    data-search="{{ Str::lower(trim(basename($image->path) . " {$owner?->sku} {$ownerName}")) }}"
                                    data-shared="{{ $shared ? 1 : 0 }}"
                                    data-owner="{{ $image->product_id }}"
                                    data-owner-label="{{ $ownerLabel }}"
                                    data-owner-total="{{ $ownerTotals[$image->product_id] ?? 1 }}">
                                    <div class="form-check d-flex align-items-center gap-2 mb-0">
                                        <input class="form-check-input flex-shrink-0 mt-0" type="checkbox" name="image_ids[]"
                                               value="{{ $image->id }}" id="img_{{ $image->id }}">

                                        <label class="form-check-label d-flex align-items-center gap-2 flex-grow-1"
                                               for="img_{{ $image->id }}" style="cursor:pointer;">
                                            <img src="{{ $image->url }}" alt="" loading="lazy"
                                                 style="width:56px;height:56px;object-fit:cover;border-radius:4px;" class="flex-shrink-0">

                                            <span class="flex-grow-1 lh-sm">
                                                <span class="small text-muted">now on</span> {{ $ownerLabel }}
                                                @if ($shared)
                                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">shared</span>
                                                @endif
                                                <br>
                                                <span class="small text-muted font-monospace text-break">{{ basename($image->path) }}</span>
                                            </span>
                                        </label>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Sticky action bar ─────────────────────────────────────────── --}}
        <div class="card mt-3 position-sticky bottom-0 shadow-sm">
            <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="small">
                    <div id="selection-summary" class="text-muted">Nothing selected yet.</div>
                    <div id="selection-warning" class="text-danger mt-1 d-none"></div>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary" id="clear-selection">Clear selection</button>
                    <button type="submit" class="btn btn-primary" id="assign-btn" disabled>Assign selected images</button>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const productRows = Array.from(document.querySelectorAll('.product-row'));
            const imageRows = Array.from(document.querySelectorAll('.image-row'));
            const productCount = document.getElementById('product-count');
            const imageCount = document.getElementById('image-count');
            const summary = document.getElementById('selection-summary');
            const warning = document.getElementById('selection-warning');
            const assignBtn = document.getElementById('assign-btn');

            // --- Filtering: plain show/hide, both lists are small enough. ---
            function applyFilter(rows, term, flag, flagAttr, countEl, noun) {
                let shown = 0;
                rows.forEach(row => {
                    const matchesTerm = !term || row.dataset.search.includes(term);
                    const matchesFlag = flag === '' || row.dataset[flagAttr] === flag;
                    const visible = matchesTerm && matchesFlag;
                    row.hidden = !visible;
                    if (visible) shown++;
                });
                countEl.textContent = `${shown} of ${rows.length} ${noun}`;
            }

            function filterProducts() {
                applyFilter(
                    productRows,
                    document.getElementById('product-search').value.trim().toLowerCase(),
                    document.querySelector('input[name="product-filter"]:checked').value,
                    'hasPhoto', productCount, 'products'
                );
            }

            function filterImages() {
                applyFilter(
                    imageRows,
                    document.getElementById('image-search').value.trim().toLowerCase(),
                    document.querySelector('input[name="image-filter"]:checked').value,
                    'shared', imageCount, 'rows'
                );
            }

            document.getElementById('product-search').addEventListener('input', filterProducts);
            document.getElementById('image-search').addEventListener('input', filterImages);
            document.querySelectorAll('input[name="product-filter"]').forEach(el => el.addEventListener('change', filterProducts));
            document.querySelectorAll('input[name="image-filter"]').forEach(el => el.addEventListener('change', filterImages));

            // --- Selection summary + "this empties a product" warning. ---
            function refreshSelection() {
                const target = document.querySelector('input[name="product_id"]:checked');
                const checked = imageRows.filter(row => row.querySelector('input[type=checkbox]').checked);

                assignBtn.disabled = !target || checked.length === 0;

                if (!target && checked.length === 0) {
                    summary.textContent = 'Nothing selected yet.';
                    summary.className = 'text-muted';
                } else {
                    const targetText = target ? target.dataset.label : 'no product yet';
                    summary.innerHTML = `Assigning <strong>${checked.length}</strong> image(s) to <strong>${targetText}</strong>.`;
                    summary.className = '';
                }

                // A source loses its last photo when every real row it holds is checked.
                const takenFrom = new Map();
                checked.forEach(row => {
                    const owner = row.dataset.owner;
                    if (target && owner === target.value) return; // already on the target
                    const entry = takenFrom.get(owner) || { taken: 0, total: +row.dataset.ownerTotal, label: row.dataset.ownerLabel };
                    entry.taken++;
                    takenFrom.set(owner, entry);
                });

                const emptied = Array.from(takenFrom.values()).filter(e => e.taken >= e.total).map(e => e.label);

                warning.classList.toggle('d-none', emptied.length === 0);
                if (emptied.length) {
                    warning.innerHTML = `⚠ This leaves ${emptied.length} product(s) with no photo: <strong>${emptied.join(', ')}</strong>`;
                }
            }

            document.getElementById('product-list').addEventListener('change', refreshSelection);
            document.getElementById('image-list').addEventListener('change', refreshSelection);

            document.getElementById('clear-selection').addEventListener('click', function () {
                document.querySelectorAll('input[name="product_id"]:checked, input[name="image_ids[]"]:checked')
                    .forEach(el => { el.checked = false; });
                refreshSelection();
            });

            document.getElementById('assign-form').addEventListener('submit', function (event) {
                if (warning.classList.contains('d-none')) return;
                if (!confirm(warning.textContent.replace('⚠ ', '') + '\n\nContinue?')) event.preventDefault();
            });

            filterProducts();
            filterImages();
            refreshSelection();
        });
    </script>
@endpush
