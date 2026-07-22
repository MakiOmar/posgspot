<?php

namespace App\Services\Storefront;

use App\StorefrontMedia;
use App\Utils\Util;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Storefront media library: checksum-deduped uploads under uploads/storefront_library/{business_id}/.
 *
 * SVGs are stored as files and served by URL — never sanitized or inlined into settings/API JSON.
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
     * @return array{media: StorefrontMedia, created: bool}
     */
    public function storeUploadedFile(int $businessId, UploadedFile $file, ?int $uploadedBy = null): array
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $mime = strtolower((string) ($file->getMimeType() ?: ''));
        $isSvg = $ext === 'svg' || str_contains($mime, 'svg');
        $kind = $isSvg ? 'svg' : 'image';

        $realPath = $file->getRealPath();
        if (! is_string($realPath) || $realPath === '' || ! is_readable($realPath)) {
            throw new \InvalidArgumentException('Could not read uploaded file.');
        }

        $bytes = (int) $file->getSize();
        if ($bytes < 1 || $bytes > self::MAX_BYTES) {
            throw new \InvalidArgumentException('File too large (max 5MB).');
        }

        if ($isSvg && ! $this->peekLooksLikeSvg($realPath)) {
            throw new \InvalidArgumentException('Invalid SVG file.');
        }

        // Hash from disk — do not load the whole file into a PHP string for checksum.
        $checksum = hash_file('sha256', $realPath);
        if (! is_string($checksum) || $checksum === '') {
            throw new \InvalidArgumentException('Could not fingerprint uploaded file.');
        }

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
        // Stream copy — avoid loading multi‑MB uploads into memory.
        if (! @copy($realPath, $absPath)) {
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
        ];
    }

    /**
     * Soft-delete a library asset (file retained so checksum restore can revive the row).
     */
    public function delete(int $businessId, int $id): bool
    {
        $media = $this->findForBusiness($businessId, $id);
        if (! $media) {
            return false;
        }

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
            'image' => (string) $media->path,
            'original_name' => (string) ($media->original_name ?? ''),
            'mime' => (string) ($media->mime ?? ''),
            'kind' => (string) $media->kind,
            'bytes' => (int) $media->bytes,
            'url' => $media->url,
            'image_url' => $media->url,
            'created_at' => optional($media->created_at)?->toIso8601String(),
        ];
    }

    /**
     * Peek at the start of a file for an <svg tag — never load/sanitize the whole document.
     */
    private function peekLooksLikeSvg(string $absolutePath): bool
    {
        $fh = @fopen($absolutePath, 'rb');
        if ($fh === false) {
            return false;
        }
        $peek = fread($fh, 8192);
        fclose($fh);
        if (! is_string($peek) || $peek === '') {
            return false;
        }

        return stripos($peek, '<svg') !== false;
    }
}
