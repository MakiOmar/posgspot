<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\Services\Storefront\Shipping\ShippingLegacyMigrator;
use App\Services\Storefront\Shipping\ShippingZoneRepository;
use App\StorefrontShippingMethod;
use App\StorefrontShippingZone;
use App\StorefrontShippingZoneLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * POS admin CRUD for storefront shipping zones / methods.
 */
class StorefrontShippingZoneController extends Controller
{
    public function __construct(
        private ShippingZoneRepository $zones,
        private ShippingLegacyMigrator $migrator
    ) {
    }

    public function index(Request $request)
    {
        if (! auth()->user()->can('storefront.settings')) {
            abort(403, 'Unauthorized action.');
        }

        $businessId = (int) $request->session()->get('user.business_id');
        $this->migrator->ensureDefaultZones($businessId);

        $zones = StorefrontShippingZone::where('business_id', $businessId)
            ->with(['locations', 'methods'])
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        $pickupLocations = BusinessLocation::where('business_id', $businessId)
            ->where('enable_pickup', 1)
            ->Active()
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'success' => true,
            'data' => [
                'zones' => $zones,
                'pickup_locations' => $pickupLocations,
            ],
        ]);
    }

    public function store(Request $request)
    {
        if (! auth()->user()->can('storefront.settings')) {
            abort(403, 'Unauthorized action.');
        }

        $businessId = (int) $request->session()->get('user.business_id');
        $data = $request->validate([
            'name' => 'required|string|max:191',
            'priority' => 'nullable|integer|min:0',
            'is_catch_all' => 'nullable|boolean',
            'is_enabled' => 'nullable|boolean',
            'locations' => 'nullable|array',
            'locations.*.type' => 'required_with:locations|in:country,state',
            'locations.*.code' => 'required_with:locations|string|max:64',
        ]);

        $zone = DB::transaction(function () use ($businessId, $data) {
            $zone = StorefrontShippingZone::create([
                'business_id' => $businessId,
                'name' => $data['name'],
                'priority' => $data['priority'] ?? 100,
                'is_catch_all' => ! empty($data['is_catch_all']),
                'is_enabled' => array_key_exists('is_enabled', $data) ? (bool) $data['is_enabled'] : true,
            ]);
            $this->syncLocations($zone, $data['locations'] ?? []);

            return $zone->load(['locations', 'methods']);
        });

        $this->zones->flush($businessId);

        return response()->json(['success' => true, 'data' => $zone]);
    }

    public function update(Request $request, int $id)
    {
        if (! auth()->user()->can('storefront.settings')) {
            abort(403, 'Unauthorized action.');
        }

        $businessId = (int) $request->session()->get('user.business_id');
        $zone = StorefrontShippingZone::where('business_id', $businessId)->findOrFail($id);

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:191',
            'priority' => 'nullable|integer|min:0',
            'is_catch_all' => 'nullable|boolean',
            'is_enabled' => 'nullable|boolean',
            'locations' => 'nullable|array',
            'locations.*.type' => 'required_with:locations|in:country,state',
            'locations.*.code' => 'required_with:locations|string|max:64',
        ]);

        DB::transaction(function () use ($zone, $data) {
            $zone->fill([
                'name' => $data['name'] ?? $zone->name,
                'priority' => $data['priority'] ?? $zone->priority,
                'is_catch_all' => array_key_exists('is_catch_all', $data) ? (bool) $data['is_catch_all'] : $zone->is_catch_all,
                'is_enabled' => array_key_exists('is_enabled', $data) ? (bool) $data['is_enabled'] : $zone->is_enabled,
            ]);
            $zone->save();
            if (array_key_exists('locations', $data)) {
                $this->syncLocations($zone, $data['locations'] ?? []);
            }
        });

        $this->zones->flush($businessId);

        return response()->json(['success' => true, 'data' => $zone->fresh(['locations', 'methods'])]);
    }

    public function destroy(Request $request, int $id)
    {
        if (! auth()->user()->can('storefront.settings')) {
            abort(403, 'Unauthorized action.');
        }

        $businessId = (int) $request->session()->get('user.business_id');
        $zone = StorefrontShippingZone::where('business_id', $businessId)->findOrFail($id);
        $zone->delete();
        $this->zones->flush($businessId);

        return response()->json(['success' => true]);
    }

    public function storeMethod(Request $request, int $zoneId)
    {
        if (! auth()->user()->can('storefront.settings')) {
            abort(403, 'Unauthorized action.');
        }

        $businessId = (int) $request->session()->get('user.business_id');
        $zone = StorefrontShippingZone::where('business_id', $businessId)->findOrFail($zoneId);

        $data = $request->validate([
            'type' => 'required|in:flat_rate,free_shipping,local_pickup',
            'title' => 'required|string|max:191',
            'title_en' => 'nullable|string|max:191',
            'title_ar' => 'nullable|string|max:191',
            'sort_order' => 'nullable|integer|min:0',
            'is_enabled' => 'nullable|boolean',
            'settings' => 'nullable|array',
        ]);

        $method = StorefrontShippingMethod::create([
            'zone_id' => $zone->id,
            'type' => $data['type'],
            'title' => $data['title'],
            'title_i18n' => [
                'en' => $data['title_en'] ?? $data['title'],
                'ar' => $data['title_ar'] ?? $data['title'],
            ],
            'settings' => $data['settings'] ?? [],
            'sort_order' => $data['sort_order'] ?? 10,
            'is_enabled' => array_key_exists('is_enabled', $data) ? (bool) $data['is_enabled'] : true,
        ]);

        $this->zones->flush($businessId);

        return response()->json(['success' => true, 'data' => $method]);
    }

    public function updateMethod(Request $request, int $methodId)
    {
        if (! auth()->user()->can('storefront.settings')) {
            abort(403, 'Unauthorized action.');
        }

        $businessId = (int) $request->session()->get('user.business_id');
        $method = StorefrontShippingMethod::whereHas('zone', fn ($q) => $q->where('business_id', $businessId))
            ->findOrFail($methodId);

        $data = $request->validate([
            'title' => 'sometimes|required|string|max:191',
            'title_en' => 'nullable|string|max:191',
            'title_ar' => 'nullable|string|max:191',
            'sort_order' => 'nullable|integer|min:0',
            'is_enabled' => 'nullable|boolean',
            'settings' => 'nullable|array',
        ]);

        if (isset($data['title'])) {
            $method->title = $data['title'];
        }
        $i18n = $method->title_i18n ?? [];
        if (array_key_exists('title_en', $data)) {
            $i18n['en'] = $data['title_en'];
        }
        if (array_key_exists('title_ar', $data)) {
            $i18n['ar'] = $data['title_ar'];
        }
        $method->title_i18n = $i18n;
        if (array_key_exists('settings', $data)) {
            $method->settings = $data['settings'];
        }
        if (array_key_exists('sort_order', $data)) {
            $method->sort_order = $data['sort_order'];
        }
        if (array_key_exists('is_enabled', $data)) {
            $method->is_enabled = (bool) $data['is_enabled'];
        }
        $method->save();
        $this->zones->flush($businessId);

        return response()->json(['success' => true, 'data' => $method]);
    }

    public function destroyMethod(Request $request, int $methodId)
    {
        if (! auth()->user()->can('storefront.settings')) {
            abort(403, 'Unauthorized action.');
        }

        $businessId = (int) $request->session()->get('user.business_id');
        $method = StorefrontShippingMethod::whereHas('zone', fn ($q) => $q->where('business_id', $businessId))
            ->findOrFail($methodId);
        $method->delete();
        $this->zones->flush($businessId);

        return response()->json(['success' => true]);
    }

    public function classesIndex(Request $request)
    {
        if (! auth()->user()->can('storefront.settings')) {
            abort(403, 'Unauthorized action.');
        }

        $businessId = (int) $request->session()->get('user.business_id');
        $classes = \App\StorefrontShippingClass::where('business_id', $businessId)
            ->orderBy('name')
            ->get();

        return response()->json(['success' => true, 'data' => ['classes' => $classes]]);
    }

    public function storeClass(Request $request)
    {
        if (! auth()->user()->can('storefront.settings')) {
            abort(403, 'Unauthorized action.');
        }

        $businessId = (int) $request->session()->get('user.business_id');
        $data = $request->validate([
            'name' => 'required|string|max:191',
            'slug' => 'nullable|string|max:191',
        ]);

        $class = \App\StorefrontShippingClass::create([
            'business_id' => $businessId,
            'name' => $data['name'],
            'slug' => $data['slug'] ?? \Illuminate\Support\Str::slug($data['name']),
        ]);

        return response()->json(['success' => true, 'data' => $class]);
    }

    public function destroyClass(Request $request, int $id)
    {
        if (! auth()->user()->can('storefront.settings')) {
            abort(403, 'Unauthorized action.');
        }

        $businessId = (int) $request->session()->get('user.business_id');
        $class = \App\StorefrontShippingClass::where('business_id', $businessId)->findOrFail($id);
        \App\Product::where('business_id', $businessId)
            ->where('shipping_class_id', $class->id)
            ->update(['shipping_class_id' => null]);
        $class->delete();

        return response()->json(['success' => true]);
    }

    private function syncLocations(StorefrontShippingZone $zone, array $locations): void
    {
        StorefrontShippingZoneLocation::where('zone_id', $zone->id)->delete();
        foreach ($locations as $loc) {
            StorefrontShippingZoneLocation::create([
                'zone_id' => $zone->id,
                'type' => $loc['type'],
                'code' => $loc['code'],
            ]);
        }
    }
}
