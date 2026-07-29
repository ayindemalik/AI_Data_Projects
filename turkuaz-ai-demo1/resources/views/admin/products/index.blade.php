@extends('layouts.admin')

@section('title', 'Products')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Products</h1>
        <a href="{{ route('admin.products.create') }}" class="btn btn-primary">+ Add Product</a>
    </div>

    <div class="mb-3">
        @if (request('trashed'))
            <a href="{{ route('admin.products.index') }}" class="btn btn-sm btn-outline-secondary">← Back to active products</a>
            <span class="ms-2 text-muted">Showing trashed products</span>
        @else
            <a href="{{ route('admin.products.index', ['trashed' => 1]) }}" class="btn btn-sm btn-outline-danger">View trashed</a>
        @endif
    </div>

    {{-- Cascading structured filters: Category > Subcategory > Product Type.
         Each also filters the table (client-side, instant). --}}
    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-2">
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Category</label>
                    <select id="filter-category" class="form-select form-select-sm">
                        <option value="">All Categories</option>
                        @foreach ($categories as $c)
                            <option value="{{ $c->name['tr'] ?? '' }}" data-id="{{ $c->id }}">{{ $c->name['tr'] ?? '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Subcategory</label>
                    <select id="filter-subcategory" class="form-select form-select-sm">
                        <option value="">All Subcategories</option>
                        @foreach ($subcategories as $s)
                            <option value="{{ $s->name['tr'] ?? '' }}" data-id="{{ $s->id }}" data-category-id="{{ $s->category_id }}">{{ $s->name['tr'] ?? '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Product Type</label>
                    <select id="filter-product-type" class="form-select form-select-sm">
                        <option value="">All Product Types</option>
                        @foreach ($productTypes as $pt)
                            <option value="{{ $pt->name['tr'] ?? '' }}" data-subcategory-id="{{ $pt->subcategory_id }}">{{ $pt->name['tr'] ?? '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Series</label>
                    <select id="filter-series" class="form-select form-select-sm">
                        <option value="">All Series</option>
                        @foreach ($series as $s)
                            <option value="{{ $s->name['tr'] ?? '' }}">{{ $s->name['tr'] ?? '' }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="row g-2 mt-1">
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Color</label>
                    <select id="filter-color" class="form-select form-select-sm">
                        <option value="">All Colors</option>
                        @foreach ($colors as $c)
                            <option value="{{ $c->name['tr'] ?? '' }}">{{ $c->name['tr'] ?? '' }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="mt-2">
                <button id="clear-filters" type="button" class="btn btn-sm btn-outline-secondary">Clear all filters</button>
                <span class="ms-2 small text-muted">Tip: red/amber badges below mark missing data to fix.</span>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <table id="products-table" class="table table-hover align-middle w-100">
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Name</th>
                        <th>SKU</th>
                        <th>New Code (2026)</th>
                        <th>Category</th>
                        <th>Subcategory</th>
                        <th>Product Type</th>
                        <th>Series</th>
                        <th>Color</th>
                        <th>KG</th>
                        <th>Desc</th>
                        <th>Status</th>
                        <th class="text-end no-sort">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($products as $product)
                        @php
                            $firstImage = $product->images->first();
                            $isPlaceholder = $firstImage && str_contains($firstImage->path ?? '', 'placeholder-product');
                            $hasRealImage = $firstImage && ! $isPlaceholder;
                            $seriesName = $product->series?->name['tr'] ?? null;
                            $colorName = $product->color?->name['tr'] ?? null;
                            $typeName = $product->productType?->name['tr'] ?? null;
                            $hasDesc = filled($product->description['tr'] ?? null);
                        @endphp
                        <tr>
                            <td style="width:60px;">
                                @if ($hasRealImage)
                                    <img src="{{ $firstImage->url }}" alt="" style="width:48px;height:48px;object-fit:cover;border-radius:4px;">
                                @else
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle" title="No real photo — placeholder in use">No photo</span>
                                @endif
                            </td>
                            <td>{{ $product->name['tr'] ?? '-' }}</td>
                            <td class="text-muted">{{ $product->sku ?? '—' }}</td>
                            <td>
                                @if ($product->sku_new)
                                    <span class="badge bg-info text-dark">{{ $product->sku_new }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>{{ $product->category?->name['tr'] ?? '—' }}</td>
                            <td>{{ $product->subcategory?->name['tr'] ?? '—' }}</td>
                            <td>
                                @if ($typeName)
                                    {{ $typeName }}
                                @else
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">None</span>
                                @endif
                            </td>
                            <td>
                                @if ($seriesName)
                                    {{ $seriesName }}
                                @else
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">None</span>
                                @endif
                            </td>
                            <td>
                                @if ($colorName)
                                    {{ $colorName }}
                                @else
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Missing</span>
                                @endif
                            </td>
                            <td data-order="{{ $product->kg ?? -1 }}">
                                @if (! is_null($product->kg))
                                    {{ rtrim(rtrim(number_format($product->kg, 2), '0'), '.') }}
                                @else
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Missing</span>
                                @endif
                            </td>
                            <td data-order="{{ $hasDesc ? 1 : 0 }}">
                                @if ($hasDesc)
                                    <span class="text-success" title="Has description">&check; yes</span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">no</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $product->status === 'active' ? 'bg-success' : 'bg-secondary' }}">
                                    {{ ucfirst($product->status) }}
                                </span>
                            </td>
                            <td class="text-end">
                                @if ($showTrashed)
                                    <form action="{{ route('admin.products.restore', $product->id) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-success">Restore</button>
                                    </form>
                                    <form action="{{ route('admin.products.force-delete', $product->id) }}" method="POST" class="d-inline"
                                          onsubmit="return confirm('Permanently delete this product and its images? This cannot be undone.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                @else
                                    <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                                    <form action="{{ route('admin.products.destroy', $product) }}" method="POST" class="d-inline"
                                          onsubmit="return confirm('Move this product to trash?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.bootstrap5.min.css">
    <style>
        div.dt-container .dt-search { margin-bottom: .75rem; }
        div.dt-container .dt-paging { margin-top: .75rem; }
        #products-table td .badge { font-weight: 500; }
    </style>
@endpush

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Column index map — keep in sync with <thead> above.
            const COL = {
                category: 4, subcategory: 5, productType: 6, series: 7, color: 8
            };

            const table = new DataTable('#products-table', {
                columnDefs: [{ targets: 'no-sort', orderable: false, searchable: false }],
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
                order: [[1, 'asc']],
                layout: {
                    topStart: 'pageLength', topEnd: 'search',
                    bottomStart: 'info', bottomEnd: 'paging'
                },
            });

            function esc(s) { return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }

            const elCat = document.getElementById('filter-category');
            const elSub = document.getElementById('filter-subcategory');
            const elType = document.getElementById('filter-product-type');

            function columnSearch(colIndex, val) {
                if (val === '') table.column(colIndex).search('');
                else table.column(colIndex).search('^' + esc(val) + '$', true, false);
            }

            // --- Cascade: hide child options whose parent doesn't match. ---
            function refreshSubcategoryOptions() {
                const catId = elCat.selectedOptions[0]?.dataset.id || '';
                Array.from(elSub.options).forEach(opt => {
                    if (!opt.value) return;
                    opt.hidden = !(!catId || opt.dataset.categoryId === catId);
                });
                if (elSub.selectedOptions[0]?.hidden) elSub.value = '';
            }

            function refreshProductTypeOptions() {
                const subId = elSub.selectedOptions[0]?.dataset.id || '';
                Array.from(elType.options).forEach(opt => {
                    if (!opt.value) return;
                    opt.hidden = !(!subId || opt.dataset.subcategoryId === subId);
                });
                if (elType.selectedOptions[0]?.hidden) elType.value = '';
            }

            elCat.addEventListener('change', function () {
                columnSearch(COL.category, this.value);
                refreshSubcategoryOptions();
                refreshProductTypeOptions();
                columnSearch(COL.subcategory, elSub.value);
                columnSearch(COL.productType, elType.value);
                table.draw();
            });

            elSub.addEventListener('change', function () {
                columnSearch(COL.subcategory, this.value);
                refreshProductTypeOptions();
                columnSearch(COL.productType, elType.value);
                table.draw();
            });

            elType.addEventListener('change', function () {
                columnSearch(COL.productType, this.value);
                table.draw();
            });

            document.getElementById('filter-series')?.addEventListener('change', function () {
                columnSearch(COL.series, this.value); table.draw();
            });
            document.getElementById('filter-color')?.addEventListener('change', function () {
                columnSearch(COL.color, this.value); table.draw();
            });

            document.getElementById('clear-filters')?.addEventListener('click', function () {
                [elCat, elSub, elType,
                 document.getElementById('filter-series'),
                 document.getElementById('filter-color')].forEach(el => { if (el) el.value = ''; });
                refreshSubcategoryOptions();
                refreshProductTypeOptions();
                table.search('');
                table.columns().search('');
                table.draw();
            });
        });
    </script>
@endpush
