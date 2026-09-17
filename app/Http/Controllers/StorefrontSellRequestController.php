<?php

namespace App\Http\Controllers;

use App\StorefrontSellRequest;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

/**
 * POS admin list/detail for storefront Sell to us trade-in requests.
 */
class StorefrontSellRequestController extends Controller
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
            $query = StorefrontSellRequest::query()
                ->where('business_id', $businessId)
                ->with(['contact:id,name,email,mobile'])
                ->select('storefront_sell_requests.*');

            if (request()->filled('status') && request('status') !== 'all') {
                $query->where('status', request('status'));
            }
            if (request()->filled('type') && request('type') !== 'all') {
                $query->where('type', request('type'));
            }

            return DataTables::of($query)
                ->addColumn('action', function ($row) {
                    $url = action([self::class, 'show'], [$row->id]);

                    return '<a href="'.e($url).'" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-info"><i class="fa fa-eye"></i> View</a>';
                })
                ->editColumn('type', fn ($row) => e(ucfirst((string) $row->type)))
                ->editColumn('status', function ($row) {
                    $label = match ($row->status) {
                        StorefrontSellRequest::STATUS_ACCEPTED => 'bg-green',
                        StorefrontSellRequest::STATUS_REJECTED => 'bg-red',
                        StorefrontSellRequest::STATUS_CONTACTED => 'bg-blue',
                        StorefrontSellRequest::STATUS_CLOSED => 'bg-gray',
                        default => 'bg-yellow',
                    };

                    return '<span class="label '.$label.'">'.e(ucfirst((string) $row->status)).'</span>';
                })
                ->editColumn('purchased_from_us', fn ($row) => $row->purchased_from_us ? 'Yes' : 'No')
                ->editColumn('created_at', function ($row) {
                    return $this->commonUtil->format_date($row->created_at, true);
                })
                ->rawColumns(['action', 'status'])
                ->make(true);
        }

        return view('storefront.sell_requests.index');
    }

    public function show(int $id)
    {
        if (! auth()->user()->can('storefront.settings')) {
            abort(403, 'Unauthorized action.');
        }

        $businessId = (int) request()->session()->get('user.business_id');
        $request = StorefrontSellRequest::where('business_id', $businessId)
            ->with(['media', 'contact', 'transaction'])
            ->findOrFail($id);

        return view('storefront.sell_requests.show', [
            'sellRequest' => $request,
            'statuses' => StorefrontSellRequest::STATUSES,
        ]);
    }

    public function updateStatus(Request $request, int $id)
    {
        if (! auth()->user()->can('storefront.settings')) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(StorefrontSellRequest::STATUSES)],
        ]);

        $businessId = (int) $request->session()->get('user.business_id');
        $row = StorefrontSellRequest::where('business_id', $businessId)->findOrFail($id);
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
