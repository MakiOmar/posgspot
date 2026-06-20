<?php

namespace Modules\Crm\Http\Controllers;

use App\BusinessLocation;
use App\Contact;
use App\Transaction;
use App\User;
use App\Utils\ModuleUtil;
use App\Utils\Util;
use DB;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Crm\Entities\Escalation;
use Modules\Crm\Entities\EscalationSource;
use Modules\Crm\Entities\EscalationStatusLog;
use Modules\Crm\Utils\EscalationUtil;
use Yajra\DataTables\Facades\DataTables;

class EscalationController extends Controller
{
    protected $commonUtil;

    protected $moduleUtil;

    protected $escalationUtil;

    public function __construct(Util $commonUtil, ModuleUtil $moduleUtil, EscalationUtil $escalationUtil)
    {
        $this->commonUtil = $commonUtil;
        $this->moduleUtil = $moduleUtil;
        $this->escalationUtil = $escalationUtil;
    }

    /**
     * Check CRM module and view permissions.
     */
    protected function authorizeView()
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin')
            || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'crm_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if (! auth()->user()->can('crm.escalation.view_all')
            && ! auth()->user()->can('crm.escalation.view_own')) {
            abort(403, 'Unauthorized action.');
        }
    }

    /**
     * Check create permission.
     */
    protected function authorizeCreate()
    {
        $this->authorizeView();

        if (! auth()->user()->can('crm.escalation.create')) {
            abort(403, 'Unauthorized action.');
        }
    }

    /**
     * Check update permission for a specific escalation.
     */
    protected function authorizeUpdate(Escalation $escalation)
    {
        $this->authorizeView();

        if ($escalation->isClosed() && ! auth()->user()->can('crm.escalation.update_all')) {
            abort(403, 'Unauthorized action.');
        }

        if (! $this->escalationUtil->canUserUpdate($escalation)) {
            abort(403, 'Unauthorized action.');
        }
    }

    /**
     * Display a listing of escalations.
     */
    public function index()
    {
        $this->authorizeView();

        $business_id = request()->session()->get('user.business_id');
        $user_id = auth()->user()->id;

        if (request()->ajax()) {
            $query = Escalation::where('crm_escalations.business_id', $business_id)
                ->leftJoin('contacts as c', 'crm_escalations.contact_id', '=', 'c.id')
                ->leftJoin('crm_escalation_sources as src', 'crm_escalations.source_id', '=', 'src.id')
                ->leftJoin('business_locations as bl', 'crm_escalations.location_id', '=', 'bl.id')
                ->leftJoin('transactions as t', 'crm_escalations.transaction_id', '=', 't.id')
                ->leftJoin('users as emp', 'crm_escalations.employee_id', '=', 'emp.id')
                ->leftJoin('users as obs', 'crm_escalations.observer_id', '=', 'obs.id')
                ->leftJoin('users as aud', 'crm_escalations.auditor_id', '=', 'aud.id')
                ->select(
                    'crm_escalations.*',
                    'c.name as customer_name',
                    'c.supplier_business_name',
                    'src.name as source_name',
                    'bl.name as location_name',
                    't.invoice_no',
                    DB::raw("CONCAT(COALESCE(emp.surname, ''), ' ', COALESCE(emp.first_name, ''), ' ', COALESCE(emp.last_name, '')) as employee_name"),
                    DB::raw("CONCAT(COALESCE(obs.surname, ''), ' ', COALESCE(obs.first_name, ''), ' ', COALESCE(obs.last_name, '')) as observer_name"),
                    DB::raw("CONCAT(COALESCE(aud.surname, ''), ' ', COALESCE(aud.first_name, ''), ' ', COALESCE(aud.last_name, '')) as auditor_name")
                );

            if (! auth()->user()->can('superadmin') && ! auth()->user()->can('crm.escalation.view_all')) {
                $query->where(function ($q) use ($user_id) {
                    $q->where('crm_escalations.employee_id', $user_id)
                        ->orWhere('crm_escalations.observer_id', $user_id)
                        ->orWhere('crm_escalations.auditor_id', $user_id)
                        ->orWhere('crm_escalations.created_by', $user_id);
                });
            }

            if (! empty(request()->input('contact_id'))) {
                $query->where('crm_escalations.contact_id', request()->input('contact_id'));
            }

            if (! empty(request()->input('employee_id'))) {
                $query->where('crm_escalations.employee_id', request()->input('employee_id'));
            }

            if (! empty(request()->input('observer_id'))) {
                $query->where('crm_escalations.observer_id', request()->input('observer_id'));
            }

            if (! empty(request()->input('auditor_id'))) {
                $query->where('crm_escalations.auditor_id', request()->input('auditor_id'));
            }

            if (! empty(request()->input('location_id'))) {
                $query->where('crm_escalations.location_id', request()->input('location_id'));
            }

            if (! empty(request()->input('source_id'))) {
                $query->where('crm_escalations.source_id', request()->input('source_id'));
            }

            if (! empty(request()->input('status'))) {
                $query->where('crm_escalations.status', request()->input('status'));
            }

            if (! empty(request()->input('callbacks_due'))) {
                $query->whereNotNull('crm_escalations.callback_at')
                    ->where('crm_escalations.callback_at', '<=', now())
                    ->whereNotIn('crm_escalations.status', ['closed', 'cancelled']);
            }

            if (! empty(request()->input('start_date')) && ! empty(request()->input('end_date'))) {
                $query->whereDate('crm_escalations.escalated_at', '>=', request()->input('start_date'))
                    ->whereDate('crm_escalations.escalated_at', '<=', request()->input('end_date'));
            }

            return Datatables::of($query)
                ->addColumn('customer', function ($row) {
                    $name = $row->customer_name;
                    if (! empty($row->supplier_business_name)) {
                        $name = $row->supplier_business_name.'<br>'.$name;
                    }

                    return $name;
                })
                ->editColumn('escalated_at', '{{@format_datetime($escalated_at)}}')
                ->editColumn('callback_at', '@if(!empty($callback_at)) {{@format_datetime($callback_at)}} @endif')
                ->editColumn('status', function ($row) {
                    $statuses = Escalation::statusDropdown();

                    return $statuses[$row->status] ?? $row->status;
                })
                ->addColumn('action', function ($row) {
                    $html = '<div class="btn-group">';
                    $html .= '<a href="'.action([self::class, 'show'], [$row->id]).'" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-info"><i class="fa fa-eye"></i></a>';

                    if ($this->escalationUtil->canUserUpdate($row)) {
                        $html .= ' <a href="'.action([self::class, 'edit'], [$row->id]).'" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary"><i class="fa fa-edit"></i></a>';
                    }

                    if (auth()->user()->can('crm.escalation.delete')) {
                        $html .= ' <button data-href="'.action([self::class, 'destroy'], [$row->id]).'" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-error delete_escalation_button"><i class="fa fa-trash"></i></button>';
                    }

                    $html .= '</div>';

                    return $html;
                })
                ->rawColumns(['customer', 'action', 'callback_at'])
                ->filterColumn('employee_name', function ($query, $keyword) {
                    $query->whereRaw("CONCAT(COALESCE(emp.surname, ''), ' ', COALESCE(emp.first_name, ''), ' ', COALESCE(emp.last_name, '')) like ?", ["%{$keyword}%"]);
                })
                ->filterColumn('observer_name', function ($query, $keyword) {
                    $query->whereRaw("CONCAT(COALESCE(obs.surname, ''), ' ', COALESCE(obs.first_name, ''), ' ', COALESCE(obs.last_name, '')) like ?", ["%{$keyword}%"]);
                })
                ->filterColumn('auditor_name', function ($query, $keyword) {
                    $query->whereRaw("CONCAT(COALESCE(aud.surname, ''), ' ', COALESCE(aud.first_name, ''), ' ', COALESCE(aud.last_name, '')) like ?", ["%{$keyword}%"]);
                })
                ->filterColumn('customer', function ($query, $keyword) {
                    $query->where(function ($q) use ($keyword) {
                        $q->where('c.name', 'like', "%{$keyword}%")
                            ->orWhere('c.supplier_business_name', 'like', "%{$keyword}%");
                    });
                })
                ->filterColumn('source_name', function ($query, $keyword) {
                    $query->where('src.name', 'like', "%{$keyword}%");
                })
                ->filterColumn('location_name', function ($query, $keyword) {
                    $query->where('bl.name', 'like', "%{$keyword}%");
                })
                ->filterColumn('invoice_no', function ($query, $keyword) {
                    $query->where('t.invoice_no', 'like', "%{$keyword}%");
                })
                ->make(true);
        }

        $this->escalationUtil->seedDefaultSources($business_id);

        $locations = BusinessLocation::forDropdown($business_id, true);
        $sources = EscalationSource::forDropdown($business_id, false);
        $statuses = Escalation::statusDropdown(true);
        $users = User::forDropdown($business_id, false);

        return view('crm::escalation.index')
            ->with(compact('locations', 'sources', 'statuses', 'users'));
    }

    /**
     * Show the form for creating a new escalation.
     */
    public function create()
    {
        $this->authorizeCreate();

        $business_id = request()->session()->get('user.business_id');
        $this->escalationUtil->seedDefaultSources($business_id);

        $locations = BusinessLocation::forDropdown($business_id);
        $sources = EscalationSource::forDropdown($business_id);
        $default_employee_id = auth()->user()->id;
        $default_escalated_at = $this->commonUtil->format_date(now(), true);

        return view('crm::escalation.create')
            ->with(compact('locations', 'sources', 'default_employee_id', 'default_escalated_at'));
    }

    /**
     * Store a newly created escalation.
     */
    public function store(Request $request)
    {
        $this->authorizeCreate();

        $business_id = request()->session()->get('user.business_id');

        try {
            $input = $request->only([
                'employee_id', 'contact_id', 'phone', 'escalated_at', 'description',
                'source_id', 'location_id', 'callback_at', 'transaction_id',
                'comment', 'observer_id', 'observer_comment', 'auditor_id',
            ]);

            $input['business_id'] = $business_id;
            $input['reference_no'] = $this->escalationUtil->generateReferenceNo($business_id);
            $input['created_by'] = auth()->user()->id;
            $input['updated_by'] = auth()->user()->id;
            $input['status'] = 'open';

            if (! empty($input['callback_at'])) {
                $input['status'] = 'callback_scheduled';
            } elseif (! empty($input['observer_id'])) {
                $input['status'] = 'in_review';
            }

            if (empty($input['escalated_at'])) {
                $input['escalated_at'] = $this->commonUtil->uf_date(now()->format('Y-m-d H:i'), true);
            } else {
                $input['escalated_at'] = $this->commonUtil->uf_date($input['escalated_at'], true);
            }

            if (! empty($input['callback_at'])) {
                $input['callback_at'] = $this->commonUtil->uf_date($input['callback_at'], true);
            } else {
                $input['callback_at'] = null;
            }

            $input['observer_id'] = ! empty($input['observer_id']) ? $input['observer_id'] : null;
            $input['auditor_id'] = ! empty($input['auditor_id']) ? $input['auditor_id'] : null;
            $input['transaction_id'] = ! empty($input['transaction_id']) ? $input['transaction_id'] : null;

            $escalation = Escalation::create($input);

            EscalationStatusLog::create([
                'escalation_id' => $escalation->id,
                'user_id' => auth()->user()->id,
                'from_status' => null,
                'to_status' => $input['status'],
                'note' => __('crm::lang.escalation_created'),
            ]);

            $output = ['success' => true, 'msg' => __('crm::lang.escalation_added_success')];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = ['success' => false, 'msg' => __('messages.something_went_wrong')];
        }

        return redirect()->action([self::class, 'index'])->with('status', $output);
    }

    /**
     * Display the specified escalation.
     */
    public function show($id)
    {
        $this->authorizeView();

        $business_id = request()->session()->get('user.business_id');

        $escalation = Escalation::with([
            'contact', 'employee', 'source', 'location', 'transaction',
            'observer', 'auditor', 'createdBy', 'statusLogs.user',
        ])
            ->where('business_id', $business_id)
            ->findOrFail($id);

        if (! $this->escalationUtil->canUserView($escalation)) {
            abort(403, 'Unauthorized action.');
        }

        $statuses = Escalation::statusDropdown();
        $can_update = $this->escalationUtil->canUserUpdate($escalation);

        return view('crm::escalation.show')
            ->with(compact('escalation', 'statuses', 'can_update'));
    }

    /**
     * Show the form for editing the specified escalation.
     */
    public function edit($id)
    {
        $this->authorizeView();

        $business_id = request()->session()->get('user.business_id');
        $this->escalationUtil->seedDefaultSources($business_id);

        $escalation = Escalation::where('business_id', $business_id)->findOrFail($id);
        $this->authorizeUpdate($escalation);

        $locations = BusinessLocation::forDropdown($business_id);
        $sources = EscalationSource::forDropdown($business_id);
        $escalated_at_formatted = $this->commonUtil->format_date($escalation->escalated_at, true);
        $callback_at_formatted = ! empty($escalation->callback_at)
            ? $this->commonUtil->format_date($escalation->callback_at, true)
            : null;

        return view('crm::escalation.edit')
            ->with(compact('escalation', 'locations', 'sources', 'escalated_at_formatted', 'callback_at_formatted'));
    }

    /**
     * Update the specified escalation.
     */
    public function update(Request $request, $id)
    {
        $this->authorizeView();

        $business_id = request()->session()->get('user.business_id');

        $escalation = Escalation::where('business_id', $business_id)->findOrFail($id);
        $this->authorizeUpdate($escalation);

        try {
            $input = $request->only([
                'employee_id', 'contact_id', 'phone', 'escalated_at', 'description',
                'source_id', 'location_id', 'callback_at', 'transaction_id',
                'comment', 'observer_id', 'observer_comment', 'auditor_id',
            ]);

            if (! auth()->user()->can('crm.escalation.assign_observer')) {
                unset($input['observer_id']);
            }

            if (! auth()->user()->can('crm.escalation.assign_auditor')) {
                unset($input['auditor_id']);
            }

            if (! empty($input['escalated_at'])) {
                $input['escalated_at'] = $this->commonUtil->uf_date($input['escalated_at'], true);
            }

            if (! empty($input['callback_at'])) {
                $input['callback_at'] = $this->commonUtil->uf_date($input['callback_at'], true);
            } else {
                $input['callback_at'] = null;
            }

            $input['observer_id'] = ! empty($input['observer_id']) ? $input['observer_id'] : null;
            $input['auditor_id'] = ! empty($input['auditor_id']) ? $input['auditor_id'] : null;
            $input['transaction_id'] = ! empty($input['transaction_id']) ? $input['transaction_id'] : null;

            $input['updated_by'] = auth()->user()->id;

            $old_status = $escalation->status;
            $escalation->update($input);

            if (! empty($input['callback_at']) && ! in_array($old_status, ['closed', 'cancelled', 'resolved'])) {
                $this->escalationUtil->changeStatus($escalation, 'callback_scheduled');
            } elseif (! empty($input['observer_id']) && $old_status === 'open') {
                $this->escalationUtil->changeStatus($escalation, 'in_review');
            }

            $output = ['success' => true, 'msg' => __('crm::lang.escalation_updated_success')];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = ['success' => false, 'msg' => __('messages.something_went_wrong')];
        }

        return redirect()->action([self::class, 'show'], [$id])->with('status', $output);
    }

    /**
     * Remove the specified escalation.
     */
    public function destroy($id)
    {
        if (! auth()->user()->can('crm.escalation.delete')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        try {
            $escalation = Escalation::where('business_id', $business_id)->findOrFail($id);
            $escalation->delete();

            $output = ['success' => true, 'msg' => __('crm::lang.escalation_deleted_success')];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = ['success' => false, 'msg' => __('messages.something_went_wrong')];
        }

        return $output;
    }

    /**
     * Update escalation status.
     */
    public function updateStatus(Request $request, $id)
    {
        $this->authorizeView();

        $business_id = request()->session()->get('user.business_id');
        $escalation = Escalation::where('business_id', $business_id)->findOrFail($id);

        if (! $this->escalationUtil->canUserView($escalation)) {
            abort(403, 'Unauthorized action.');
        }

        $to_status = $request->input('status');
        $note = $request->input('note');

        if ($to_status === 'closed' && ! auth()->user()->can('crm.escalation.close')) {
            abort(403, 'Unauthorized action.');
        }

        if ($to_status !== 'closed' && ! $this->escalationUtil->canUserUpdate($escalation)) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $this->escalationUtil->changeStatus($escalation, $to_status, $note);

            $output = ['success' => true, 'msg' => __('crm::lang.escalation_status_updated')];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = ['success' => false, 'msg' => __('messages.something_went_wrong')];
        }

        if ($request->ajax()) {
            return $output;
        }

        return redirect()->action([self::class, 'show'], [$id])->with('status', $output);
    }

    /**
     * AJAX search users for Select2.
     */
    public function searchUsers()
    {
        $this->authorizeView();

        if (! request()->ajax()) {
            abort(404);
        }

        $term = request()->input('q', '');
        if (empty($term)) {
            return json_encode([]);
        }

        $business_id = request()->session()->get('user.business_id');

        $users = User::where('business_id', $business_id)
            ->user()
            ->where('is_cmmsn_agnt', 0)
            ->where(function ($query) use ($term) {
                $query->where('first_name', 'like', '%'.$term.'%')
                    ->orWhere('last_name', 'like', '%'.$term.'%')
                    ->orWhere('surname', 'like', '%'.$term.'%')
                    ->orWhere('username', 'like', '%'.$term.'%');
            })
            ->select(
                'id',
                DB::raw("CONCAT(COALESCE(surname, ''), ' ', COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) as text")
            )
            ->limit(20)
            ->get();

        return response()->json($users);
    }

    /**
     * AJAX search invoices for Select2.
     */
    public function searchInvoices()
    {
        $this->authorizeView();

        if (! request()->ajax()) {
            abort(404);
        }

        $term = request()->input('q', '');
        $contact_id = request()->input('contact_id');

        $business_id = request()->session()->get('user.business_id');

        $query = Transaction::where('business_id', $business_id)
            ->where('type', 'sell')
            ->where('status', 'final');

        $permitted_locations = auth()->user()->permitted_locations();
        if ($permitted_locations != 'all') {
            $query->whereIn('location_id', $permitted_locations);
        }

        if (! empty($contact_id)) {
            $query->where('contact_id', $contact_id);
        }

        if (! empty($term)) {
            $query->where('invoice_no', 'like', '%'.$term.'%');
        }

        $sells = $query->with('contact')
            ->select('id', 'invoice_no', 'contact_id', 'final_total', 'transaction_date')
            ->orderBy('transaction_date', 'desc')
            ->limit(20)
            ->get();

        $results = [];
        foreach ($sells as $sell) {
            $contact_label = $sell->contact->name ?? '';
            $results[] = [
                'id' => $sell->id,
                'text' => $sell->invoice_no.' - '.$contact_label,
            ];
        }

        return response()->json($results);
    }
}
