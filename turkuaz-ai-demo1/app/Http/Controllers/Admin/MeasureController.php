<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Measure\StoreMeasureRequest;
use App\Http\Requests\Measure\UpdateMeasureRequest;
use App\Models\Measure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MeasureController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Measure::class);

        $showTrashed = $request->boolean('trashed');

        $measures = Measure::query()
            ->when($showTrashed, fn ($q) => $q->onlyTrashed())
            ->searchName($request->string('q')->toString())
            ->orderByRaw("JSON_EXTRACT(name, '$.tr') asc")
            ->paginate(15)
            ->withQueryString();

        return view('admin.measures.index', compact('measures', 'showTrashed'));
    }

    public function create(): View
    {
        $this->authorize('create', Measure::class);

        return view('admin.measures.create');
    }

    public function store(StoreMeasureRequest $request): RedirectResponse
    {
        $this->authorize('create', Measure::class);

        Measure::create($request->validated());

        return redirect()->route('admin.measures.index')->with('status', 'Measure created successfully.');
    }

    public function edit(Measure $measure): View
    {
        $this->authorize('update', Measure::class);

        return view('admin.measures.edit', compact('measure'));
    }

    public function update(UpdateMeasureRequest $request, Measure $measure): RedirectResponse
    {
        $this->authorize('update', Measure::class);

        $measure->update($request->validated());

        return redirect()->route('admin.measures.index')->with('status', 'Measure updated successfully.');
    }

    public function destroy(Measure $measure): RedirectResponse
    {
        $this->authorize('delete', Measure::class);

        $measure->delete();

        return redirect()->route('admin.measures.index')->with('status', 'Measure moved to trash.');
    }

    public function restore(int $measureId): RedirectResponse
    {
        $this->authorize('update', Measure::class);

        Measure::onlyTrashed()->findOrFail($measureId)->restore();

        return redirect()->route('admin.measures.index')->with('status', 'Measure restored.');
    }

    public function forceDelete(int $measureId): RedirectResponse
    {
        $this->authorize('delete', Measure::class);

        Measure::onlyTrashed()->findOrFail($measureId)->forceDelete();

        return redirect()->route('admin.measures.index')->with('status', 'Measure permanently deleted.');
    }
}
