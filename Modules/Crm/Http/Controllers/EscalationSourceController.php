<?php

namespace Modules\Crm\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Crm\Entities\EscalationSource;
use Yajra\DataTables\Facades\DataTables;

class EscalationSourceController extends Controller
{
    protected $moduleUtil;

    public function __construct(ModuleUtil $moduleUtil)
    {
        $this->moduleUtil = $moduleUtil;
    }

    /**
     * Authorize source management.
     */
    protected function authorizeManage()
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin')
            || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'crm_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if (! auth()->user()->can('crm.escalation.manage_sources')) {
            abort(403, 'Unauthorized action.');
        }
    }

    /**
     * Display escalation sources list.
     */
    public function index()
    {
        $this->authorizeManage();

        $business_id = request()->session()->get('user.business_id');

        if (request()->ajax()) {
            $sources = EscalationSource::where('business_id', $business_id);

            return Datatables::of($sources)
                ->addColumn('is_active', function ($row) {
                    return $row->is_active
                        ? '<span class="label bg-green">'.__('business.is_active').'</span>'
                        : '<span class="label bg-red">'.__('lang_v1.inactive').'</span>';
                })
                ->addColumn('action', function ($row) {
                    $html = '<button data-href="'.action([self::class, 'edit'], [$row->id]).'" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary btn-modal" data-container=".escalation_source_modal"><i class="fa fa-edit"></i></button>';
                    $html .= ' <button data-href="'.action([self::class, 'destroy'], [$row->id]).'" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-error delete_escalation_source_button"><i class="fa fa-trash"></i></button>';

                    return $html;
                })
                ->rawColumns(['is_active', 'action'])
                ->make(true);
        }

        return view('crm::escalation_sources.index');
    }

    /**
     * Show create form (modal).
     */
    public function create()
    {
        $this->authorizeManage();

        return view('crm::escalation_sources.create');
    }

    /**
     * Store a new escalation source.
     */
    public function store(Request $request)
    {
        $this->authorizeManage();

        $business_id = request()->session()->get('user.business_id');

        try {
            $input = $request->only(['name', 'is_active']);
            $input['business_id'] = $business_id;
            $input['created_by'] = auth()->user()->id;
            $input['is_active'] = ! empty($input['is_active']) ? 1 : 0;

            EscalationSource::create($input);

            $output = ['success' => true, 'msg' => __('crm::lang.escalation_source_added_success')];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = ['success' => false, 'msg' => __('messages.something_went_wrong')];
        }

        return $output;
    }

    /**
     * Show edit form (modal).
     */
    public function edit($id)
    {
        $this->authorizeManage();

        $business_id = request()->session()->get('user.business_id');
        $source = EscalationSource::where('business_id', $business_id)->findOrFail($id);

        return view('crm::escalation_sources.edit')
            ->with(compact('source'));
    }

    /**
     * Update escalation source.
     */
    public function update(Request $request, $id)
    {
        $this->authorizeManage();

        $business_id = request()->session()->get('user.business_id');

        try {
            $source = EscalationSource::where('business_id', $business_id)->findOrFail($id);

            $input = $request->only(['name', 'is_active']);
            $input['is_active'] = ! empty($input['is_active']) ? 1 : 0;

            $source->update($input);

            $output = ['success' => true, 'msg' => __('crm::lang.escalation_source_updated_success')];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = ['success' => false, 'msg' => __('messages.something_went_wrong')];
        }

        return $output;
    }

    /**
     * Delete escalation source.
     */
    public function destroy($id)
    {
        $this->authorizeManage();

        $business_id = request()->session()->get('user.business_id');

        try {
            $source = EscalationSource::where('business_id', $business_id)->findOrFail($id);
            $source->delete();

            $output = ['success' => true, 'msg' => __('crm::lang.escalation_source_deleted_success')];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = ['success' => false, 'msg' => __('messages.something_went_wrong')];
        }

        return $output;
    }
}
