@extends('layouts.admin')

@section('title', 'Edit Document')

@section('content')
    <h1 class="h3 mb-3">Edit Document</h1>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.documents.update', $document) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Title (Turkish)</label>
                        <input type="text" name="title[tr]" value="{{ old('title.tr', $document->title['tr'] ?? '') }}" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Title (English)</label>
                        <input type="text" name="title[en]" value="{{ old('title.en', $document->title['en'] ?? '') }}" class="form-control">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Type</label>
                        <select name="type" class="form-select" required>
                            @foreach ($types as $type)
                                <option value="{{ $type }}" @selected(old('type', $document->type) === $type)>{{ ucfirst(str_replace('_', ' ', $type)) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Document Category (optional)</label>
                        <select name="document_category_id" class="form-select">
                            <option value="">-- None --</option>
                            @foreach ($documentCategories as $cat)
                                <option value="{{ $cat->id }}" @selected(old('document_category_id', $document->document_category_id) == $cat->id)>{{ $cat->name['tr'] ?? '' }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Product (optional)</label>
                        <select name="product_id" class="form-select">
                            <option value="">-- None --</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}" @selected(old('product_id', $document->product_id) == $product->id)>
                                    {{ $product->name['tr'] ?? '' }} {{ $product->sku ? "({$product->sku})" : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <hr class="my-4">
                <h5 class="mb-3">Files</h5>
                <p class="text-muted small">Leave both inputs blank to keep the current file. Paste a new URL <em>or</em> upload to replace; upload wins if both are given.</p>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Turkish File</label>
                        @if (!empty($document->file['tr']))
                            <div class="mb-1"><a href="{{ $document->fileUrl('tr') }}" target="_blank" class="small">Current TR file ↗</a></div>
                        @else
                            <div class="mb-1 text-muted small">No TR file yet.</div>
                        @endif
                        <input type="url" name="file_url_tr" value="{{ old('file_url_tr') }}" class="form-control" placeholder="New URL (optional)">
                        <label class="form-label mt-2">or Upload</label>
                        <input type="file" name="file_upload_tr" class="form-control" accept=".pdf,.png,.jpg,.jpeg">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">English File</label>
                        @if (!empty($document->file['en']))
                            <div class="mb-1"><a href="{{ $document->fileUrl('en') }}" target="_blank" class="small">Current EN file ↗</a></div>
                        @else
                            <div class="mb-1 text-muted small">No EN file yet.</div>
                        @endif
                        <input type="url" name="file_url_en" value="{{ old('file_url_en') }}" class="form-control" placeholder="New URL (optional)">
                        <label class="form-label mt-2">or Upload</label>
                        <input type="file" name="file_upload_en" class="form-control" accept=".pdf,.png,.jpg,.jpeg">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select" style="max-width:200px;">
                        <option value="active" @selected(old('status', $document->status) === 'active')>Active</option>
                        <option value="inactive" @selected(old('status', $document->status) === 'inactive')>Inactive</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="{{ route('admin.documents.index') }}" class="btn btn-link">Cancel</a>
            </form>
        </div>
    </div>
@endsection
