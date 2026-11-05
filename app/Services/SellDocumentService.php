<?php

namespace App\Services;

use App\Media;
use App\Transaction;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * Multiple "Attach Document" files on sells / sales orders, stored as Media rows (model_media_type = sell_document).
 * The legacy single `transactions.document` column is still shown and can be removed.
 */
class SellDocumentService
{
    public const INPUT_NAME = 'sell_documents';

    public const MEDIA_TYPE = 'sell_document';

    public const MAX_FILES_PER_REQUEST = 10;

    public const POS_IMAGE_INPUT = 'image';

    public const DOCUMENTS_DIR = 'documents';

    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public function __construct(private Util $util)
    {
    }

    /**
     * Stores the image picked in the POS payment modal ahead of the sale submit
     * and returns the stored filename (later posted as `document`).
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function storePosImage(Request $request): ?string
    {
        $maxKb = (int) floor(((int) config('constants.document_size_limit')) / 1024);

        Validator::make($request->only(self::POS_IMAGE_INPUT), [
            self::POS_IMAGE_INPUT => 'required|file|image|mimes:' . implode(',', self::IMAGE_EXTENSIONS) . '|max:' . $maxKb,
        ])->validate();

        return $this->util->uploadFile($request, self::POS_IMAGE_INPUT, self::DOCUMENTS_DIR, 'image');
    }

    /**
     * Returns the filename only if it is a bare image name already stored in uploads/documents,
     * so a posted string can never point outside that folder or at a file that was not uploaded.
     */
    public function resolveUploadedImageName($fileName): ?string
    {
        if (! is_string($fileName) || $fileName === '') {
            return null;
        }

        if (str_contains($fileName, '/') || str_contains($fileName, '\\') || str_contains($fileName, '..')) {
            return null;
        }

        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (! in_array($extension, self::IMAGE_EXTENSIONS, true)) {
            return null;
        }

        return Storage::exists(self::DOCUMENTS_DIR . '/' . $fileName) ? $fileName : null;
    }

    public static function isImageFile(?string $fileName): bool
    {
        return ! empty($fileName)
            && in_array(strtolower(pathinfo($fileName, PATHINFO_EXTENSION)), self::IMAGE_EXTENSIONS, true);
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function validateUploads(Request $request): void
    {
        $maxKb = (int) floor(((int) config('constants.document_size_limit')) / 1024);
        $mimeTypes = implode(',', array_keys(config('constants.document_upload_mimes_types')));

        Validator::make($request->only(self::INPUT_NAME), [
            self::INPUT_NAME => 'nullable|array|max:' . self::MAX_FILES_PER_REQUEST,
            self::INPUT_NAME . '.*' => 'file|max:' . $maxKb . '|mimetypes:' . $mimeTypes,
        ])->validate();
    }

    public function storeUploads(int $businessId, Transaction $transaction, Request $request): void
    {
        Media::uploadMedia($businessId, $transaction, $request, self::INPUT_NAME, false, self::MEDIA_TYPE);
    }

    public function documentsFor(Transaction $transaction): Collection
    {
        return $transaction->media->where('model_media_type', self::MEDIA_TYPE)->values();
    }

    public function deleteDocument(int $businessId, Transaction $transaction, int $mediaId): void
    {
        $media = $transaction->media()
            ->where('business_id', $businessId)
            ->where('model_media_type', self::MEDIA_TYPE)
            ->findOrFail($mediaId);

        Media::deleteMedia($businessId, $media->id);
    }

    public function deleteLegacyDocument(Transaction $transaction): void
    {
        if (empty($transaction->document)) {
            return;
        }

        $path = public_path('uploads/documents/' . basename($transaction->document));
        if (is_file($path)) {
            @unlink($path);
        }

        $transaction->document = null;
        $transaction->save();
    }
}
