<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Storefront\StorefrontTranslationService;
use Illuminate\Http\Request;

/**
 * Storefront-only Arabic overlay editor (POS catalog forms unchanged).
 */
class StorefrontTranslationController extends Controller
{
    public function __construct(private StorefrontTranslationService $translations)
    {
    }

    private function businessId(Request $request): int
    {
        return (int) $request->session()->get('user.business_id');
    }

    private function authorizeSettings(Request $request): void
    {
        if (! auth()->user()->can('storefront.settings')) {
            abort(403, 'Unauthorized action.');
        }
    }

    public function productsIndex(Request $request)
    {
        $this->authorizeSettings($request);
        $products = $this->translations->listProducts($this->businessId($request));

        return view('storefront.translations.products_index', compact('products'));
    }

    public function productsEdit(Request $request, int $id)
    {
        $this->authorizeSettings($request);

        if (! $request->ajax()) {
            return redirect()->action([self::class, 'productsIndex']);
        }

        $product = $this->translations->getProductForEdit($this->businessId($request), $id);
        if (empty($product)) {
            abort(404);
        }

        $ar = $product->storefrontTranslations->firstWhere('locale', 'ar');

        return view('storefront.translations.products_edit', compact('product', 'ar'));
    }

    public function productsUpdate(Request $request, int $id)
    {
        $this->authorizeSettings($request);
        $validated = $request->validate([
            'name' => 'nullable|string|max:191',
            'slug' => 'nullable|string|max:191',
            'product_description' => 'nullable|string',
            'variations' => 'nullable|array',
            'variations.*' => 'nullable|string|max:191',
        ]);

        try {
            $this->translations->saveProductTranslation($this->businessId($request), $id, $validated);
        } catch (\Throwable $e) {
            \Log::emergency('File:'.$e->getFile().' Line:'.$e->getLine().' Message:'.$e->getMessage());

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ]);
            }

            return redirect()
                ->action([self::class, 'productsIndex'])
                ->with('status', ['success' => false, 'msg' => __('messages.something_went_wrong')]);
        }

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'msg' => __('lang_v1.success'),
            ]);
        }

        return redirect()
            ->action([self::class, 'productsIndex'])
            ->with('status', ['success' => true, 'msg' => __('lang_v1.success')]);
    }

    public function categoriesIndex(Request $request)
    {
        $this->authorizeSettings($request);
        $categories = $this->translations->listCategories($this->businessId($request));

        return view('storefront.translations.categories_index', compact('categories'));
    }

    public function categoriesEdit(Request $request, int $id)
    {
        $this->authorizeSettings($request);
        $category = $this->translations->getCategoryForEdit($this->businessId($request), $id);
        if (empty($category)) {
            abort(404);
        }

        $ar = $category->storefrontTranslations->firstWhere('locale', 'ar');

        return view('storefront.translations.categories_edit', compact('category', 'ar'));
    }

    public function categoriesUpdate(Request $request, int $id)
    {
        $this->authorizeSettings($request);
        $validated = $request->validate([
            'name' => 'nullable|string|max:191',
            'slug' => 'nullable|string|max:191',
        ]);

        $this->translations->saveCategoryTranslation($this->businessId($request), $id, $validated);

        return redirect()
            ->action([self::class, 'categoriesEdit'], $id)
            ->with('status', ['success' => true, 'msg' => __('lang_v1.success')]);
    }

    public function brandsIndex(Request $request)
    {
        $this->authorizeSettings($request);
        $brands = $this->translations->listBrands($this->businessId($request));

        return view('storefront.translations.brands_index', compact('brands'));
    }

    public function brandsEdit(Request $request, int $id)
    {
        $this->authorizeSettings($request);
        $brand = $this->translations->getBrandForEdit($this->businessId($request), $id);
        if (empty($brand)) {
            abort(404);
        }

        $ar = $brand->storefrontTranslations->firstWhere('locale', 'ar');

        return view('storefront.translations.brands_edit', compact('brand', 'ar'));
    }

    public function brandsUpdate(Request $request, int $id)
    {
        $this->authorizeSettings($request);
        $validated = $request->validate([
            'name' => 'nullable|string|max:191',
        ]);

        $this->translations->saveBrandTranslation($this->businessId($request), $id, $validated);

        return redirect()
            ->action([self::class, 'brandsEdit'], $id)
            ->with('status', ['success' => true, 'msg' => __('lang_v1.success')]);
    }
}
