<?php

declare(strict_types=1);

namespace Osmium\Services\Shows\Controllers;

use Osmium\Modules\Admin\Core\AdminController;
use Osmium\Services\Shows\Models\Show;
use Osmium\Services\Geocoder\Models\PostcodeGeocoder;

/**
 * Shows controller - manages the curated listing behind a public shows page.
 *
 * Depends on the "geocoder" service (see this package's service.json
 * "requires") - ServiceDependencyResolver guarantees it's installed before
 * this service can be, so PostcodeGeocoder is safely referenced directly.
 *
 * Routes:
 *   - index()  → /admin/shows/
 *   - action() → /admin/shows/action/   (AJAX CRUD + logo upload)
 */
class ShowsController extends AdminController
{
    private const RECORD_TYPE = 'show';
    private const IMAGE_PATH_PREFIX = '/shows/';
    private const IMAGE_ASSET_DIR = '/assets/images/shows/';
    private const LOGO_MAX_DIMENSION = 400;
    private const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'];

    // =========================================================================
    // Public Actions
    // =========================================================================

    public function index(): void
    {
        $model = new Show(database: $this->osmium->dataSource);
        $shows = $model->listAll();

        // Mirrors a public page's own past-shows cutoff convention, so
        // "archived" here means exactly what a public page would exclude.
        $archivedCutoff = \date('Y-01-01');
        $currentShows = [];
        $archivedShows = [];
        foreach ($shows as $show) {
            $isArchived = $show['end_date'] < $archivedCutoff;
            if ($isArchived) {
                $archivedShows[] = $show;
            } else {
                $currentShows[] = $show;
            }
        }

        $this->data['admin']['shows'] = \array_map($this->formatShow(...), $currentShows);
        $this->data['admin']['archivedShows'] = \array_map($this->formatShow(...), $archivedShows);
        $this->data['admin']['venues'] = $this->distinctVenues($shows);
        $this->data['admin']['showNames'] = $this->distinctShowNames($shows);

        $this->setView('shows/index.phtml');
    }

    public function action()
    {
        \header('Content-Type: application/json');

        $isPost = $this->isPost();
        if (!$isPost) $this->admin->jsonError('Method not allowed');

        // Logo uploads arrive as multipart FormData, everything else as JSON.
        $input = $this->admin->auth->getJsonInput();
        $action = $input['action'] ?? '';
        $id = (int) ($input['id'] ?? 0);

        $csrfValid = $this->admin->auth->validateCsrfJson($input);
        if (!$csrfValid) $this->admin->jsonError('Invalid request token. Please refresh and try again.');

        $needsId = $action !== 'create';
        $missingParams = !$action || ($needsId && !$id);
        if ($missingParams) $this->admin->jsonError('Missing parameters');

        try {
            $result = match ($action) {
                'create' => $this->createShow($input),
                'update' => $this->updateShow(id: $id, input: $input),
                'toggle_published' => $this->togglePublished(id: $id, input: $input),
                'upload_image' => $this->uploadShowImage(id: $id, file: $_FILES['image'] ?? []),
                'delete' => $this->deleteShow($id),
                default => throw new \InvalidArgumentException('Unknown action'),
            };

            $this->admin->jsonSuccess($result);
        } catch (\Exception $e) {
            $this->admin->jsonError($e->getMessage());
        }
    }

    // =========================================================================
    // Action Helpers
    // =========================================================================

    private function createShow(array $input): array
    {
        $details = $this->validateDetails($input);
        $model = new Show(database: $this->osmium->dataSource);

        $newId = $model->create(
            name: $details['name'],
            startDate: $details['startDate'],
            endDate: $details['endDate'],
            location: $details['location'],
            postcode: $details['postcode'],
            latitude: $details['latitude'],
            longitude: $details['longitude'],
            linkUrl: $details['linkUrl'],
            isPublished: $details['isPublished'],
        );

        $this->logChange(action: 'Created', id: $newId, name: $details['name']);

        return ['success' => true, 'id' => $newId];
    }

    private function updateShow(int $id, array $input): array
    {
        $details = $this->validateDetails($input);
        $model = new Show(database: $this->osmium->dataSource);

        $existing = $model->getById($id);
        if (!$existing) throw new \InvalidArgumentException('Show not found');

        // Only re-geocode when the postcode actually changed, so a details save
        // doesn't depend on postcodes.io being reachable.
        $postcodeUnchanged = $details['postcode'] === ($existing['postcode'] ?? null);
        if ($postcodeUnchanged) {
            $details['latitude'] = $existing['latitude'] !== null ? (float) $existing['latitude'] : null;
            $details['longitude'] = $existing['longitude'] !== null ? (float) $existing['longitude'] : null;
        }

        $model->update(
            id: $id,
            name: $details['name'],
            startDate: $details['startDate'],
            endDate: $details['endDate'],
            location: $details['location'],
            postcode: $details['postcode'],
            latitude: $details['latitude'],
            longitude: $details['longitude'],
            linkUrl: $details['linkUrl'],
            isPublished: $details['isPublished'],
        );

        $this->logChange(action: 'Updated', id: $id, name: $details['name']);

        return ['success' => true, 'id' => $id];
    }

    private function togglePublished(int $id, array $input): array
    {
        $model = new Show(database: $this->osmium->dataSource);
        $show = $model->getById($id);
        if (!$show) throw new \InvalidArgumentException('Show not found');

        $isPublished = !empty($input['is_published']);
        $model->updatePublished(id: $id, isPublished: $isPublished);

        $action = $isPublished ? 'Published' : 'Hidden';
        $this->logChange(action: $action, id: $id, name: $show['name']);

        return ['success' => true, 'id' => $id, 'is_published' => $isPublished];
    }

    private function deleteShow(int $id): array
    {
        $model = new Show(database: $this->osmium->dataSource);
        $show = $model->getById($id);
        if (!$show) throw new \InvalidArgumentException('Show not found');

        $model->softDelete($id);

        $this->logChange(action: 'Deleted', id: $id, name: $show['name']);

        return ['success' => true, 'id' => $id];
    }

    /**
     * Validate and normalise the details form, geocoding the postcode so the
     * show gets a map pin.
     *
     * @return array{name: string, startDate: string, endDate: string, location: ?string, postcode: ?string, latitude: ?float, longitude: ?float, linkUrl: ?string, isPublished: bool}
     */
    private function validateDetails(array $input): array
    {
        $name = \trim($input['name'] ?? '');
        $startDate = \trim($input['start_date'] ?? '');
        $endDate = \trim($input['end_date'] ?? '');

        $requiredFieldsMissing = $name === '' || $startDate === '' || $endDate === '';
        if ($requiredFieldsMissing) throw new \InvalidArgumentException('Name, start date and end date are required');

        $datesOutOfOrder = $endDate < $startDate;
        if ($datesOutOfOrder) throw new \InvalidArgumentException('End date cannot be before the start date');

        $location = \trim($input['location'] ?? '');
        $postcode = \strtoupper(\trim($input['postcode'] ?? ''));
        $linkUrl = \trim($input['link_url'] ?? '');

        $coordinates = $postcode !== '' ? (new PostcodeGeocoder())->geocode($postcode) : null;

        return [
            'name' => $name,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'location' => $location !== '' ? $location : null,
            'postcode' => $postcode !== '' ? $postcode : null,
            'latitude' => $coordinates['latitude'] ?? null,
            'longitude' => $coordinates['longitude'] ?? null,
            'linkUrl' => $linkUrl !== '' ? $linkUrl : null,
            'isPublished' => !empty($input['is_published']),
        ];
    }

    /**
     * Upload a show's logo for its card on a public shows page.
     */
    private function uploadShowImage(int $id, array $file): array
    {
        $uploadFailed = !isset($file['error']) || $file['error'] !== \UPLOAD_ERR_OK;
        if ($uploadFailed) throw new \InvalidArgumentException('No file uploaded');

        $finfo = new \finfo(\FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        $invalidMimeType = !\in_array(needle: $mimeType, haystack: self::ALLOWED_IMAGE_TYPES);
        if ($invalidMimeType) throw new \InvalidArgumentException('Invalid file type. Allowed: JPG, PNG, GIF, WebP, AVIF');

        $maxUpload = \Osmium\Modules\Admin\Services\OsmiumAdmin::getMaxUploadSize();
        $tooLarge = $file['size'] > $maxUpload['bytes'];
        if ($tooLarge) throw new \InvalidArgumentException('File too large. Maximum ' . $maxUpload['formatted'] . ' allowed');

        $model = new Show(database: $this->osmium->dataSource);
        $show = $model->getById($id);
        if (!$show) throw new \InvalidArgumentException('Show not found');

        $basename = 'show_' . $id . '_' . \time();
        $imageDir = $_SERVER['DOCUMENT_ROOT'] . self::IMAGE_ASSET_DIR;

        $destImage = $this->resizeLogo(sourcePath: $file['tmp_name'], mimeType: $mimeType);
        $this->saveImageFormats(sourceImage: $destImage, baseDir: $imageDir, filename: $basename);

        $model->updateImage(id: $id, imageFilename: $basename);

        $this->logChange(action: 'Updated logo for', id: $id, name: $show['name']);

        // Stored without an extension - Osmium::imagePath() then tries avif/webp
        // first and falls back to this .png master automatically (see Image.php).
        return ['success' => true, 'id' => $id, 'image_filename' => $basename];
    }

    // =========================================================================
    // View Helpers
    // =========================================================================

    /**
     * @return string[] distinct, sorted show names already used on other shows.
     */
    private function distinctShowNames(array $shows): array
    {
        $names = \array_unique(\array_filter(\array_column($shows, 'name')));
        \sort($names);

        return $names;
    }

    /**
     * @return array<string,string> distinct, sorted venue names mapped to the
     * postcode last used with them, so picking a venue can prefill the postcode.
     */
    private function distinctVenues(array $shows): array
    {
        $venues = [];
        foreach ($shows as $show) {
            $location = $show['location'] ?? '';
            if ($location === '') continue;
            $venues[$location] = $show['postcode'] ?? '';
        }
        \ksort($venues);

        return $venues;
    }

    private function formatShow(array $row): array
    {
        $hasImage = !empty($row['image_filename']);

        return [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'location' => $row['location'] ?? '',
            'postcode' => $row['postcode'] ?? '',
            'startDate' => $row['start_date'],
            'endDate' => $row['end_date'],
            'dateRange' => $this->formatDateRange($row['start_date'], $row['end_date']),
            'linkUrl' => $row['link_url'] ?? '',
            'isPublished' => (bool) $row['is_published'],
            // A pin exists only where a postcode geocoded, so the only signal
            // worth showing is a postcode that didn't resolve - there is no
            // separate pin to toggle, and an unpublished show is off the map
            // entirely regardless of its coordinates.
            'geocodeFailed' => !empty($row['postcode']) && $row['latitude'] === null,
            'hasImage' => $hasImage,
            'imageUrl' => $hasImage
                ? $this->osmium->imagePath(self::IMAGE_PATH_PREFIX . $row['image_filename'])
                : null,
        ];
    }

    private function formatDateRange(string $startDate, string $endDate): string
    {
        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);

        $sameDay = $start->format('Y-m-d') === $end->format('Y-m-d');
        if ($sameDay) return $start->format('j M Y');

        $sameMonth = $start->format('Y-m') === $end->format('Y-m');
        if ($sameMonth) return $start->format('j') . ' - ' . $end->format('j M Y');

        return $start->format('j M') . ' - ' . $end->format('j M Y');
    }

    private function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    private function logChange(string $action, int $id, string $name): void
    {
        $this->admin->model->changelog->log(
            description: $action . ': ' . $name,
            recordType: self::RECORD_TYPE,
            recordId: $id,
        );
    }

    /**
     * Resize the uploaded logo to fit within LOGO_MAX_DIMENSION square,
     * preserving aspect ratio (no cropping to a fixed shape - logos are
     * rarely square). Surrounding whitespace baked into the source file is
     * trimmed first, otherwise it counts towards LOGO_MAX_DIMENSION and the
     * logo mark itself ends up rendering small on the page.
     */
    private function resizeLogo(string $sourcePath, string $mimeType): \GdImage
    {
        $decoderFunction = match ($mimeType) {
            'image/jpeg' => 'imagecreatefromjpeg',
            'image/png' => 'imagecreatefrompng',
            'image/gif' => 'imagecreatefromgif',
            'image/webp' => 'imagecreatefromwebp',
            'image/avif' => 'imagecreatefromavif',
            default => null,
        };

        $unsupportedFormat = $decoderFunction === null || !\function_exists($decoderFunction);
        if ($unsupportedFormat) {
            throw new \Exception("This server can't process {$mimeType} images. Try JPG or PNG instead.");
        }

        $sourceImage = $decoderFunction($sourcePath);
        if (!$sourceImage) throw new \Exception('Failed to process image');

        $sourceImage = $this->cropToContent($sourceImage);

        $origWidth = \imagesx($sourceImage);
        $origHeight = \imagesy($sourceImage);
        $scale = \min(1, self::LOGO_MAX_DIMENSION / \max($origWidth, $origHeight));
        $destWidth = (int) \round($origWidth * $scale);
        $destHeight = (int) \round($origHeight * $scale);

        $destImage = \imagecreatetruecolor(width: $destWidth, height: $destHeight);
        \imagealphablending(image: $destImage, enable: false);
        \imagesavealpha(image: $destImage, enable: true);

        \imagecopyresampled(
            dst_image: $destImage,
            src_image: $sourceImage,
            dst_x: 0,
            dst_y: 0,
            src_x: 0,
            src_y: 0,
            dst_width: $destWidth,
            dst_height: $destHeight,
            src_width: $origWidth,
            src_height: $origHeight,
        );

        return $destImage;
    }

    /**
     * Crop away any solid-white or transparent margin surrounding the logo
     * mark, so LOGO_MAX_DIMENSION scaling is applied to the actual content
     * rather than to baked-in whitespace. Returns the original image
     * untouched if no croppable content boundary is found.
     */
    private function cropToContent(\GdImage $image): \GdImage
    {
        $width = \imagesx($image);
        $height = \imagesy($image);

        $bounds = $this->findContentBounds(image: $image, width: $width, height: $height);
        if (!$bounds) return $image;

        // Padding is applied equally on every side by pasting the tight
        // content crop onto a fresh, larger canvas - never by clamping the
        // crop rectangle to the original image's edges. Clamping made the
        // margin uneven (sometimes 0) whenever the logo mark already sat
        // close to one edge of the uploaded file, which left some show
        // logos visibly touching the top of their card while others didn't.
        $padding = (int) \round(\max($bounds['width'], $bounds['height']) * 0.08);

        $tight = \imagecreatetruecolor(width: $bounds['width'], height: $bounds['height']);
        \imagealphablending(image: $tight, enable: false);
        \imagesavealpha(image: $tight, enable: true);
        \imagecopy(
            dst_image: $tight,
            src_image: $image,
            dst_x: 0,
            dst_y: 0,
            src_x: $bounds['minX'],
            src_y: $bounds['minY'],
            src_width: $bounds['width'],
            src_height: $bounds['height'],
        );

        $canvasWidth = $bounds['width'] + $padding * 2;
        $canvasHeight = $bounds['height'] + $padding * 2;

        $canvas = \imagecreatetruecolor(width: $canvasWidth, height: $canvasHeight);
        \imagealphablending(image: $canvas, enable: false);
        \imagesavealpha(image: $canvas, enable: true);

        $backgroundColor = $this->sampleBorderColor(image: $image, width: $width, height: $height);
        $fill = $backgroundColor === null
            ? \imagecolorallocatealpha($canvas, 0, 0, 0, 127)
            : \imagecolorallocate($canvas, $backgroundColor['r'], $backgroundColor['g'], $backgroundColor['b']);
        \imagefill(image: $canvas, x: 0, y: 0, color: $fill);

        \imagealphablending(image: $canvas, enable: true);
        \imagecopy(
            dst_image: $canvas,
            src_image: $tight,
            dst_x: $padding,
            dst_y: $padding,
            src_x: 0,
            src_y: 0,
            src_width: $bounds['width'],
            src_height: $bounds['height'],
        );

        return $canvas;
    }

    /**
     * Find the pixel bounding box of "content" - anything that isn't fully/
     * near transparent or close to the image's own border colour. The
     * border colour is sampled rather than assumed to be white, since logo
     * exports commonly sit on a flat colour tile (navy, brand colour, etc.)
     * rather than a white or transparent background.
     *
     * @return array{minX: int, minY: int, width: int, height: int}|null
     */
    private function findContentBounds(\GdImage $image, int $width, int $height): ?array
    {
        $transparentThreshold = 120; // GD alpha: 0 = opaque, 127 = fully transparent
        $colorTolerance = 32; // max per-channel distance still counted as background

        $backgroundColor = $this->sampleBorderColor(image: $image, width: $width, height: $height);

        $minX = $width;
        $maxX = -1;
        $minY = $height;
        $maxY = -1;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgba = \imagecolorat($image, $x, $y);
                $alpha = ($rgba >> 24) & 0x7F;
                $red = ($rgba >> 16) & 0xFF;
                $green = ($rgba >> 8) & 0xFF;
                $blue = $rgba & 0xFF;

                $isTransparent = $alpha >= $transparentThreshold;
                $matchesBackground = $backgroundColor !== null
                    && \abs($red - $backgroundColor['r']) <= $colorTolerance
                    && \abs($green - $backgroundColor['g']) <= $colorTolerance
                    && \abs($blue - $backgroundColor['b']) <= $colorTolerance;
                $isBackground = $isTransparent || $matchesBackground;
                if ($isBackground) continue;

                $minX = \min($minX, $x);
                $maxX = \max($maxX, $x);
                $minY = \min($minY, $y);
                $maxY = \max($maxY, $y);
            }
        }

        $noContentFound = $maxX < $minX;
        if ($noContentFound) return null;

        return [
            'minX' => $minX,
            'minY' => $minY,
            'width' => $maxX - $minX + 1,
            'height' => $maxY - $minY + 1,
        ];
    }

    /**
     * Sample opaque pixels along all four edges to find the image's own
     * background colour, rather than assuming it's white. Uses the most
     * common colour among the samples (in coarse buckets, to absorb
     * compression noise) so a single corner touching the logo mark doesn't
     * throw off the result.
     *
     * @return array{r: int, g: int, b: int}|null null if the border is
     *   entirely transparent (nothing meaningful to sample) or has no
     *   dominant colour.
     */
    private function sampleBorderColor(\GdImage $image, int $width, int $height): ?array
    {
        $bucketSize = 16; // quantise channels to absorb anti-aliasing/compression noise
        $step = \max(1, (int) \round(\min($width, $height) / 20));

        $points = [];
        for ($x = 0; $x < $width; $x += $step) {
            $points[] = [$x, 0];
            $points[] = [$x, $height - 1];
        }
        for ($y = 0; $y < $height; $y += $step) {
            $points[] = [0, $y];
            $points[] = [$width - 1, $y];
        }

        $buckets = []; // bucketKey => ['count' => int, 'r' => sum, 'g' => sum, 'b' => sum]
        foreach ($points as [$x, $y]) {
            $rgba = \imagecolorat($image, $x, $y);
            $alpha = ($rgba >> 24) & 0x7F;
            $isTransparent = $alpha >= 120;
            if ($isTransparent) continue;

            $red = ($rgba >> 16) & 0xFF;
            $green = ($rgba >> 8) & 0xFF;
            $blue = $rgba & 0xFF;

            $key = \intdiv($red, $bucketSize) . '-' . \intdiv($green, $bucketSize) . '-' . \intdiv($blue, $bucketSize);
            $buckets[$key] ??= ['count' => 0, 'r' => 0, 'g' => 0, 'b' => 0];
            $buckets[$key]['count']++;
            $buckets[$key]['r'] += $red;
            $buckets[$key]['g'] += $green;
            $buckets[$key]['b'] += $blue;
        }

        $noOpaqueBorderPixels = empty($buckets);
        if ($noOpaqueBorderPixels) return null;

        \uasort($buckets, fn($a, $b) => $b['count'] <=> $a['count']);
        $winner = \reset($buckets);

        return [
            'r' => (int) \round($winner['r'] / $winner['count']),
            'g' => (int) \round($winner['g'] / $winner['count']),
            'b' => (int) \round($winner['b'] / $winner['count']),
        ];
    }

    /**
     * Save the resized logo as a PNG master plus WebP/AVIF variants, matching
     * the format-generation convention served transparently by
     * Osmium::imagePath() (which prefers avif, then webp).
     */
    private function saveImageFormats(\GdImage $sourceImage, string $baseDir, string $filename): void
    {
        // This directory isn't pre-seeded on the server (gitignored, deploy
        // never creates it) - so create it on demand.
        $this->ensureDirectoryExists($baseDir);
        $this->ensureDirectoryExists($baseDir . 'webp/');
        $this->ensureDirectoryExists($baseDir . 'avif/');

        $pngPath = $baseDir . $filename . '.png';
        $saved = \imagepng(image: $sourceImage, file: $pngPath, quality: 6);
        if (!$saved) throw new \Exception('Failed to save image');

        $hasWebp = \function_exists('imagewebp');
        if ($hasWebp) {
            $webpPath = $baseDir . 'webp/' . $filename . '.webp';
            \imagewebp(image: $sourceImage, file: $webpPath, quality: 80);
        }

        $hasAvif = \function_exists('imageavif');
        if ($hasAvif) {
            $avifPath = $baseDir . 'avif/' . $filename . '.avif';
            \imageavif(image: $sourceImage, file: $avifPath, quality: 60);
        }
    }

    private function ensureDirectoryExists(string $path): void
    {
        $alreadyExists = \is_dir($path);
        if ($alreadyExists) return;

        $created = \mkdir(directory: $path, permissions: 0755, recursive: true);
        $stillMissing = !$created && !\is_dir($path); // another request may have created it first
        if ($stillMissing) throw new \Exception("Failed to create directory: {$path}");
    }
}
