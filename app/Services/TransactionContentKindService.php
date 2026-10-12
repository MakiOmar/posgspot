<?php

namespace App\Services;

use App\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Tags sells / sales orders by what they contain so listings can filter Accounts orders:
 * accounts_only (every line is a game account / card), mixed, or pos_only.
 */
class TransactionContentKindService
{
    public const ACCOUNTS_ONLY = 'accounts_only';

    public const MIXED = 'mixed';

    public const POS_ONLY = 'pos_only';

    public const KINDS = [self::ACCOUNTS_ONLY, self::MIXED, self::POS_ONLY];

    /** Transaction types that carry sell lines and appear in the sales listings. */
    public const TYPES = ['sell', 'sales_order'];

    private ?bool $hasColumn = null;

    /**
     * Recompute and store the kind for one transaction (never throws: tagging is non-critical).
     */
    public function refresh(int $transactionId): ?string
    {
        if (! $this->hasColumn()) {
            return null;
        }

        try {
            $counts = $this->countsQuery()
                ->where('tsl.transaction_id', $transactionId)
                ->first();
            $kind = self::classify((int) ($counts->total ?? 0), (int) ($counts->accounts ?? 0));

            // toBase(): tagging must not bump updated_at.
            Transaction::whereKey($transactionId)
                ->whereIn('type', self::TYPES)
                ->toBase()
                ->update(['content_kind' => $kind]);

            return $kind;
        } catch (\Throwable $e) {
            Log::warning('Content kind refresh failed: '.$e->getMessage(), ['transaction_id' => $transactionId]);

            return null;
        }
    }

    /**
     * Classify many transactions with one grouped query per chunk.
     *
     * @param  list<int>  $transactionIds
     */
    public function refreshMany(array $transactionIds): int
    {
        if (! $this->hasColumn() || $transactionIds === []) {
            return 0;
        }

        $counts = $this->countsQuery()
            ->whereIn('tsl.transaction_id', $transactionIds)
            ->groupBy('tsl.transaction_id')
            ->addSelect('tsl.transaction_id')
            ->get()
            ->keyBy('transaction_id');

        $byKind = [];
        foreach ($transactionIds as $id) {
            $row = $counts->get($id);
            $kind = self::classify((int) ($row->total ?? 0), (int) ($row->accounts ?? 0));
            $byKind[$kind][] = $id;
        }

        $updated = 0;
        foreach ($byKind as $kind => $ids) {
            $updated += Transaction::whereIn('id', $ids)->toBase()->update(['content_kind' => $kind]);
        }

        return $updated;
    }

    public static function classify(int $totalLines, int $accountLines): string
    {
        if ($accountLines <= 0) {
            return self::POS_ONLY;
        }

        return $accountLines >= $totalLines ? self::ACCOUNTS_ONLY : self::MIXED;
    }

    /**
     * Total / account parent-line counts. An account line is a synced ACCOUNTS-* SKU, a legacy
     * Accounts placeholder SKU, or one of the configured legacy product ids.
     */
    private function countsQuery()
    {
        $bindings = [];
        $conditions = ["p.sku LIKE 'ACCOUNTS-GAME-%'", "p.sku LIKE 'ACCOUNTS-CARD-%'"];

        $legacySkus = (array) config('services.accounts.legacy_pos_skus', []);
        if ($legacySkus !== []) {
            $conditions[] = 'p.sku IN ('.implode(',', array_fill(0, count($legacySkus), '?')).')';
            $bindings = array_merge($bindings, array_values($legacySkus));
        }
        $legacyIds = array_map('intval', (array) config('services.accounts.legacy_product_ids', []));
        if ($legacyIds !== []) {
            $conditions[] = 'p.id IN ('.implode(',', array_fill(0, count($legacyIds), '?')).')';
            $bindings = array_merge($bindings, $legacyIds);
        }

        return DB::table('transaction_sell_lines as tsl')
            ->join('products as p', 'p.id', '=', 'tsl.product_id')
            ->whereNull('tsl.parent_sell_line_id')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN '.implode(' OR ', $conditions).' THEN 1 ELSE 0 END) as accounts', $bindings);
    }

    private function hasColumn(): bool
    {
        return $this->hasColumn ??= Schema::hasColumn('transactions', 'content_kind');
    }
}
