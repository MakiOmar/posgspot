<?php

namespace App\Services\Storefront;

use App\Contact;
use App\Product;
use App\ProductReview;
use App\Transaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Moderated product reviews for the public storefront.
 */
class ProductReviewService
{
    public function __construct(
        private CatalogService $catalog,
        private StorefrontSettingService $storefrontSettings
    ) {
    }

    public function hasPurchasedProduct(int $businessId, int $contactId, int $productId): bool
    {
        return Transaction::where('business_id', $businessId)
            ->where('contact_id', $contactId)
            ->where('type', 'sell')
            ->where('status', 'final')
            ->whereHas('sell_lines', fn (Builder $q) => $q->where('product_id', $productId))
            ->exists();
    }

    /**
     * @return array{can_review: bool, already_reviewed: bool, reason: string|null}
     */
    public function eligibility(int $businessId, Contact $contact, int $productId): array
    {
        if (! $this->productExistsForBusiness($businessId, $productId)) {
            return [
                'can_review' => false,
                'already_reviewed' => false,
                'reason' => 'not_found',
            ];
        }

        $existing = ProductReview::where('business_id', $businessId)
            ->where('product_id', $productId)
            ->where('contact_id', $contact->id)
            ->first();

        if ($existing && ! $existing->isRejected()) {
            return [
                'can_review' => false,
                'already_reviewed' => true,
                'reason' => $existing->isPending() ? 'pending' : 'already_reviewed',
            ];
        }

        if (! $this->hasPurchasedProduct($businessId, (int) $contact->id, $productId)) {
            return [
                'can_review' => false,
                'already_reviewed' => false,
                'reason' => 'not_purchased',
            ];
        }

        return [
            'can_review' => true,
            'already_reviewed' => false,
            'reason' => null,
        ];
    }

    /**
     * @param  array{rating: int, title?: string|null, body: string}  $input
     * @return array<string, mixed>
     */
    public function submit(int $businessId, Contact $contact, int $productId, array $input): array
    {
        $eligibility = $this->eligibility($businessId, $contact, $productId);
        if (! $eligibility['can_review']) {
            $message = match ($eligibility['reason']) {
                'not_found' => 'Product not found.',
                'pending' => 'Your review is awaiting moderation.',
                'already_reviewed' => 'You have already reviewed this product.',
                'not_purchased' => 'Only customers who purchased this product can leave a review.',
                default => 'You cannot review this product.',
            };

            throw ValidationException::withMessages(['product' => [$message]]);
        }

        $rating = (int) $input['rating'];
        $title = isset($input['title']) ? trim((string) $input['title']) : null;
        if ($title === '') {
            $title = null;
        }
        $body = trim((string) $input['body']);

        $review = ProductReview::updateOrCreate(
            [
                'product_id' => $productId,
                'contact_id' => $contact->id,
            ],
            [
                'business_id' => $businessId,
                'rating' => $rating,
                'title' => $title,
                'body' => $body,
                'status' => ProductReview::STATUS_PENDING,
                'is_verified_purchase' => true,
                'moderated_by' => null,
                'moderated_at' => null,
                'moderator_note' => null,
            ]
        );

        return [
            'id' => $review->id,
            'status' => $review->status,
            'message' => 'Thank you! Your review was submitted and is awaiting approval.',
        ];
    }

    public function listApproved(int $businessId, int $productId, int $perPage = 10): LengthAwarePaginator
    {
        $paginator = ProductReview::query()
            ->where('business_id', $businessId)
            ->where('product_id', $productId)
            ->where('status', ProductReview::STATUS_APPROVED)
            ->with(['contact:id,name,first_name,last_name'])
            ->orderByDesc('moderated_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        $paginator->getCollection()->transform(fn (ProductReview $review) => $this->formatPublicReview($review));

        return $paginator;
    }

    /**
     * @return array{average: float, count: int}
     */
    public function ratingSummaryForProduct(Product $product): array
    {
        return [
            'average' => round((float) ($product->storefront_rating_avg ?? 0), 2),
            'count' => (int) ($product->storefront_rating_count ?? 0),
        ];
    }

    public function recomputeProductRating(int $productId): void
    {
        $stats = ProductReview::query()
            ->where('product_id', $productId)
            ->where('status', ProductReview::STATUS_APPROVED)
            ->selectRaw('COUNT(*) as cnt, COALESCE(AVG(rating), 0) as avg_rating')
            ->first();

        Product::where('id', $productId)->update([
            'storefront_rating_avg' => round((float) ($stats->avg_rating ?? 0), 2),
            'storefront_rating_count' => (int) ($stats->cnt ?? 0),
        ]);
    }

    public function approve(ProductReview $review, int $moderatorUserId, ?string $note = null): ProductReview
    {
        $review->status = ProductReview::STATUS_APPROVED;
        $review->moderated_by = $moderatorUserId;
        $review->moderated_at = now();
        $review->moderator_note = $note;
        $review->save();

        $this->recomputeProductRating((int) $review->product_id);

        return $review;
    }

    public function reject(ProductReview $review, int $moderatorUserId, ?string $note = null): ProductReview
    {
        $wasApproved = $review->isApproved();
        $review->status = ProductReview::STATUS_REJECTED;
        $review->moderated_by = $moderatorUserId;
        $review->moderated_at = now();
        $review->moderator_note = $note;
        $review->save();

        if ($wasApproved) {
            $this->recomputeProductRating((int) $review->product_id);
        }

        return $review;
    }

    public function adminListQuery(int $businessId): Builder
    {
        return ProductReview::query()
            ->where('product_reviews.business_id', $businessId)
            ->leftJoin('products', 'products.id', '=', 'product_reviews.product_id')
            ->leftJoin('contacts', 'contacts.id', '=', 'product_reviews.contact_id')
            ->select([
                'product_reviews.*',
                'products.name as product_name',
                'contacts.name as contact_name',
                'contacts.mobile as contact_mobile',
                'contacts.email as contact_email',
            ]);
    }

    /**
     * Resolve catalog product id from id or slug for the selling locations.
     */
    public function resolveProductId(
        int $businessId,
        string $idOrSlug,
        string $locale = \App\Support\StorefrontLocale::DEFAULT
    ): ?int {
        $detail = $this->catalog->findProduct($businessId, $idOrSlug, null, $locale);
        if (empty($detail)) {
            return null;
        }

        return (int) $detail['id'];
    }

    /**
     * @return array<string, mixed>
     */
    public function formatPublicReview(ProductReview $review): array
    {
        $contact = $review->contact;
        $displayName = $contact?->name
            ?: trim(($contact->first_name ?? '').' '.($contact->last_name ?? ''))
            ?: 'Customer';

        return [
            'id' => $review->id,
            'rating' => (int) $review->rating,
            'title' => $review->title,
            'body' => $review->body,
            'is_verified_purchase' => (bool) $review->is_verified_purchase,
            'author_name' => $this->maskAuthorName($displayName),
            'created_at' => optional($review->created_at)?->toIso8601String(),
            'moderated_at' => optional($review->moderated_at)?->toIso8601String(),
        ];
    }

    private function productExistsForBusiness(int $businessId, int $productId): bool
    {
        $locationIds = $this->storefrontSettings->getSellingLocationIds($businessId);
        if ($locationIds === []) {
            return false;
        }

        return Product::query()
            ->where('business_id', $businessId)
            ->where('id', $productId)
            ->active()
            ->where('not_for_selling', 0)
            ->whereHas('product_locations', fn ($q) => $q->whereIn('product_locations.location_id', $locationIds))
            ->exists();
    }

    private function maskAuthorName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return 'Customer';
        }

        $parts = preg_split('/\s+/', $name) ?: [$name];
        $first = $parts[0];
        if (count($parts) === 1) {
            return mb_substr($first, 0, 1).str_repeat('*', max(0, mb_strlen($first) - 1));
        }

        return $first.' '.mb_strtoupper(mb_substr($parts[count($parts) - 1], 0, 1)).'.';
    }
}
