<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Document\StoreDocumentRequest;
use App\Http\Requests\Document\UpdateDocumentRequest;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DocumentController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Document::class);

        $showTrashed = $request->boolean('trashed');
        $term = $request->string('q')->toString();

        $documents = Document::query()
            ->with(['category', 'product'])
            ->when($showTrashed, fn ($q) => $q->onlyTrashed())
            ->when($request->filled('document_category_id'), fn ($q) => $q->where('document_category_id', $request->integer('document_category_id')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($term, function ($q) use ($term) {
                $q->where(function ($inner) use ($term) {
                    $inner->whereRaw("JSON_EXTRACT(title, '$.tr') LIKE ?", ["%{$term}%"])
                          ->orWhereRaw("JSON_EXTRACT(title, '$.en') LIKE ?", ["%{$term}%"]);
                });
            })
            ->orderByRaw("JSON_EXTRACT(title, '$.tr') asc")
            ->paginate(15)
            ->withQueryString();

        $documentCategories = DocumentCategory::active()->orderByRaw("JSON_EXTRACT(name, '$.tr') asc")->get();

        return view('admin.documents.index', compact('documents', 'showTrashed', 'documentCategories'));
    }

    public function create(): View
    {
        $this->authorize('create', Document::class);

        return view('admin.documents.create', $this->formOptions());
    }

    public function store(StoreDocumentRequest $request): RedirectResponse
    {
        $this->authorize('create', Document::class);

        $data = $request->validated();

        Document::create([
            'document_category_id' => $data['document_category_id'] ?? null,
            'product_id' => $data['product_id'] ?? null,
            'type' => $data['type'],
            'title' => $data['title'],
            'file' => [
                'tr' => $this->resolveFile($request, 'tr', $data),
                'en' => $this->resolveFile($request, 'en', $data),
            ],
            'status' => $data['status'],
        ]);

        return redirect()->route('admin.documents.index')->with('status', 'Document created successfully.');
    }

    public function edit(Document $document): View
    {
        $this->authorize('update', Document::class);

        return view('admin.documents.edit', array_merge($this->formOptions(), compact('document')));
    }

    public function update(UpdateDocumentRequest $request, Document $document): RedirectResponse
    {
        $this->authorize('update', Document::class);

        $data = $request->validated();

        $document->update([
            'document_category_id' => $data['document_category_id'] ?? null,
            'product_id' => $data['product_id'] ?? null,
            'type' => $data['type'],
            'title' => $data['title'],
            'file' => [
                // Blank inputs keep the existing file for that language.
                'tr' => $this->resolveFile($request, 'tr', $data) ?? ($document->file['tr'] ?? null),
                'en' => $this->resolveFile($request, 'en', $data) ?? ($document->file['en'] ?? null),
            ],
            'status' => $data['status'],
        ]);

        return redirect()->route('admin.documents.index')->with('status', 'Document updated successfully.');
    }

    public function destroy(Document $document): RedirectResponse
    {
        $this->authorize('delete', Document::class);

        $document->delete();

        return redirect()->route('admin.documents.index')->with('status', 'Document moved to trash.');
    }

    public function restore(int $documentId): RedirectResponse
    {
        $this->authorize('update', Document::class);

        Document::onlyTrashed()->findOrFail($documentId)->restore();

        return redirect()->route('admin.documents.index')->with('status', 'Document restored.');
    }

    public function forceDelete(int $documentId): RedirectResponse
    {
        $this->authorize('delete', Document::class);

        $document = Document::onlyTrashed()->findOrFail($documentId);

        // Remove locally-uploaded files; leave external CDN URLs untouched.
        foreach (['tr', 'en'] as $locale) {
            $value = $document->file[$locale] ?? null;
            if ($value && !Str::startsWith($value, ['http://', 'https://'])) {
                Storage::disk('public')->delete($value);
            }
        }

        $document->forceDelete();

        return redirect()->route('admin.documents.index')->with('status', 'Document permanently deleted.');
    }

    private function formOptions(): array
    {
        return [
            'documentCategories' => DocumentCategory::active()->orderByRaw("JSON_EXTRACT(name, '$.tr') asc")->get(),
            // Products list for the optional product link; id+name is enough for a dropdown.
            'products' => Product::active()->orderByRaw("JSON_EXTRACT(name, '$.tr') asc")->get(['id', 'name', 'sku']),
            'types' => Document::TYPES,
        ];
    }

    /**
     * A language's file comes from either an uploaded file (stored on the
     * public disk) or a typed URL. Upload wins if both are provided.
     */
    private function resolveFile(Request $request, string $locale, array $data): ?string
    {
        if ($request->hasFile("file_upload_{$locale}")) {
            return $request->file("file_upload_{$locale}")->store('documents', 'public');
        }

        return $data["file_url_{$locale}"] ?? null;
    }
}
