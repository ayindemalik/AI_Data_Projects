@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Total Users</div>
                    <div class="fs-2 fw-bold">{{ $totalUsers }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Total Roles</div>
                    <div class="fs-2 fw-bold">{{ $totalRoles }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Users by Role</div>
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

        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Recently Added Users</div>
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
