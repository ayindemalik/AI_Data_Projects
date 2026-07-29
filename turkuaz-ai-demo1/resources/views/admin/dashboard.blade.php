@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
    {{-- ===== Headline stat cards ===== --}}
    <div class="row g-3 mb-4">
        @php
            $cards = [
                ['label' => 'Products', 'value' => $stats['products'], 'icon' => 'bi-box-seam', 'route' => 'admin.products.index', 'color' => 'primary'],
                ['label' => 'Product Types', 'value' => $stats['productTypes'], 'icon' => 'bi-tags', 'route' => 'admin.product-types.index', 'color' => 'info'],
                ['label' => 'Categories', 'value' => $stats['categories'], 'icon' => 'bi-diagram-3', 'route' => 'admin.categories.index', 'color' => 'success'],
                ['label' => 'Subcategories', 'value' => $stats['subcategories'], 'icon' => 'bi-diagram-2', 'route' => 'admin.subcategories.index', 'color' => 'success'],
                ['label' => 'Series', 'value' => $stats['series'], 'icon' => 'bi-grid-3x3-gap', 'route' => 'admin.series.index', 'color' => 'secondary'],
                ['label' => 'Colors', 'value' => $stats['colors'], 'icon' => 'bi-palette', 'route' => 'admin.colors.index', 'color' => 'secondary'],
                ['label' => 'Documents', 'value' => $stats['documents'], 'icon' => 'bi-file-earmark-text', 'route' => 'admin.documents.index', 'color' => 'warning'],
                ['label' => 'Users', 'value' => $stats['users'], 'icon' => 'bi-people', 'route' => 'admin.users.index', 'color' => 'dark'],
            ];
        @endphp

        @foreach ($cards as $card)
            <div class="col-6 col-md-3">
                <a href="{{ route($card['route']) }}" class="text-decoration-none">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body d-flex align-items-center">
                            <div class="rounded-circle bg-{{ $card['color'] }} bg-opacity-10 p-3 me-3">
                                <i class="bi {{ $card['icon'] }} fs-4 text-{{ $card['color'] }}"></i>
                            </div>
                            <div>
                                <div class="text-muted small text-uppercase">{{ $card['label'] }}</div>
                                <div class="fs-3 fw-bold text-body">{{ number_format($card['value']) }}</div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    {{-- ===== Data quality + charts row ===== --}}
    <div class="row g-3 mb-4">
        {{-- Products needing attention --}}
        <div class="col-md-4">
            <div class="card h-100 shadow-sm">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-exclamation-triangle text-warning me-1"></i> Products Needing Attention
                </div>
                <div class="list-group list-group-flush">
                    @php
                        $qualityRows = [
                            ['label' => 'No real photo', 'value' => $quality['no_image'], 'flag' => 'missing=image'],
                            ['label' => 'Missing weight (KG)', 'value' => $quality['missing_kg'], 'flag' => 'missing=kg'],
                            ['label' => 'No series', 'value' => $quality['no_series'], 'flag' => 'missing=series'],
                            ['label' => 'No product type', 'value' => $quality['no_type'], 'flag' => 'missing=type'],
                            ['label' => 'No description', 'value' => $quality['no_description'], 'flag' => 'missing=description'],
                        ];
                    @endphp
                    @foreach ($qualityRows as $row)
                        <a href="{{ route('admin.products.index') }}?{{ $row['flag'] }}"
                           class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            {{ $row['label'] }}
                            <span class="badge {{ $row['value'] > 0 ? 'bg-danger' : 'bg-success' }} rounded-pill">
                                {{ number_format($row['value']) }}
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Products per category chart --}}
        <div class="col-md-4">
            <div class="card h-100 shadow-sm">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-bar-chart me-1"></i> Products per Category
                </div>
                <div class="card-body">
                    <canvas id="categoryChart" height="220"></canvas>
                </div>
            </div>
        </div>

        {{-- Products by status chart --}}
        <div class="col-md-4">
            <div class="card h-100 shadow-sm">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-pie-chart me-1"></i> Products by Status
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <canvas id="statusChart" height="220"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== Recent products + system row ===== --}}
    <div class="row g-3">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-clock-history me-1"></i> Recently Added Products
                </div>
                <table class="table table-hover mb-0">
                    <tbody>
                        @forelse ($recentProducts as $product)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.products.edit', $product) }}" class="text-decoration-none">
                                        {{ $product->name['tr'] ?? '-' }}
                                    </a>
                                </td>
                                <td class="text-muted small">{{ $product->category?->name['tr'] ?? '—' }}</td>
                                <td class="text-muted small">{{ $product->productType?->name['tr'] ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td class="text-muted">No products yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">Users by Role</div>
                <table class="table mb-0">
                    <tbody>
                        @foreach ($usersByRole as $role)
                            <tr>
                                <td>{{ $role->name }}</td>
                                <td class="text-end">{{ $role->users_count }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">Recently Added Users</div>
                <table class="table mb-0">
                    <tbody>
                        @forelse ($recentUsers as $user)
                            <tr>
                                <td>{{ $user->name }}</td>
                                <td class="text-muted small">{{ $user->role?->name }}</td>
                            </tr>
                        @empty
                            <tr><td class="text-muted">No users yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Products per category (bar)
            const catData = @json($byCategory);
            new Chart(document.getElementById('categoryChart'), {
                type: 'bar',
                data: {
                    labels: catData.map(r => r.label),
                    datasets: [{
                        label: 'Products',
                        data: catData.map(r => r.total),
                        backgroundColor: '#0d6efd',
                        borderRadius: 4,
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                }
            });

            // Products by status (doughnut)
            const statusData = @json($byStatus);
            new Chart(document.getElementById('statusChart'), {
                type: 'doughnut',
                data: {
                    labels: Object.keys(statusData),
                    datasets: [{
                        data: Object.values(statusData),
                        backgroundColor: ['#198754', '#adb5bd', '#dc3545'],
                    }]
                },
                options: { responsive: true }
            });
        });
    </script>
@endpush
