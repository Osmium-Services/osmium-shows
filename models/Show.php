<?php

declare(strict_types=1);

namespace Osmium\Services\Shows\Models;

use Osmium\Core\Library\OsmiumPDO;
use Osmium\Core\Models\Model;

/**
 * Shows are curated by hand in admin, not synced from any external source.
 */
class Show extends Model
{
    protected ?string $sqlDir = __DIR__ . '/sql';

    public function __construct(OsmiumPDO $database)
    {
        parent::__construct(database: $database, tableName: 'shows');
    }

    /**
     * List published shows ending today or later, soonest first
     */
    public function listUpcoming(): array
    {
        $sql = $this->loadSqlFile('show-list-upcoming.sql');
        $this->database->query($sql);
        $this->database->bind(param: ':today', value: \date('Y-m-d'));

        return $this->database->resultset();
    }

    /**
     * List published shows that ended before today, back to the given cutoff
     */
    public function listPast(string $since): array
    {
        $sql = $this->loadSqlFile('show-list-past.sql');
        $this->database->query($sql);
        $this->database->bind(param: ':today', value: \date('Y-m-d'));
        $this->database->bind(param: ':since', value: $since);

        return $this->database->resultset();
    }

    /**
     * List every show for admin, newest first (excludes soft-deleted)
     */
    public function listAll(): array
    {
        $sql = $this->loadSqlFile('show-list-all.sql');
        $this->database->query($sql);

        return $this->database->resultset();
    }

    public function getById(int $id): ?array
    {
        $sql = $this->loadSqlFile('show-get-by-id.sql');
        $this->database->query($sql);
        $this->database->bind(param: ':id', value: $id);
        $result = $this->database->single();

        return $result ?: null;
    }

    public function create(
        string $name,
        string $startDate,
        string $endDate,
        ?string $location = null,
        ?string $postcode = null,
        ?float $latitude = null,
        ?float $longitude = null,
        ?string $linkUrl = null,
        bool $isPublished = true,
    ): int {
        $sql = $this->loadSqlFile('show-create.sql');
        $this->database->query($sql);
        $this->bindDetails(
            name: $name,
            startDate: $startDate,
            endDate: $endDate,
            location: $location,
            postcode: $postcode,
            latitude: $latitude,
            longitude: $longitude,
            linkUrl: $linkUrl,
            isPublished: $isPublished,
        );
        $this->database->execute();

        return (int) $this->database->lastInsertId();
    }

    public function update(
        int $id,
        string $name,
        string $startDate,
        string $endDate,
        ?string $location = null,
        ?string $postcode = null,
        ?float $latitude = null,
        ?float $longitude = null,
        ?string $linkUrl = null,
        bool $isPublished = true,
    ): void {
        $sql = $this->loadSqlFile('show-update.sql');
        $this->database->query($sql);
        $this->database->bind(param: ':id', value: $id);
        $this->bindDetails(
            name: $name,
            startDate: $startDate,
            endDate: $endDate,
            location: $location,
            postcode: $postcode,
            latitude: $latitude,
            longitude: $longitude,
            linkUrl: $linkUrl,
            isPublished: $isPublished,
        );
        $this->database->execute();
    }

    /**
     * Flip public visibility on its own, so the list view's pill toggle never
     * re-geocodes or rewrites any of the show's other fields.
     */
    public function updatePublished(int $id, bool $isPublished): void
    {
        $sql = $this->loadSqlFile('show-update-published.sql');
        $this->database->query($sql);
        $this->database->bind(param: ':id', value: $id);
        $this->database->bind(param: ':is_published', value: $isPublished ? 1 : 0);
        $this->database->execute();
    }

    /**
     * Update the logo filename. Separate from update() so saving the details
     * form never clears an image, and an upload never rewrites the details.
     */
    public function updateImage(int $id, string $imageFilename): void
    {
        $sql = $this->loadSqlFile('show-update-image.sql');
        $this->database->query($sql);
        $this->database->bind(param: ':id', value: $id);
        $this->database->bind(param: ':image_filename', value: $imageFilename);
        $this->database->execute();
    }

    public function softDelete(int $id): void
    {
        $sql = $this->loadSqlFile('show-soft-delete.sql');
        $this->database->query($sql);
        $this->database->bind(param: ':id', value: $id);
        $this->database->execute();
    }

    private function bindDetails(
        string $name,
        string $startDate,
        string $endDate,
        ?string $location,
        ?string $postcode,
        ?float $latitude,
        ?float $longitude,
        ?string $linkUrl,
        bool $isPublished,
    ): void {
        $this->database->bind(param: ':name', value: $name);
        $this->database->bind(param: ':start_date', value: $startDate);
        $this->database->bind(param: ':end_date', value: $endDate);
        $this->database->bind(param: ':location', value: $location);
        $this->database->bind(param: ':postcode', value: $postcode);
        $this->database->bind(param: ':latitude', value: $latitude);
        $this->database->bind(param: ':longitude', value: $longitude);
        $this->database->bind(param: ':link_url', value: $linkUrl);
        $this->database->bind(param: ':is_published', value: $isPublished ? 1 : 0);
    }
}
