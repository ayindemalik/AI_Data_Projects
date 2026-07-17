<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Collection\StoreCollectionRequest;
use App\Http\Requests\Collection\UpdateCollectionRequest;
use App\Models\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CollectionController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Collection::class);

        $showTrashed = $request->boolean('trashed');

        $collections = Collection::query()
            ->when($showTrashed, fn ($q) => $q->onlyTrashed())
            ->searchName($request->string('q')->toString())
            ->orderByRaw("JSON_EXTRACT(name, '$.tr') asc")
            ->paginate(15)
            ->withQueryString();

        return view('admin.collections.index', compact('collections', 'showTrashed'));
    }

    public function create(): View
    {
        $this->authorize('create', Collection::class);

        return view('admin.collections.create');
    }

    public function store(StoreCollectionRequest $request): RedirectResponse
    {
        $this->authorize('create', Collection::class);

        Collection::create($request->validated());

        return redirect()->route('admin.collections.index')->with('status', 'Collection created successfully.');
    }

    public function edit(Collection $collection): View
    {
        $this->authorize('update', Collection::class);

        return view('admin.collections.edit', compact('collection'));
    }

    public function update(UpdateCollectionRequest $request, Collection $collection): RedirectResponse
    {
        $this->authorize('update', Collection::class);

        $collection->update($request->validated());

        return redirect()->route('admin.collections.index')->with('status', 'Collection updated successfully.');
    }

    public function destroy(Collection $collection): RedirectResponse
    {
        $this->authorize('delete', Collection::class);

        $collection->delete();

        return redirect()->route('admin.collections.index')->with('status', 'Collection moved to trash.');
    }

    public function restore(int $collectionId): RedirectResponse
    {
        $this->authorize('update', Collection::class);

        Collection::onlyTrashed()->findOrFail($collectionId)->restore();

        return redirect()->route('admin.collections.index')->with('status', 'Collection restored.');
    }

    public function forceDelete(int $collectionId): RedirectResponse
    {
        $this->authorize('delete', Collection::class);

        Collection::onlyTrashed()->findOrFail($collectionId)->forceDelete();

        return redirect()->route('admin.collections.index')->with('status', 'Collection permanently deleted.');
    }
}
