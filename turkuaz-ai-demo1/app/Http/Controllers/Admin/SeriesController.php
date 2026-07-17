<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Series\StoreSeriesRequest;
use App\Http\Requests\Series\UpdateSeriesRequest;
use App\Models\Series;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SeriesController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Series::class);

        $showTrashed = $request->boolean('trashed');

        $series = Series::query()
            ->when($showTrashed, fn ($q) => $q->onlyTrashed())
            ->searchName($request->string('q')->toString())
            ->orderByRaw("JSON_EXTRACT(name, '$.tr') asc")
            ->paginate(15)
            ->withQueryString();

        return view('admin.series.index', compact('series', 'showTrashed'));
    }

    public function create(): View
    {
        $this->authorize('create', Series::class);

        return view('admin.series.create');
    }

    public function store(StoreSeriesRequest $request): RedirectResponse
    {
        $this->authorize('create', Series::class);

        Series::create($request->validated());

        return redirect()->route('admin.series.index')->with('status', 'Series created successfully.');
    }

    public function edit(Series $series): View
    {
        $this->authorize('update', Series::class);

        return view('admin.series.edit', compact('series'));
    }

    public function update(UpdateSeriesRequest $request, Series $series): RedirectResponse
    {
        $this->authorize('update', Series::class);

        $series->update($request->validated());

        return redirect()->route('admin.series.index')->with('status', 'Series updated successfully.');
    }

    public function destroy(Series $series): RedirectResponse
    {
        $this->authorize('delete', Series::class);

        $series->delete();

        return redirect()->route('admin.series.index')->with('status', 'Series moved to trash.');
    }

    public function restore(int $seriesId): RedirectResponse
    {
        $this->authorize('update', Series::class);

        Series::onlyTrashed()->findOrFail($seriesId)->restore();

        return redirect()->route('admin.series.index')->with('status', 'Series restored.');
    }

    public function forceDelete(int $seriesId): RedirectResponse
    {
        $this->authorize('delete', Series::class);

        Series::onlyTrashed()->findOrFail($seriesId)->forceDelete();

        return redirect()->route('admin.series.index')->with('status', 'Series permanently deleted.');
    }
}
