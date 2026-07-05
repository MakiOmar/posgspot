<?php

namespace App\Http\Controllers;

use App\Coupon;
use App\Services\CouponAdminService;
use App\Utils\Util;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

/**
 * POS admin CRUD for storefront promo codes (separate from automatic discounts).
 */
class CouponController extends Controller
{
    public function __construct(
        private Util $commonUtil,
        private CouponAdminService $couponAdmin
    ) {
    }

    public function index()
    {
        if (! auth()->user()->can('coupon.access')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $businessId = (int) request()->session()->get('user.business_id');
            $query = $this->couponAdmin->listQuery($businessId);

            return DataTables::of($query)
                ->addColumn('action', function ($row) {
                    $html = '';
                    if (auth()->user()->can('coupon.create')) {
                        $html .= '<a href="'.action([self::class, 'edit'], [$row->id]).'" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary"><i class="glyphicon glyphicon-edit"></i> '.__('messages.edit').'</a> ';
                        $html .= '<a href="'.action([self::class, 'duplicate'], [$row->id]).'" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-accent"><i class="fa fa-copy"></i> Duplicate</a> ';
                    }
                    if (auth()->user()->can('coupon.delete')) {
                        $html .= '<button data-href="'.action([self::class, 'destroy'], [$row->id]).'" class="tw-dw-btn tw-dw-btn-outline tw-dw-btn-xs tw-dw-btn-error delete_coupon_button"><i class="glyphicon glyphicon-trash"></i> '.__('messages.delete').'</button> ';
                    }
                    if (! $row->is_active && auth()->user()->can('coupon.create')) {
                        $html .= '<button data-href="'.action([self::class, 'activate'], [$row->id]).'" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-accent activate-coupon"><i class="fa fa-circle-o"></i> '.__('lang_v1.reactivate').'</button>';
                    }

                    return $html;
                })
                ->editColumn('name', function ($row) {
                    return $row->is_active
                        ? e($row->name)
                        : e($row->name).' <span class="label bg-yellow">'.__('lang_v1.inactive').'</span>';
                })
                ->editColumn('type', fn ($row) => ucwords(str_replace('_', ' ', $row->type)))
                ->editColumn('discount_amount', function ($row) {
                    if ($row->type === Coupon::TYPE_PERCENT_ORDER) {
                        return @num_format($row->discount_amount).' %';
                    }
                    if ($row->type === Coupon::TYPE_FREE_SHIPPING) {
                        return '—';
                    }

                    return @num_format($row->discount_amount);
                })
                ->editColumn('usage', fn ($row) => (int) $row->times_used.'/'.($row->max_uses_total ?: '∞'))
                ->editColumn('starts_at', function ($row) {
                    return ! empty($row->starts_at)
                        ? $this->commonUtil->format_date($row->starts_at->toDateTimeString(), true)
                        : '—';
                })
                ->editColumn('ends_at', function ($row) {
                    return ! empty($row->ends_at)
                        ? $this->commonUtil->format_date($row->ends_at->toDateTimeString(), true)
                        : '—';
                })
                ->rawColumns(['name', 'action'])
                ->make(true);
        }

        return view('coupons.index');
    }

    public function create()
    {
        if (! auth()->user()->can('coupon.create')) {
            abort(403, 'Unauthorized action.');
        }

        $businessId = (int) request()->session()->get('user.business_id');
        $options = $this->couponAdmin->formOptions($businessId);

        return view('coupons.partials.form', array_merge($options, [
            'action' => action([self::class, 'store']),
            'method' => 'post',
            'title' => 'Add promo code',
            'coupon' => null,
            'starts_at' => null,
            'ends_at' => null,
        ]));
    }

    public function store(Request $request)
    {
        if (! auth()->user()->can('coupon.create')) {
            abort(403, 'Unauthorized action.');
        }

        $businessId = (int) $request->session()->get('user.business_id');

        try {
            $this->couponAdmin->create($businessId, $this->normalizeInput($request));

            return redirect()
                ->action([self::class, 'index'])
                ->with('status', ['success' => 1, 'msg' => __('lang_v1.added_success')]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withInput()->withErrors($e->errors());
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            return redirect()->back()->withInput()->with('status', [
                'success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ]);
        }
    }

    public function edit($id)
    {
        if (! auth()->user()->can('coupon.create')) {
            abort(403, 'Unauthorized action.');
        }

        $businessId = (int) request()->session()->get('user.business_id');
        $coupon = Coupon::where('business_id', $businessId)
            ->with(['categories', 'variations'])
            ->findOrFail($id);

        $options = $this->couponAdmin->formOptions($businessId);

        return view('coupons.partials.form', array_merge($options, [
            'action' => action([self::class, 'update'], [$coupon->id]),
            'method' => 'PUT',
            'title' => 'Edit promo code',
            'coupon' => $coupon,
            'starts_at' => $coupon->starts_at
                ? $this->commonUtil->format_date($coupon->starts_at->toDateTimeString(), true)
                : null,
            'ends_at' => $coupon->ends_at
                ? $this->commonUtil->format_date($coupon->ends_at->toDateTimeString(), true)
                : null,
        ]));
    }

    public function update(Request $request, $id)
    {
        if (! auth()->user()->can('coupon.create')) {
            abort(403, 'Unauthorized action.');
        }

        $businessId = (int) $request->session()->get('user.business_id');
        $coupon = Coupon::where('business_id', $businessId)->findOrFail($id);

        try {
            $this->couponAdmin->update($coupon, $this->normalizeInput($request));

            return redirect()
                ->action([self::class, 'index'])
                ->with('status', ['success' => 1, 'msg' => __('lang_v1.updated_success')]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withInput()->withErrors($e->errors());
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            return redirect()->back()->withInput()->with('status', [
                'success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ]);
        }
    }

    public function destroy($id)
    {
        if (! auth()->user()->can('coupon.delete')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $businessId = (int) request()->session()->get('user.business_id');
            $coupon = Coupon::where('business_id', $businessId)->findOrFail($id);
            $coupon->delete();

            return ['success' => true, 'msg' => __('lang_v1.deleted_success')];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            return ['success' => false, 'msg' => __('messages.something_went_wrong')];
        }
    }

    public function activate($id)
    {
        if (! auth()->user()->can('coupon.create')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $businessId = (int) request()->session()->get('user.business_id');
            $coupon = Coupon::where('business_id', $businessId)->findOrFail($id);
            $coupon->is_active = true;
            $coupon->save();

            return ['success' => true, 'msg' => __('lang_v1.updated_success')];
        } catch (\Exception $e) {
            return ['success' => false, 'msg' => __('messages.something_went_wrong')];
        }
    }

    public function duplicate($id)
    {
        if (! auth()->user()->can('coupon.create')) {
            abort(403, 'Unauthorized action.');
        }

        $businessId = (int) request()->session()->get('user.business_id');
        $coupon = Coupon::where('business_id', $businessId)->findOrFail($id);
        $copy = $this->couponAdmin->duplicate($coupon);

        return redirect()
            ->action([self::class, 'edit'], [$copy->id])
            ->with('status', ['success' => 1, 'msg' => 'Promo code duplicated. Review and activate when ready.']);
    }

    private function normalizeInput(Request $request): array
    {
        $data = $request->only([
            'code', 'name', 'description', 'type', 'discount_amount', 'max_discount_amount',
            'min_order_subtotal', 'channel', 'max_uses_total', 'max_uses_per_customer',
            'applies_to', 'category_ids', 'variation_ids',
        ]);

        $data['starts_at'] = $request->filled('starts_at')
            ? $this->commonUtil->uf_date($request->input('starts_at'), true)
            : null;
        $data['ends_at'] = $request->filled('ends_at')
            ? $this->commonUtil->uf_date($request->input('ends_at'), true)
            : null;
        $data['is_active'] = $request->has('is_active');
        $data['first_order_only'] = $request->has('first_order_only');
        $data['exclude_sale_items'] = $request->has('exclude_sale_items');
        $data['stack_with_reward_points'] = $request->has('stack_with_reward_points');

        return $data;
    }
}
