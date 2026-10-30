<?php

declare(strict_types=1);

/**
 * Grid (card) layout for the Shows list, rendered by Osmium\Modules\Admin\Core\ListViewGrid. Columns use the
 * same vocabulary as lists/shows.php but nothing is shared with it. The actions are a copy of the list's for now.
 */
return [
    'columns' => 5,
    'imageHeight' => 150,
    'image' => ['key' => 'imageUrl', 'type' => 'image', 'altKey' => 'name'],
    'title' => [
        'key' => 'name',
        'type' => 'text',
        'strong' => true,
        'sub' => ['key' => 'linkUrl', 'style' => 'link', 'href' => '{linkUrl}'],
    ],
    'meta' => [
        ['key' => 'dateRange', 'type' => 'text', 'small' => true],
        ['key' => 'locationHtml', 'type' => 'raw'],
        ['key' => 'publishedBadge', 'type' => 'raw'],
    ],
    'actionsAt' => 'bottom-end',
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
];
