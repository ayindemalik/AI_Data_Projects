<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    private array $permissions = [
        'use-assistant'         => 'Can chat with the AI assistant',
        'view-product-codes'   => 'Can see professional/dealer product codes',
        'manage-products'      => 'Can create, edit, and delete products',
        'manage-categories'    => 'Can create, edit, and delete categories',
        'manage-subcategories' => 'Can create, edit, and delete subcategories',
        'manage-series'        => 'Can create, edit, and delete series',
        'manage-collections'   => 'Can create, edit, and delete collections',
        'manage-colors'        => 'Can create, edit, and delete colors',
        'manage-measures'      => 'Can create, edit, and delete measures',
        'manage-documents'     => 'Can upload and manage documents',
        'manage-knowledge-base'=> 'Can manage knowledge base entries',
        'manage-showrooms'     => 'Can manage showroom listings',
        'manage-users'         => 'Can create, edit, and delete users',
        'manage-roles'         => 'Can create, edit, and delete roles',
        'manage-permissions'   => 'Can change which permissions a role has',
        'manage-settings'      => 'Can change application settings',
        'view-chat-history'    => 'Can view AI assistant chat history',
        'view-audit-logs'      => 'Can view the audit log',
    ];

    private array $rolePermissions = [
        'guest'         => ['use-assistant'],
        'customer'      => ['use-assistant'],
        'dealer'        => ['use-assistant', 'view-product-codes'],
        'sales'         => ['use-assistant', 'view-product-codes', 'view-chat-history'],
        'administrator' => '*',
    ];

    private array $roles = [
        'guest'         => 'Guest',
        'customer'      => 'Customer',
        'dealer'        => 'Dealer',
        'sales'         => 'Sales',
        'administrator' => 'Administrator',
    ];

    public function run(): void
    {
        foreach ($this->permissions as $slug => $name) {
            Permission::updateOrCreate(['slug' => $slug], ['name' => $name]);
        }

        foreach ($this->roles as $slug => $name) {
            $role = Role::updateOrCreate(['slug' => $slug], ['name' => $name]);

            $assignedSlugs = $this->rolePermissions[$slug] === '*'
                ? array_keys($this->permissions)
                : $this->rolePermissions[$slug];

            $permissionIds = Permission::whereIn('slug', $assignedSlugs)->pluck('id');

            $role->permissions()->sync($permissionIds);
        }
    }
}
