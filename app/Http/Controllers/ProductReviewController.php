<?php

namespace App\Http\Controllers;

use App\ProductReview;
use App\Services\Storefront\ProductReviewService;
use App\Utils\Util;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

/**
 * POS admin moderation for storefront product reviews.
 */
class ProductReviewController extends Controller
{
    public function __construct(
        private Util $commonUtil,
        private ProductReviewService $reviews
    ) {
    }

    public function index()
    {
        if (! auth()->user()->can('product_review.access')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $businessId = (int) request()->session()->get('user.business_id');
            $query = $this->reviews->adminListQuery($businessId);

            if (request()->filled('status') && request('status') !== 'all') {
                $query->where('product_reviews.status', request('status'));
            }

            return DataTables::of($query)
                ->addColumn('action', function ($row) {
                    if (! auth()->user()->can('product_review.moderate')) {
                        return '';
                    }

                    $html = '';
                    if ($row->status !== ProductReview::STATUS_APPROVED) {
                        $html .= '<button type="button" data-href="'.action([self::class, 'approve'], [$row->id]).'" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-success review-approve-btn"><i class="fa fa-check"></i> Approve</button> ';
                    }
                    if ($row->status !== ProductReview::STATUS_REJECTED) {
                        $html .= '<button type="button" data-href="'.action([self::class, 'reject'], [$row->id]).'" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-error review-reject-btn"><i class="fa fa-times"></i> Reject</button>';
                    }

                    return $html;
                })
                ->editColumn('rating', fn ($row) => (int) $row->rating.' / 5')
                ->editColumn('status', function ($row) {
                    $label = match ($row->status) {
                        ProductReview::STATUS_APPROVED => 'bg-green',
                        ProductReview::STATUS_REJECTED => 'bg-red',
                        default => 'bg-yellow',
                    };

                    return '<span class="label '.$label.'">'.e(ucfirst($row->status)).'</span>';
                })
                ->editColumn('body', function ($row) {
                    $body = e(\Illuminate\Support\Str::limit((string) $row->body, 120));
                    $title = $row->title ? '<strong>'.e($row->title).'</strong><br>' : '';

                    return $title.$body;
                })
                ->editColumn('contact_name', function ($row) {
                    $name = e($row->contact_name ?: '—');
                    $meta = trim(($row->contact_email ?: '').' '.($row->contact_mobile ?: ''));

                    return $meta !== '' ? $name.'<br><small class="text-muted">'.e($meta).'</small>' : $name;
                })
                ->editColumn('created_at', function ($row) {
                    return $this->commonUtil->format_date($row->created_at, true);
                })
                ->rawColumns(['action', 'status', 'body', 'contact_name', 'product_name'])
                ->make(true);
        }

        return view('product_reviews.index');
    }

    public function approve(Request $request, int $id)
    {
        if (! auth()->user()->can('product_review.moderate')) {
            abort(403, 'Unauthorized action.');
        }

        $businessId = (int) $request->session()->get('user.business_id');
        $review = ProductReview::where('business_id', $businessId)->findOrFail($id);
        $this->reviews->approve($review, (int) auth()->id(), $request->input('note'));

        return response()->json([
            'success' => true,
            'msg' => 'Review approved.',
        ]);
    }

    public function reject(Request $request, int $id)
    {
        if (! auth()->user()->can('product_review.moderate')) {
            abort(403, 'Unauthorized action.');
        }

        $businessId = (int) $request->session()->get('user.business_id');
        $review = ProductReview::where('business_id', $businessId)->findOrFail($id);
        $this->reviews->reject($review, (int) auth()->id(), $request->input('note'));

        return response()->json([
            'success' => true,
            'msg' => 'Review rejected.',
        ]);
    }
}
