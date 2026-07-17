<?php

return [
    [
        'label' => 'Dashboard',
        'route' => 'admin.dashboard',
        'icon' => 'bi-speedometer2',
        'permission' => null,
    ],
    [
        'label' => 'Products',
        'route' => 'admin.products.index',
        'icon' => 'bi-box-seam',
        'permission' => 'manage-products',
    ],
    [
        'label' => 'Categories',
        'route' => 'admin.categories.index',
        'icon' => 'bi-diagram-3',
        'permission' => 'manage-categories',
    ],
    [
        'label' => 'Subcategories',
        'route' => 'admin.subcategories.index',
        'icon' => 'bi-diagram-2',
        'permission' => 'manage-subcategories',
    ],
    [
        'label' => 'Collections',
        'route' => 'admin.collections.index',
        'icon' => 'bi-collection',
        'permission' => 'manage-collections',
    ],
    [
        'label' => 'Series',
        'route' => 'admin.series.index',
        'icon' => 'bi-grid-3x3-gap',
        'permission' => 'manage-series',
    ],
    [
        'label' => 'Colors',
        'route' => 'admin.colors.index',
        'icon' => 'bi-palette',
        'permission' => 'manage-colors',
    ],
    [
        'label' => 'Measures',
        'route' => 'admin.measures.index',
        'icon' => 'bi-rulers',
        'permission' => 'manage-measures',
    ],
    [
        'label' => 'Documents',
        'route' => 'admin.documents.index',
        'icon' => 'bi-file-earmark-text',
        'permission' => 'manage-documents',
    ],
    [
        'label' => 'Document Categories',
        'route' => 'admin.document-categories.index',
        'icon' => 'bi-folder2-open',
        'permission' => 'manage-documents',
    ],
    [
        'label' => 'Chat History',
        'route' => 'admin.chat-history.index',
        'icon' => 'bi-chat-dots',
        'permission' => 'view-chat-history',
    ],
    [
        'label' => 'Users',
        'route' => 'admin.users.index',
        'icon' => 'bi-people',
        'permission' => 'manage-users',
    ],
    [
        'label' => 'Settings',
        'route' => 'admin.settings.edit',
        'icon' => 'bi-gear',
        'permission' => 'manage-settings',
    ],
];
