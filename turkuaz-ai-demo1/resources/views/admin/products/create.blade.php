@extends('layouts.admin')

@section('title', 'Add Product')

@section('content')
    <h1 class="h3 mb-3">Add Product</h1>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.products.store') }}" enctype="multipart/form-data">
                @csrf

                <h5 class="mb-3">Basic Information</h5>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Category</label>
                        <select name="category_id" id="category_id" class="form-select">
                            <option value="">-- None --</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>{{ $category->name['tr'] ?? '' }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Subcategory</label>
                        <select name="subcategory_id" id="subcategory_id" class="form-select">
                            <option value="">-- None --</option>
                            @foreach ($subcategories as $subcategory)
                                <option value="{{ $subcategory->id }}" data-category="{{ $subcategory->category_id }}"
                                        @selected(old('subcategory_id') == $subcategory->id)>{{ $subcategory->name['tr'] ?? '' }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Series</label>
                        <select name="series_id" class="form-select">
                            <option value="">-- None --</option>
                            @foreach ($seriesList as $s)
                                <option value="{{ $s->id }}" @selected(old('series_id') == $s->id)>{{ $s->name['tr'] ?? '' }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Name (Turkish)</label>
                        <input type="text" name="name[tr]" value="{{ old('name.tr') }}" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Name (English)</label>
                        <input type="text" name="name[en]" value="{{ old('name.en') }}" class="form-control" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Description (Turkish)</label>
                        <textarea name="description[tr]" class="form-control" rows="3">{{ old('description.tr') }}</textarea>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Description (English)</label>
                        <textarea name="description[en]" class="form-control" rows="3">{{ old('description.en') }}</textarea>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">SKU / Main Code</label>
                        <input type="text" name="sku" value="{{ old('sku') }}" class="form-control" placeholder="e.g. HC00106PB00">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Slug</label>
                        <input type="text" name="slug" value="{{ old('slug') }}" class="form-control" placeholder="e.g. ibiza-lavabo-91x51" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Dimensions (display text)</label>
                        <input type="text" name="dimensions" value="{{ old('dimensions') }}" class="form-control" placeholder="e.g. 91x51 cm">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select" style="max-width:200px;">
                        <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                        <option value="inactive" @selected(old('status') === 'inactive')>Inactive</option>
                    </select>
                </div>

                <hr class="my-4">
                <h5 class="mb-3">Colors</h5>
                <div class="mb-4">
                    @foreach ($colors as $color)
                        <div class="form-check form-check-inline">
                            <input type="checkbox" name="colors[]" value="{{ $color->id }}" class="form-check-input"
                                   id="color_{{ $color->id }}" @checked(collect(old('colors', []))->contains($color->id))>
                            <label class="form-check-label" for="color_{{ $color->id }}">
                                @if ($color->hex_value)
                                    <span style="display:inline-block;width:14px;height:14px;border-radius:3px;border:1px solid #ccc;background:{{ $color->hex_value }};"></span>
                                @endif
                                {{ $color->name['tr'] ?? '' }}
                            </label>
                        </div>
                    @endforeach
                </div>

                <hr class="my-4">
                <h5 class="mb-3">Measures</h5>
                <div class="row mb-4">
                    @foreach ($measures as $measure)
                        <div class="col-md-3 mb-2">
                            <label class="form-label">{{ $measure->name['tr'] ?? '' }} ({{ $measure->unit }})</label>
                            <input type="number" step="0.01" name="measures[{{ $measure->id }}]"
                                   value="{{ old('measures.' . $measure->id) }}" class="form-control">
                        </div>
                    @endforeach
                </div>

                <hr class="my-4">
                <h5 class="mb-3">Images</h5>
                <div class="mb-4">
                    <input type="file" name="images[]" class="form-control" multiple accept="image/png,image/jpeg,image/webp">
                    <div class="form-text">You can select multiple images. JPG, PNG, or WEBP, up to 4MB each.</div>
                </div>

                <hr class="my-4">
                <h5 class="mb-3">Variant Codes <small class="text-muted">(optional, dealer-facing)</small></h5>
                <div id="variant-rows" class="mb-3"></div>
                <button type="button" id="add-variant-row" class="btn btn-sm btn-outline-secondary mb-4">+ Add Variant</button>

                <div>
                    <button type="submit" class="btn btn-primary">Create Product</button>
                    <a href="{{ route('admin.products.index') }}" class="btn btn-link">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <template id="variant-row-template">
        <div class="row variant-row mb-2 align-items-center">
            <div class="col-md-3">
                <input type="text" name="variant_sku[]" class="form-control" placeholder="Variant SKU">
            </div>
            <div class="col-md-4">
                <input type="text" name="variant_note_tr[]" class="form-control" placeholder="Note (Turkish)">
            </div>
            <div class="col-md-4">
                <input type="text" name="variant_note_en[]" class="form-control" placeholder="Note (English)">
            </div>
            <div class="col-md-1">
                <button type="button" class="btn btn-outline-danger btn-sm remove-variant-row">&times;</button>
            </div>
        </div>
    </template>

    <script>
        // Filter the Subcategory dropdown by the selected Category.
        const categorySelect = document.getElementById('category_id');
        const subcategorySelect = document.getElementById('subcategory_id');
        const allSubcategoryOptions = Array.from(subcategorySelect.options);

        function filterSubcategories() {
            const selectedCategory = categorySelect.value;
            subcategorySelect.innerHTML = '';
            subcategorySelect.appendChild(allSubcategoryOptions[0].cloneNode(true)); // "-- None --"
            allSubcategoryOptions.forEach(function (opt) {
                if (opt.dataset.category === selectedCategory) {
                    subcategorySelect.appendChild(opt.cloneNode(true));
                }
            });
        }
        categorySelect.addEventListener('change', filterSubcategories);
        filterSubcategories();

        // Add/remove variant rows.
        const variantContainer = document.getElementById('variant-rows');
        const variantTemplate = document.getElementById('variant-row-template');

        document.getElementById('add-variant-row').addEventListener('click', function () {
            variantContainer.appendChild(variantTemplate.content.cloneNode(true));
        });

        variantContainer.addEventListener('click', function (e) {
            if (e.target.classList.contains('remove-variant-row')) {
                e.target.closest('.variant-row').remove();
            }
        });
    </script>
@endsection
