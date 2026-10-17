{{-- Storefront dashboard capabilities (shared by role create / edit). Expects $role_permissions (array of names). --}}
@php
  $storefront_permissions = [
    'storefront.settings' => __('lang_v1.storefront_perm_settings'),
    'storefront.community' => __('lang_v1.storefront_perm_community'),
    'storefront.requests' => __('lang_v1.storefront_perm_requests'),
    'product_review.access' => __('lang_v1.storefront_perm_reviews_view'),
    'product_review.moderate' => __('lang_v1.storefront_perm_reviews_moderate'),
    'coupon.access' => __('lang_v1.storefront_perm_coupons_view'),
    'coupon.create' => __('lang_v1.storefront_perm_coupons_create'),
    'coupon.delete' => __('lang_v1.storefront_perm_coupons_delete'),
  ];
@endphp
<div class="row check_group">
  <div class="col-md-1">
    <h4>@lang('lang_v1.storefront_permissions')</h4>
  </div>
  <div class="col-md-2">
    <div class="checkbox">
      <label>
        <input type="checkbox" class="check_all input-icheck"> {{ __('role.select_all') }}
      </label>
    </div>
  </div>
  <div class="col-md-9">
    @foreach($storefront_permissions as $permission => $label)
      <div class="col-md-12">
        <div class="checkbox">
          <label>
            {!! Form::checkbox('permissions[]', $permission, in_array($permission, $role_permissions ?? []), ['class' => 'input-icheck']); !!} {{ $label }}
          </label>
        </div>
      </div>
    @endforeach
  </div>
</div>
<hr>
