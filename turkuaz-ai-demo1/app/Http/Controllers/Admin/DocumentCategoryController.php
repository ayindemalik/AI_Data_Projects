<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\DocumentCategory\StoreDocumentCategoryRequest;
use App\Http\Requests\DocumentCategory\UpdateDocumentCategoryRequest;
use App\Models\DocumentCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DocumentCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', DocumentCategory::class);

        $showTrashed = $request->boolean('trashed');

        $documentCategories = DocumentCategory::query()
            ->withCount('documents')
            ->when($showTrashed, fn ($q) => $q->onlyTrashed())
            ->searchName($request->string('q')->toString())
            ->orderByRaw("JSON_EXTRACT(name, '$.tr') asc")
            ->paginate(15)
            ->withQueryString();

        return view('admin.document-categories.index', compact('documentCategories', 'showTrashed'));
    }

    public function create(): View
    {
        $this->authorize('create', DocumentCategory::class);

        return view('admin.document-categories.create');
    }

    public function store(StoreDocumentCategoryRequest $request): RedirectResponse
    {
        $this->authorize('create', DocumentCategory::class);

        DocumentCategory::create($request->validated());

        return redirect()->route('admin.document-categories.index')->with('status', 'Document category created successfully.');
    }

    public function edit(DocumentCategory $documentCategory): View
    {
        $this->authorize('update', DocumentCategory::class);

        return view('admin.document-categories.edit', compact('documentCategory'));
    }

    public function update(UpdateDocumentCategoryRequest $request, DocumentCategory $documentCategory): RedirectResponse
    {
        $this->authorize('update', DocumentCategory::class);

        $documentCategory->update($request->validated());

        return redirect()->route('admin.document-categories.index')->with('status', 'Document category updated successfully.');
    }

    public function destroy(DocumentCategory $documentCategory): RedirectResponse
    {
        $this->authorize('delete', DocumentCategory::class);

        $documentCategory->delete();

        return redirect()->route('admin.document-categories.index')->with('status', 'Document category moved to trash.');
    }

    public function restore(int $id): RedirectResponse
    {
        $this->authorize('update', DocumentCategory::class);

        DocumentCategory::onlyTrashed()->findOrFail($id)->restore();

        return redirect()->route('admin.document-categories.index')->with('status', 'Document category restored.');
    }

    public function forceDelete(int $id): RedirectResponse
    {
        $this->authorize('delete', DocumentCategory::class);

        DocumentCategory::onlyTrashed()->findOrFail($id)->forceDelete();

        return redirect()->route('admin.document-categories.index')->with('status', 'Document category permanently deleted.');
    }
}
