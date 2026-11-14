<?php

declare(strict_types=1);

namespace Tests\Services\Shows;

use Osmium\Services\Shows\Models\Show;
use Osmium\Services\Shows\Models\ShowsAdminBar;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/Show.php';
require_once __DIR__ . '/../models/ShowsAdminBar.php';

/**
 * The staff bar's shows answers: a page that listed shows announces it and gets a "Shows" link.
 */
final class ShowsAdminBarTest extends TestCase
{
    protected function setUp(): void
    {
        Show::forgetShown();
    }

    public function test_a_page_that_listed_no_shows_announces_nothing(): void
    {
        $this->assertNull(ShowsAdminBar::context(['data' => []]));
    }

    public function test_a_page_that_listed_shows_announces_it(): void
    {
        $property = new \ReflectionProperty(Show::class, 'publicListShown');
        $property->setValue(null, true);

        $this->assertSame('shows:all', ShowsAdminBar::context(['data' => []]));
    }

    public function test_the_link_opens_the_shows_list_with_the_services_own_icon(): void
    {
        $links = ShowsAdminBar::links(['context' => 'shows:all']);

        $this->assertSame('shows/', $links[0]['path']);
        $this->assertSame('Edit shows', $links[0]['label']);
        $this->assertSame('bx-calendar-event', $links[0]['icon']);
        $this->assertTrue($links[0]['primary']);
        $this->assertArrayNotHasKey('order', $links[0]); // The page's main content: the default order
    }

    public function test_other_contexts_get_no_links(): void
    {
        $this->assertNull(ShowsAdminBar::links(['context' => 'product:5']));
        $this->assertNull(ShowsAdminBar::links([]));
    }
}
