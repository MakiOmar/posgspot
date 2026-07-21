<?php

namespace App\Http\Controllers;

use App\Brands;
use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class BrandController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $moduleUtil;

    /**
     * Constructor
     *
     * @param  ProductUtils  $product
     * @return void
     */
    public function __construct(ModuleUtil $moduleUtil)
    {
        $this->moduleUtil = $moduleUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (! auth()->user()->can('brand.view') && ! auth()->user()->can('brand.create')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');
            $can_update = auth()->user()->can('brand.update');
            $can_delete = auth()->user()->can('brand.delete');

            $brands = Brands::where('business_id', $business_id)
                        ->select(['name', 'description', 'image', 'id']);

            return Datatables::of($brands)
                ->addColumn('logo', function ($row) {
                    if (empty($row->image)) {
                        return '<span class="text-muted">—</span>';
                    }

                    return '<img src="'.e($row->image_url).'" alt="" style="max-height:36px;max-width:72px;object-fit:contain;">';
                })
                ->addColumn(
                    'action',
                    function ($row) use ($can_update, $can_delete) {
                        $html = '';
                        if ($can_update) {
                            $html .= '<button type="button" data-href="'.action([\App\Http\Controllers\BrandController::class, 'edit'], [$row->id]).'" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary edit_brand_button"><i class="glyphicon glyphicon-edit"></i> '.__('messages.edit').'</button>&nbsp;';
                        }
                        if ($can_delete) {
                            $html .= '<button type="button" data-href="'.action([\App\Http\Controllers\BrandController::class, 'destroy'], [$row->id]).'" class="tw-dw-btn tw-dw-btn-outline tw-dw-btn-xs tw-dw-btn-error delete_brand_button"><i class="glyphicon glyphicon-trash"></i> '.__('messages.delete').'</button>';
                        }

                        return $html;
                    }
                )
                ->removeColumn('id')
                ->removeColumn('image')
                ->rawColumns(['logo', 'action'])
                ->make(true);
        }

        return view('brand.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (! auth()->user()->can('brand.create')) {
            abort(403, 'Unauthorized action.');
        }

        $quick_add = false;
        if (! empty(request()->input('quick_add'))) {
            $quick_add = true;
        }

        $is_repair_installed = $this->moduleUtil->isModuleInstalled('Repair');

        return view('brand.create')
                ->with(compact('quick_add', 'is_repair_installed'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (! auth()->user()->can('brand.create')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->only(['name', 'description']);
            $business_id = $request->session()->get('user.business_id');
            $input['business_id'] = $business_id;
            $input['created_by'] = $request->session()->get('user.id');

            if ($this->moduleUtil->isModuleInstalled('Repair')) {
                $input['use_for_repair'] = ! empty($request->input('use_for_repair')) ? 1 : 0;
            }

            $input['slug'] = Brands::generateSlug((string) $input['name'], $business_id);

            // Storefront brand logo (same uploads/img path as products).
            $fileName = $this->moduleUtil->uploadFile($request, 'image', config('constants.product_img_path'), 'image');
            if (! empty($fileName)) {
                $input['image'] = $fileName;
            }

            $brand = Brands::create($input);
            $output = ['success' => true,
                'data' => $brand,
                'msg' => __('brand.added_success'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (! auth()->user()->can('brand.update')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');
            $brand = Brands::where('business_id', $business_id)->findOrFail($id);

            $is_repair_installed = $this->moduleUtil->isModuleInstalled('Repair');

            return view('brand.edit')
                ->with(compact('brand', 'is_repair_installed'));
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (! auth()->user()->can('brand.update')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $input = $request->only(['name', 'description']);
                $business_id = $request->session()->get('user.business_id');

                $brand = Brands::where('business_id', $business_id)->findOrFail($id);
                $brand->name = $input['name'];
                $brand->description = $input['description'];

                if (empty($brand->slug)) {
                    $brand->slug = Brands::generateSlug((string) $brand->name, $business_id, $brand->id);
                }

                if ($this->moduleUtil->isModuleInstalled('Repair')) {
                    $brand->use_for_repair = ! empty($request->input('use_for_repair')) ? 1 : 0;
                }

                // Storefront brand logo.
                if (! empty($request->input('clear_image'))) {
                    $brand->image = null;
                }
                $fileName = $this->moduleUtil->uploadFile($request, 'image', config('constants.product_img_path'), 'image');
                if (! empty($fileName)) {
                    $brand->image = $fileName;
                }

                $brand->save();

                $output = ['success' => true,
                    'msg' => __('brand.updated_success'),
                ];
            } catch (\Exception $e) {
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = ['success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (! auth()->user()->can('brand.delete')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $business_id = request()->user()->business_id;

                $brand = Brands::where('business_id', $business_id)->findOrFail($id);
                $brand->delete();

                $output = ['success' => true,
                    'msg' => __('brand.deleted_success'),
                ];
            } catch (\Exception $e) {
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = ['success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }

    public function getBrandsApi()
    {
        try {
            $api_token = request()->header('API-TOKEN');

            $api_settings = $this->moduleUtil->getApiSettings($api_token);

            $brands = Brands::where('business_id', $api_settings->business_id)
                                ->get();
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            return $this->respondWentWrong($e);
        }

        return $this->respond($brands);
    }
}
