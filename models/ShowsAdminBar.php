<?php

declare(strict_types=1);

namespace Osmium\Services\Shows\Models;

/**
 * What the shows service tells the front-end staff bar: that the page listed shows (adminbar.context) and the admin
 * link that goes with it (adminbar.links). Themes fetch the public lists through Show::listUpcoming() and
 * Show::listPast(), which note it; core asks after the page is built, so the answer is current. The list is the
 * page's main content, so the link takes the default order, like the blog index's. Shows have no pages of their own,
 * so the link opens the shows list.
 */
class ShowsAdminBar
{
    /**
     * @param array<string, mixed> $payload the finished page's view data (unused: the model notes itself)
     * @return string|null "shows:all" when the page listed shows, null otherwise
     */
    public static function context(array $payload): ?string
    {
        return Show::publicListWasShown() ? 'shows:all' : null;
    }

    /**
     * @param array{context?: string, role?: string} $payload
     * @return array<int, array<string, mixed>>|null
     */
    public static function links(array $payload): ?array
    {
        $isContext = ($payload['context'] ?? '') === 'shows:all';
        if (!$isContext) return null;

        return [['label' => 'Edit shows', 'icon' => 'bx-calendar-event', 'path' => 'shows/', 'primary' => true]];
    }
}
