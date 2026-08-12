@extends('layouts.admin')

@section('title', 'Image Paths')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-1">Image Paths</h1>
            <p class="text-muted mb-0 small">
                One row per <code>product_images</code> record. Edit any field on a line and press
                <strong>Save</strong> (or Enter) to write the whole line back — no page reload.
            </p>
        </div>
        <a href="{{ route('admin.product-images.index') }}" class="btn btn-sm btn-outline-secondary">
            Assign images →
        </a>
    </div>

    {{-- Cascading structured filters, mirroring the products table. --}}
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
                    <label class="form-label small text-muted mb-1">Image status</label>
                    <select id="filter-status" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="real">Has a real photo</option>
                        <option value="placeholder">Placeholder only</option>
                        <option value="none">No image row</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button id="clear-filters" type="button" class="btn btn-sm btn-outline-secondary">Clear all filters</button>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <table id="paths-table" class="table table-hover align-middle w-100">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th style="min-width:130px;">SKU</th>
                        <th style="min-width:150px;">New Code</th>
                        <th style="min-width:220px;">Name</th>
                        <th style="min-width:150px;">Series</th>
                        <th style="min-width:150px;">Category</th>
                        <th style="min-width:150px;">Subcategory</th>
                        <th style="min-width:150px;">Product Type</th>
                        <th class="no-sort">Preview</th>
                        <th style="min-width:300px;">Image Path</th>
                        <th class="no-sort text-end" style="min-width:150px;">Actions</th>
                        <th>Status</th>{{-- hidden, drives the status filter --}}
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        @php
                            $product = $row['product'];
                            $image = $row['image'];
                            $path = $image->path ?? '';
                            $isPlaceholder = $image && str_contains($path, 'placeholder-product');
                            $status = !$image ? 'none' : ($isPlaceholder ? 'placeholder' : 'real');
                            $nameTr = $product->name['tr'] ?? '';

                            // Each select ships with its current option only; the rest
                            // arrive from TAXONOMY on first focus.
                            $taxCells = [
                                'series_id' => [$product->series_id, $product->series?->name['tr'] ?? ''],
                                'category_id' => [$product->category_id, $product->category?->name['tr'] ?? ''],
                                'subcategory_id' => [$product->subcategory_id, $product->subcategory?->name['tr'] ?? ''],
                                'product_type_id' => [$product->productType?->id, $product->productType?->name['tr'] ?? ''],
                            ];
                        @endphp
{{-- Deliberately flush left: this block is emitted 727 times, and normal Blade
     indentation alone added ~2 MB of leading whitespace to the response.
     data-search/-order carry each cell's filter and sort value, because a cell
     rendering an <input> or <select> has no text for DataTables to index. The
     dirty-tracking baseline (data-original) is stamped on by JS at startup. --}}
<tr data-image-id="{{ $image->id ?? '' }}" data-product-id="{{ $product->id }}">
<td class="text-muted">{{ $product->id }}</td>
<td data-search="{{ $product->sku }}" data-order="{{ $product->sku }}"><input type="text" class="form-control form-control-sm js-field" data-field="sku" value="{{ $product->sku }}"></td>
<td data-search="{{ $product->sku_new }}" data-order="{{ $product->sku_new }}"><input type="text" class="form-control form-control-sm js-field" data-field="sku_new" value="{{ $product->sku_new }}"></td>
<td data-search="{{ $nameTr }}" data-order="{{ $nameTr }}"><input type="text" class="form-control form-control-sm js-field" data-field="name_tr" value="{{ $nameTr }}"></td>
@foreach ($taxCells as $field => [$id, $label])
<td data-search="{{ $label }}" data-order="{{ $label }}"><select class="form-select form-select-sm js-field js-tax" data-field="{{ $field }}"><option value="">—</option>@if ($id)<option value="{{ $id }}" selected>{{ $label }}</option>@endif</select></td>
@endforeach
<td class="preview-cell">@if ($image)<img src="{{ $image->url }}" alt="" loading="lazy" class="js-preview thumb">@else<img src="" alt="" hidden class="js-preview thumb"><span class="text-muted small js-no-image">—</span>@endif</td>
<td data-search="{{ $path }}" data-order="{{ $path }}"><input type="text" class="form-control form-control-sm font-monospace js-field js-path" data-field="path" value="{{ $path }}" placeholder="https://… or products/12/photo.jpg"><div class="small mt-1 js-feedback">@if ($image)<span class="text-muted">product_images #{{ $image->id }}</span>@else<span class="text-warning-emphasis">no image row yet — saving creates one</span>@endif</div></td>
<td class="text-end text-nowrap"><div class="btn-group btn-group-sm"><button type="button" class="btn btn-outline-secondary js-view" title="View image" @disabled(!$image)><i class="bi bi-eye"></i></button><button type="button" class="btn btn-outline-primary js-save" title="Save this line" disabled>Save</button><button type="button" class="btn btn-outline-danger js-delete" title="Delete this image row" @disabled(!$image)><i class="bi bi-trash"></i></button></div></td>
<td>{{ $status }}</td>
</tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Shared preview modal, filled in on demand. --}}
    <div class="modal fade" id="image-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="image-modal-title">Image</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="image-modal-img" src="" alt="" class="img-fluid rounded mb-3" style="max-height:60vh;">
                    <p id="image-modal-error" class="text-danger small d-none mb-2">
                        This path did not load. It may be wrong, or the file may be missing.
                    </p>
                    <p class="small text-muted font-monospace text-break mb-0" id="image-modal-path"></p>
                </div>
                <div class="modal-footer">
                    <a id="image-modal-open" href="#" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">
                        Open in new tab
                    </a>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.bootstrap5.min.css">
    <style>
        div.dt-container .dt-search { margin-bottom: .75rem; }
        div.dt-container .dt-paging { margin-top: .75rem; }
        #paths-table tr.is-dirty > td { background-color: #fff8e1; }
        #paths-table tr.is-saved > td { background-color: #e8f5e9; }
        #paths-table td .form-control-sm, #paths-table td .form-select-sm { font-size: .8rem; }
        #paths-table td.preview-cell { width: 64px; }
        #paths-table img.thumb { width: 48px; height: 48px; object-fit: cover; border-radius: 4px; }
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
                sku: 1, skuNew: 2, name: 3,
                series: 4, category: 5, subcategory: 6, productType: 7,
                path: 9, status: 11,
            };

            // Which column shows which editable field, for post-save resync.
            const FIELD_COL = {
                sku: COL.sku, sku_new: COL.skuNew, name_tr: COL.name,
                series_id: COL.series, category_id: COL.category,
                subcategory_id: COL.subcategory, product_type_id: COL.productType,
                path: COL.path,
            };

            const TAXONOMY = @json($taxonomy);

            // Dirty tracking compares each control against its starting value.
            // Stamped here rather than rendered as data-original on every field,
            // which duplicated all 727 rows' values into the response. Must run
            // before DataTables init, while every row is still in the document.
            document.querySelectorAll('#paths-table .js-field')
                .forEach(el => { el.dataset.original = el.value; });

            const table = new DataTable('#paths-table', {
                columnDefs: [
                    { targets: 'no-sort', orderable: false, searchable: false },
                    { targets: COL.status, visible: false },
                ],
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
                order: [[COL.name, 'asc']],
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
                columnSearch(COL.productType, this.value); table.draw();
            });
            document.getElementById('filter-series').addEventListener('change', function () {
                columnSearch(COL.series, this.value); table.draw();
            });
            document.getElementById('filter-status').addEventListener('change', function () {
                columnSearch(COL.status, this.value); table.draw();
            });

            document.getElementById('clear-filters').addEventListener('click', function () {
                [elCat, elSub, elType,
                 document.getElementById('filter-series'),
                 document.getElementById('filter-status')].forEach(el => { el.value = ''; });
                refreshSubcategoryOptions();
                refreshProductTypeOptions();
                table.search('');
                table.columns().search('');
                table.draw();
            });

            // --- Lazy row selects -------------------------------------------
            // Rendering 104 options into 727 rows up front is ~76k nodes, so a
            // select carries only its current value until the operator opens it.
            const PARENT_FIELD = { subcategory_id: 'category_id', product_type_id: 'subcategory_id' };

            function populate(select) {
                const field = select.dataset.field;
                const parentField = PARENT_FIELD[field];
                const parentValue = parentField
                    ? select.closest('tr').querySelector(`[data-field="${parentField}"]`).value
                    : '';

                if (select.dataset.populatedFor === (parentValue || '*')) return;

                const current = select.value;
                const currentLabel = select.selectedOptions[0]?.textContent ?? '';

                select.replaceChildren(new Option('—', ''));

                TAXONOMY[field].forEach(item => {
                    // With a parent chosen, show only its children — but never hide
                    // the value already on the record, or saving would silently drop it.
                    if (parentValue && String(item.parent) !== parentValue && String(item.id) !== current) return;
                    select.appendChild(new Option(item.name, item.id, false, String(item.id) === current));
                });

                // Value survived nowhere in the list (deleted/inactive term) — keep it.
                if (current && !select.value) {
                    select.appendChild(new Option(currentLabel, current, false, true));
                }

                select.dataset.populatedFor = parentValue || '*';
            }

            // --- Dirty tracking ---------------------------------------------
            function fieldsOf(row) { return Array.from(row.querySelectorAll('.js-field')); }

            function isDirty(row) {
                return fieldsOf(row).some(el => el.value !== el.dataset.original);
            }

            function markDirty(row) {
                const dirty = isDirty(row);
                const path = row.querySelector('.js-path');
                const name = row.querySelector('[data-field="name_tr"]');

                row.classList.toggle('is-dirty', dirty);
                row.classList.remove('is-saved');
                // A line always needs a path and a name to be savable.
                row.querySelector('.js-save').disabled =
                    !dirty || path.value.trim() === '' || name.value.trim() === '';
            }

            function feedback(row, html, cls) {
                row.querySelector('.js-feedback').innerHTML = `<span class="${cls}">${html}</span>`;
            }

            // --- Keeping DataTables' cached search/sort values honest --------
            // These cells render inputs, so their filter value lives in
            // data-search/data-order and must be re-pointed after every write.
            function syncCell(row, colIndex, value) {
                const cell = table.cell(row, colIndex);
                const node = cell.node();
                if (!node) return;
                node.dataset.search = value ?? '';
                node.dataset.order = value ?? '';
                cell.invalidate('dom');
            }

            function applyPayload(row, data) {
                const p = data.product;

                row.dataset.imageId = data.id;

                const set = (field, value) => {
                    const el = row.querySelector(`[data-field="${field}"]`);
                    if (!el) return;
                    el.value = value ?? '';
                    el.dataset.original = el.value;
                };

                set('sku', p.sku);
                set('sku_new', p.sku_new);
                set('name_tr', p.name_tr);
                set('path', data.path);

                // Selects may not hold the saved option yet if they were never
                // opened; make sure the value exists before assigning it.
                [['series_id', p.series_id, p.series],
                 ['category_id', p.category_id, p.category],
                 ['subcategory_id', p.subcategory_id, p.subcategory],
                 ['product_type_id', p.product_type_id, p.product_type]].forEach(([field, id, label]) => {
                    const el = row.querySelector(`[data-field="${field}"]`);
                    if (!el) return;
                    const value = id ? String(id) : '';
                    if (value && !Array.from(el.options).some(o => o.value === value)) {
                        el.appendChild(new Option(label, value));
                    }
                    el.value = value;
                    el.dataset.original = value;
                    delete el.dataset.populatedFor;   // parent may have changed
                });

                syncCell(row, COL.sku, p.sku);
                syncCell(row, COL.skuNew, p.sku_new);
                syncCell(row, COL.name, p.name_tr);
                syncCell(row, COL.series, p.series);
                syncCell(row, COL.category, p.category);
                syncCell(row, COL.subcategory, p.subcategory);
                syncCell(row, COL.productType, p.product_type);
                syncCell(row, COL.path, data.path);
                table.cell(row, COL.status).data(data.is_placeholder ? 'placeholder' : 'real');

                const preview = row.querySelector('.js-preview');
                preview.src = data.url;
                preview.hidden = false;
                row.querySelector('.js-no-image')?.remove();
                row.querySelector('.js-view').disabled = false;
                row.querySelector('.js-delete').disabled = false;

                row.classList.remove('is-dirty');
                row.classList.add('is-saved');
                row.querySelector('.js-save').disabled = true;
            }

            // One product can own several image rows, so its other lines have to
            // pick up the product-level edits too.
            function syncSiblings(sourceRow, data) {
                const productId = sourceRow.dataset.productId;
                const p = data.product;

                table.rows().nodes().each(function (node) {
                    if (node === sourceRow || node.dataset.productId !== productId) return;
                    if (isDirty(node)) return;   // don't clobber someone's unsaved edit

                    applyPayload(node, {
                        ...data,
                        id: node.dataset.imageId,
                        path: node.querySelector('.js-path').value,
                        url: node.querySelector('.js-preview').src,
                        is_placeholder: table.cell(node, COL.status).data() === 'placeholder',
                        product: p,
                    });
                    node.classList.remove('is-saved');
                });
            }

            // --- Requests ----------------------------------------------------
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            const rowUrl = @json(route('admin.product-image-paths.update', ['productImage' => '__ID__']));
            const storeUrl = @json(route('admin.product-image-paths.store'));
            const tableEl = document.getElementById('paths-table');

            function lineBody(row) {
                const body = { product_id: row.dataset.productId };
                fieldsOf(row).forEach(el => { body[el.dataset.field] = el.value; });
                return body;
            }

            async function request(url, method, body) {
                const res = await fetch(url, {
                    method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                    },
                    body: body ? JSON.stringify(body) : undefined,
                });

                return { ok: res.ok, data: await res.json() };
            }

            function errorText(data) {
                // 422 from validation carries {errors:{field:[…]}}; ours carries {message}.
                const first = data.errors && Object.values(data.errors)[0]?.[0];
                return first || data.message || 'Save failed.';
            }

            async function save(row) {
                const button = row.querySelector('.js-save');
                const imageId = row.dataset.imageId;

                button.disabled = true;
                feedback(row, 'Saving…', 'text-muted');

                try {
                    const { ok, data } = await request(
                        imageId ? rowUrl.replace('__ID__', imageId) : storeUrl,
                        imageId ? 'PUT' : 'POST',
                        lineBody(row)
                    );

                    if (!ok) {
                        feedback(row, errorText(data), 'text-danger');
                        button.disabled = false;
                        return;
                    }

                    applyPayload(row, data);
                    syncSiblings(row, data);
                    feedback(row, `${data.message} product_images #${data.id}`, 'text-success');
                } catch (error) {
                    feedback(row, 'Network error — nothing was saved.', 'text-danger');
                    button.disabled = false;
                }
            }

            async function remove(row) {
                const imageId = row.dataset.imageId;
                if (!imageId) return;

                const name = row.querySelector('[data-field="name_tr"]').value;
                if (!confirm(`Delete image row #${imageId} from "${name}"?\n\n`
                    + 'The product itself is kept. If this is its last image, the placeholder comes back.\n'
                    + 'An uploaded file no other product uses is deleted from disk. This cannot be undone.')) {
                    return;
                }

                feedback(row, 'Deleting…', 'text-muted');

                try {
                    const { ok, data } = await request(rowUrl.replace('__ID__', imageId), 'DELETE', null);

                    if (!ok) {
                        feedback(row, errorText(data), 'text-danger');
                        return;
                    }

                    if (data.replacement) {
                        // Last image gone: the line stays, now showing the placeholder.
                        row.dataset.imageId = data.replacement.id;
                        const input = row.querySelector('.js-path');
                        input.value = data.replacement.path;
                        input.dataset.original = data.replacement.path;
                        row.querySelector('.js-preview').src = data.replacement.url;
                        syncCell(row, COL.path, data.replacement.path);
                        table.cell(row, COL.status).data('placeholder');
                        row.classList.remove('is-dirty', 'is-saved');
                        row.querySelector('.js-save').disabled = true;
                        feedback(row, `${data.message} Now product_images #${data.replacement.id}`, 'text-warning-emphasis');
                    } else {
                        // The product still has other photos, so drop the line.
                        table.row(row).remove().draw(false);
                    }
                } catch (error) {
                    feedback(row, 'Network error — nothing was deleted.', 'text-danger');
                }
            }

            // --- Image modal -------------------------------------------------
            const modalEl = document.getElementById('image-modal');
            const modal = new bootstrap.Modal(modalEl);
            const modalImg = document.getElementById('image-modal-img');
            const modalError = document.getElementById('image-modal-error');

            modalImg.addEventListener('load', () => modalError.classList.add('d-none'));
            modalImg.addEventListener('error', () => modalError.classList.remove('d-none'));

            function view(row) {
                const path = row.querySelector('.js-path').value;
                const url = row.querySelector('.js-preview').src;
                const sku = row.querySelector('[data-field="sku"]').value;
                const name = row.querySelector('[data-field="name_tr"]').value;

                document.getElementById('image-modal-title').textContent =
                    `${name}${sku ? ` [${sku}]` : ''} · product_images #${row.dataset.imageId}`;
                document.getElementById('image-modal-path').textContent = path;
                document.getElementById('image-modal-open').href = url;

                modalError.classList.add('d-none');
                modalImg.src = url;
                modal.show();
            }

            modalEl.addEventListener('hidden.bs.modal', () => { modalImg.src = ''; });

            // --- Delegated row events ---------------------------------------
            tableEl.addEventListener('input', function (event) {
                if (event.target.classList.contains('js-field')) markDirty(event.target.closest('tr'));
            });

            tableEl.addEventListener('change', function (event) {
                const el = event.target;
                if (!el.classList.contains('js-field')) return;

                const row = el.closest('tr');

                // Choosing a parent narrows its children, and invalidates a child
                // that no longer belongs to it.
                Object.entries(PARENT_FIELD).forEach(([child, parent]) => {
                    if (parent !== el.dataset.field) return;
                    const childEl = row.querySelector(`[data-field="${child}"]`);
                    delete childEl.dataset.populatedFor;

                    const chosen = TAXONOMY[child].find(i => String(i.id) === childEl.value);
                    if (chosen && String(chosen.parent) !== el.value) childEl.value = '';
                });

                markDirty(row);
            });

            // focusin, not focus — select elements need the bubbling variant.
            tableEl.addEventListener('focusin', function (event) {
                if (event.target.classList.contains('js-tax')) populate(event.target);
            });

            tableEl.addEventListener('click', function (event) {
                const button = event.target.closest('button');
                if (!button) return;
                const row = button.closest('tr');

                if (button.classList.contains('js-save')) save(row);
                else if (button.classList.contains('js-delete')) remove(row);
                else if (button.classList.contains('js-view')) view(row);
            });

            tableEl.addEventListener('keydown', function (event) {
                if (event.key !== 'Enter' || !event.target.classList.contains('js-field')) return;
                event.preventDefault();
                const row = event.target.closest('tr');
                if (!row.querySelector('.js-save').disabled) save(row);
            });

            // Warn before losing edits that were never saved.
            window.addEventListener('beforeunload', function (event) {
                if (tableEl.querySelector('tr.is-dirty')) {
                    event.preventDefault();
                    event.returnValue = '';
                }
            });
        });
    </script>
@endpush
