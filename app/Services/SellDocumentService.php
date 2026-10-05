<?php

namespace App\Services;

use App\Media;
use App\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
