<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Color\StoreColorRequest;
use App\Http\Requests\Color\UpdateColorRequest;
use App\Models\Color;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ColorController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Color::class);

        $showTrashed = $request->boolean('trashed');

        $colors = Color::query()
            ->when($showTrashed, fn ($q) => $q->onlyTrashed())
            ->searchName($request->string('q')->toString())
            ->orderByRaw("JSON_EXTRACT(name, '$.tr') asc")
            ->paginate(15)
            ->withQueryString();

        return view('admin.colors.index', compact('colors', 'showTrashed'));
    }

    public function create(): View
    {
        $this->authorize('create', Color::class);

        return view('admin.colors.create');
    }

    public function store(StoreColorRequest $request): RedirectResponse
    {
        $this->authorize('create', Color::class);

        Color::create($request->validated());

        return redirect()->route('admin.colors.index')->with('status', 'Color created successfully.');
    }

    public function edit(Color $color): View
    {
        $this->authorize('update', Color::class);

        return view('admin.colors.edit', compact('color'));
    }

    public function update(UpdateColorRequest $request, Color $color): RedirectResponse
    {
        $this->authorize('update', Color::class);

        $color->update($request->validated());

        return redirect()->route('admin.colors.index')->with('status', 'Color updated successfully.');
    }

    public function destroy(Color $color): RedirectResponse
    {
        $this->authorize('delete', Color::class);

        $color->delete();

        return redirect()->route('admin.colors.index')->with('status', 'Color moved to trash.');
    }

    public function restore(int $colorId): RedirectResponse
    {
        $this->authorize('update', Color::class);

        Color::onlyTrashed()->findOrFail($colorId)->restore();

        return redirect()->route('admin.colors.index')->with('status', 'Color restored.');
    }

    public function forceDelete(int $colorId): RedirectResponse
    {
        $this->authorize('delete', Color::class);

        Color::onlyTrashed()->findOrFail($colorId)->forceDelete();

        return redirect()->route('admin.colors.index')->with('status', 'Color permanently deleted.');
    }
}
