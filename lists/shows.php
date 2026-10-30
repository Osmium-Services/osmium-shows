<?php

declare(strict_types=1);

use Osmium\Modules\Admin\Core\ListView;

/**
 * Shows lists, rendered by Osmium\Modules\Admin\Core\ListView. Row fields (locationHtml, publishedBadge...) are
 * prepared by ShowsController::formatShow(), formatRecentShow() and formatDeletedShow().
 */
require_once 'app/core/admin/services/ChangedByCell.php';

$changedByColumn = ['key' => 'changed_by', 'label' => '', 'type' => 'html', 'sortable' => false, 'render' => fn(array $row, array $context): string => \Osmium\Modules\Admin\Services\ChangedByCell::html($row, (string)($context['avatarUrl'] ?? ''))];

$showColumns = static fn(string $statusKey, string $statusLabel): array => [
    ['key' => 'imageUrl', 'label' => 'Logo', 'type' => 'image', 'altKey' => 'name', 'sortable' => false],
    ['key' => 'name', 'label' => 'Show', 'type' => 'text', 'strong' => true, 'fill' => true, 'sub' => ['key' => 'linkUrl', 'style' => 'link', 'href' => '{linkUrl}']],
    ['key' => 'dateRange', 'label' => 'Dates', 'type' => 'text', 'nowrap' => true, 'sortKey' => 'startDate'],
    ['key' => 'locationHtml', 'label' => 'Location', 'type' => 'raw'],
    ['key' => $statusKey, 'label' => $statusLabel, 'type' => 'raw'],
];

return [
    'active' => [
        'id' => 'shows',
        'title' => 'Shows',
        'noun' => ['show', 'shows'],
        'endpoint' => 'shows/action/',
        'emptyText' => 'No shows yet - use Add Show to create one.',
        'order' => null, // Server order: newest start date first
        'grid' => require __DIR__ . '/../grids/shows.php',
        'columns' => $showColumns('publishedBadge', 'Published'),
        'actions' => [
            ['label' => 'Edit', 'icon' => 'bx-edit-alt', 'event' => 'edit'],
            [
                'label' => 'Publish',
                'icon' => 'bx-show',
                'action' => 'publish',
                'visible' => fn(array $row, array $context): bool => !$row['isPublished'],
            ],
            [
                'label' => 'Hide',
                'icon' => 'bx-hide',
                'action' => 'unpublish',
                'visible' => fn(array $row, array $context): bool => (bool) $row['isPublished'],
            ],
            [
                'label' => 'Delete',
                'icon' => 'bx-trash',
                'action' => 'delete',
                'confirm' => 'Delete this show? It will be removed from the public shows page, and can be restored from Deleted Shows.',
                'danger' => true,
            ],
        ],
    ],

    'archived' => [
        'id' => 'shows-archived',
        'title' => 'Archived Shows',
        'noun' => ['show', 'shows'],
        'icon' => 'bx-archive',
        'endpoint' => 'shows/action/',
        'order' => null,
        'collapsible' => ['label' => 'Archived Shows ({count})'],
        'hideWhenEmpty' => true,
        'columns' => $showColumns('archivedBadge', 'Status'),
        'actions' => [
            ['label' => 'Edit', 'icon' => 'bx-edit-alt', 'event' => 'edit'],
            [
                'label' => 'Delete',
                'icon' => 'bx-trash',
                'action' => 'delete',
                'confirm' => 'Delete this show? It can be restored from Deleted Shows.',
                'danger' => true,
            ],
        ],
    ],

    'recent' => [
        'id' => 'shows-recent',
        'title' => 'Recent Changes',
        'noun' => ['show', 'shows'],
        'icon' => 'bx-time-five',
        'rowClass' => static fn(array $row): string => empty($row['deleted_at']) ? '' : 'table-danger',
        'endpoint' => 'shows/action/',
        'collapsible' => ['label' => 'Recent Changes ({count})'],
        'hideWhenEmpty' => false,
        'columns' => [
            $changedByColumn,
            ['key' => 'name', 'label' => 'Show', 'type' => 'text', 'strong' => true, 'fill' => true],
            ['key' => 'updatedDate', 'label' => 'Updated', 'type' => 'text', 'small' => true, 'nowrap' => true],
        ],
    ],

    'deleted' => ListView::deletedPreset([
        'id' => 'shows-deleted',
        'title' => 'Deleted Shows',
        'noun' => ['item', 'items'],
        'endpoint' => 'shows/action/',
        'hideWhenEmpty' => false,
        'headerButton' => [
            'label' => 'Purge All',
            'icon' => 'bx-trash',
            'class' => 'btn-outline-danger',
            'action' => 'purge_deleted',
            'confirm' => 'Permanently delete all deleted shows? This cannot be undone!',
            'visible' => fn(array $rows, array $context): bool => (bool) $context['isAdmin'],
        ],
        'columns' => [
            $changedByColumn,
            ['key' => 'name', 'label' => 'Show', 'type' => 'text', 'strong' => true, 'fill' => true],
            ['key' => 'dateRange', 'label' => 'Dates', 'type' => 'text', 'nowrap' => true],
            ['key' => 'deletedDate', 'label' => 'Deleted', 'type' => 'text', 'small' => true, 'nowrap' => true],
        ],
    ]),
];
