<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        // Simple counts for the dashboard cards. More cards (products, chat
        // sessions, etc.) get added here as later modules are built.
        $totalUsers = User::count();
        $totalRoles = Role::count();

        // How many users are in each role, for a quick breakdown table.
        $usersByRole = Role::withCount('users')->orderBy('name')->get();

        $recentUsers = User::with('role')->latest()->take(5)->get();

        return view('admin.dashboard', compact('totalUsers', 'totalRoles', 'usersByRole', 'recentUsers'));
    }
}
