<?php

namespace App\Services\Storefront;

use App\StorefrontMedia;
use App\Utils\Util;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Storefront media library: checksum-deduped uploads under uploads/storefront_library/{business_id}/.
 */
class StorefrontMediaLibraryService
{
    public const DIR = 'storefront_library';

    public const MAX_BYTES = 5_242_880; // 5 MB

    public function __construct(
        private Util $util
    ) {
    }

    /**
     * @return array{items: list<array<string, mixed>>, meta: array{current_page: int, last_page: int, per_page: int, total: int}}
     */
    public function list(int $businessId, ?string $kind = null, ?string $q = null, int $page = 1, int $perPage = 24): array
    {
        $perPage = max(1, min(48, $perPage));
        $page = max(1, $page);

        $query = StorefrontMedia::query()
            ->where('business_id', $businessId)
            ->orderByDesc('id');

        if ($kind === 'image' || $kind === 'svg') {
            $query->where('kind', $kind);
        }

        $q = trim((string) $q);
        if ($q !== '') {
            $query->where('original_name', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%');
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'items' => array_map(fn (StorefrontMedia $m) => $this->present($m), $paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    public function findForBusiness(int $businessId, int $id): ?StorefrontMedia
    {
        return StorefrontMedia::query()
            ->where('business_id', $businessId)
            ->where('id', $id)
            ->first();
    }

    /**
     * Store an uploaded file (or return existing row with the same checksum).
     *
     * @return array{media: StorefrontMedia, created: bool, svg_markup: string|null}
     */
    public function storeUploadedFile(int $businessId, UploadedFile $file, ?int $uploadedBy = null): array
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $mime = strtolower((string) ($file->getMimeType() ?: ''));
        $isSvg = $ext === 'svg' || str_contains($mime, 'svg');
        $kind = $isSvg ? 'svg' : 'image';

        $bytes = (int) $file->getSize();
        if ($bytes < 1 || $bytes > self::MAX_BYTES) {
            throw new \InvalidArgumentException('File too large (max 5MB).');
        }

        $raw = @file_get_contents($file->getRealPath());
        if (! is_string($raw) || $raw === '') {
            throw new \InvalidArgumentException('Could not read uploaded file.');
        }

        $svgMarkup = null;
        if ($isSvg) {
            $svgMarkup = app(\App\Services\Storefront\Homepage\HomepageSectionService::class)
                ->sanitizeSvgForUpload($raw);
            if ($svgMarkup === null || $svgMarkup === '') {
                throw new \InvalidArgumentException('Invalid SVG file.');
            }
            $raw = $svgMarkup;
            $bytes = strlen($raw);
        }

        $checksum = hash('sha256', $raw);
        $existing = StorefrontMedia::withTrashed()
            ->where('business_id', $businessId)
            ->where('checksum', $checksum)
            ->first();

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
            }

            return [
                'media' => $existing->fresh(),
                'created' => false,
                'svg_markup' => $isSvg ? $this->readSvgMarkup($existing) : null,
            ];
        }

        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName()) ?: 'file';
        if ($isSvg && ! str_ends_with(strtolower($safeName), '.svg')) {
            $safeName .= '.svg';
        }
        $filename = time().'_'.Str::lower(Str::random(8)).'_'.$safeName;
        $relativeDir = self::DIR.'/'.$businessId;
        $relativePath = $relativeDir.'/'.$filename;

        $this->util->ensurePublicUploadPermissions(self::DIR.'/'.$businessId, null, true);
        $absDir = public_path('uploads/'.$relativeDir);
        if (! is_dir($absDir) && ! @mkdir($absDir, 0755, true) && ! is_dir($absDir)) {
            throw new \RuntimeException('Could not create media library directory.');
        }

        $absPath = $absDir.DIRECTORY_SEPARATOR.$filename;
        if (@file_put_contents($absPath, $raw) === false) {
            throw new \RuntimeException('Could not store media file.');
        }
        $this->util->ensurePublicUploadPermissions(self::DIR.'/'.$businessId, $filename);

        $media = StorefrontMedia::create([
            'business_id' => $businessId,
            'path' => $relativePath,
            'original_name' => mb_substr((string) $file->getClientOriginalName(), 0, 255),
            'mime' => $isSvg ? 'image/svg+xml' : mb_substr($mime !== '' ? $mime : 'application/octet-stream', 0, 120),
            'kind' => $kind,
            'bytes' => $bytes,
            'checksum' => $checksum,
            'uploaded_by' => $uploadedBy,
        ]);

        return [
            'media' => $media,
            'created' => true,
            'svg_markup' => $isSvg ? $svgMarkup : null,
        ];
    }

    /**
     * Store sanitized SVG markup (paste path) with checksum dedupe.
     *
     * @return array{media: StorefrontMedia, created: bool}
     */
    public function storeSvgMarkup(int $businessId, string $svgMarkup, ?int $uploadedBy = null, string $originalName = 'pasted.svg'): array
    {
        $clean = app(\App\Services\Storefront\Homepage\HomepageSectionService::class)
            ->sanitizeSvgForUpload($svgMarkup);
        if ($clean === null || $clean === '') {
            throw new \InvalidArgumentException('Invalid SVG markup.');
        }

        $checksum = hash('sha256', $clean);
        $existing = StorefrontMedia::withTrashed()
            ->where('business_id', $businessId)
            ->where('checksum', $checksum)
            ->first();
        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
            }

            return ['media' => $existing->fresh(), 'created' => false];
        }

        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName) ?: 'pasted.svg';
        if (! str_ends_with(strtolower($safeName), '.svg')) {
            $safeName .= '.svg';
        }
        $filename = 'paste_'.time().'_'.Str::lower(Str::random(8)).'_'.$safeName;
        $relativeDir = self::DIR.'/'.$businessId;
        $relativePath = $relativeDir.'/'.$filename;

        $this->util->ensurePublicUploadPermissions(self::DIR.'/'.$businessId, null, true);
        $absDir = public_path('uploads/'.$relativeDir);
        if (! is_dir($absDir) && ! @mkdir($absDir, 0755, true) && ! is_dir($absDir)) {
            throw new \RuntimeException('Could not create media library directory.');
        }
        if (@file_put_contents($absDir.DIRECTORY_SEPARATOR.$filename, $clean) === false) {
            throw new \RuntimeException('Could not store SVG file.');
        }
        $this->util->ensurePublicUploadPermissions(self::DIR.'/'.$businessId, $filename);

        $media = StorefrontMedia::create([
            'business_id' => $businessId,
            'path' => $relativePath,
            'original_name' => mb_substr($originalName, 0, 255),
            'mime' => 'image/svg+xml',
            'kind' => 'svg',
            'bytes' => strlen($clean),
            'checksum' => $checksum,
            'uploaded_by' => $uploadedBy,
        ]);

        return ['media' => $media, 'created' => true];
    }

    public function delete(int $businessId, int $id): bool
    {
        $media = $this->findForBusiness($businessId, $id);
        if (! $media) {
            return false;
        }

        // Soft-delete only — keep the file so a later identical upload can restore the row.
        $media->delete();

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function present(StorefrontMedia $media): array
    {
        return [
            'id' => (int) $media->id,
            'path' => (string) $media->path,
            'image' => (string) $media->path, // homepage builder uses `image` as storage key
            'original_name' => (string) ($media->original_name ?? ''),
            'mime' => (string) ($media->mime ?? ''),
            'kind' => (string) $media->kind,
            'bytes' => (int) $media->bytes,
            'url' => $media->url,
            'image_url' => $media->url,
            'created_at' => optional($media->created_at)?->toIso8601String(),
        ];
    }

    public function readSvgMarkup(StorefrontMedia $media): ?string
    {
        if ($media->kind !== 'svg') {
            return null;
        }
        $abs = $media->absolutePath();
        if (! is_readable($abs)) {
            return null;
        }
        $size = @filesize($abs);
        if (! is_int($size) || $size < 1 || $size > 120000) {
            return null;
        }
        $raw = @file_get_contents($abs);
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        return app(\App\Services\Storefront\Homepage\HomepageSectionService::class)->sanitizeSvgForUpload($raw);
    }
}
