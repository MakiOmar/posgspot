<?php

namespace App\Http\Controllers;

use App\StorefrontProductRequest;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

/**
 * POS admin list/detail for storefront product requests.
 */
class StorefrontProductRequestController extends Controller
{
    public function __construct(private Util $commonUtil)
    {
    }

    public function index()
    {
        if (! auth()->user()->can('storefront.settings')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $businessId = (int) request()->session()->get('user.business_id');
            $query = StorefrontProductRequest::query()
                ->where('business_id', $businessId)
                ->with(['contact:id,name,email,mobile'])
                ->select('storefront_product_requests.*');

            if (request()->filled('status') && request('status') !== 'all') {
                $query->where('status', request('status'));
            }

            return DataTables::of($query)
                ->addColumn('action', function ($row) {
                    $url = action([self::class, 'show'], [$row->id]);

                    return '<a href="'.e($url).'" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-info"><i class="fa fa-eye"></i> View</a>';
                })
                ->editColumn('platform', fn ($row) => $row->platform ? e(strtoupper((string) $row->platform)) : '—')
                ->editColumn('status', function ($row) {
                    $label = match ($row->status) {
                        StorefrontProductRequest::STATUS_FULFILLED => 'bg-green',
                        StorefrontProductRequest::STATUS_CLOSED => 'bg-gray',
                        StorefrontProductRequest::STATUS_CONTACTED => 'bg-blue',
                        default => 'bg-yellow',
                    };

                    return '<span class="label '.$label.'">'.e(ucfirst((string) $row->status)).'</span>';
                })
                ->editColumn('created_at', fn ($row) => $this->commonUtil->format_date($row->created_at, true))
                ->rawColumns(['action', 'status'])
                ->make(true);
        }

        return view('storefront.product_requests.index');
    }

    public function show(int $id)
    {
        if (! auth()->user()->can('storefront.settings')) {
            abort(403, 'Unauthorized action.');
        }

        $businessId = (int) request()->session()->get('user.business_id');
        $request = StorefrontProductRequest::where('business_id', $businessId)
            ->with('contact')
            ->findOrFail($id);

        return view('storefront.product_requests.show', [
            'productRequest' => $request,
            'statuses' => StorefrontProductRequest::STATUSES,
        ]);
    }

    public function updateStatus(Request $request, int $id)
    {
        if (! auth()->user()->can('storefront.settings')) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(StorefrontProductRequest::STATUSES)],
        ]);

        $businessId = (int) $request->session()->get('user.business_id');
        $row = StorefrontProductRequest::where('business_id', $businessId)->findOrFail($id);
        $row->status = $validated['status'];
        $row->save();

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'msg' => 'Status updated.',
                'status' => $row->status,
            ]);
        }

        return redirect()
            ->action([self::class, 'show'], [$row->id])
            ->with('status', ['success' => 1, 'msg' => 'Status updated.']);
    }
}
