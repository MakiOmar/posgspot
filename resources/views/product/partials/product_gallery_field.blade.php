{{-- Product gallery: existing thumbs (edit), library picker, direct upload --}}
@php
  $productGallery = isset($product)
    ? ($product->media ?? collect())->where('model_media_type', 'product_gallery')
    : collect();
@endphp
<div class="form-group" id="product_gallery_field"
     data-media-url="{{ action([\App\Http\Controllers\StorefrontSettingController::class, 'listMedia']) }}"
     data-upload-url="{{ action([\App\Http\Controllers\StorefrontSettingController::class, 'uploadHomepageMedia']) }}">
  {!! Form::label('product_gallery', __('lang_v1.product_gallery') . ':') !!}

  @if($productGallery->isNotEmpty())
    <div class="row" style="margin-bottom:8px;">
      @foreach($productGallery as $media)
        <div class="col-xs-4 col-sm-3 col-md-2" style="margin-bottom:8px;">
          <div class="img-thumbnail" style="position:relative;display:inline-block;">
            <span class="badge bg-red delete-media" data-href="{{ action([\App\Http\Controllers\ProductController::class, 'deleteMedia'], ['media_id' => $media->id]) }}"><i class="fas fa-times"></i></span>
            {!! $media->thumbnail() !!}
          </div>
        </div>
      @endforeach
    </div>
  @endif

  {{-- Pending library picks (copied into product Media on save) --}}
  <div id="product_gallery_library_pending" class="row" style="margin-bottom:8px;"></div>

  <div class="btn-group" style="margin-bottom:8px;">
    <button type="button" class="btn btn-default btn-sm" id="product_gallery_library_btn">
      <i class="fas fa-images"></i> @lang('lang_v1.product_gallery_from_library')
    </button>
  </div>

  {!! Form::file('product_gallery[]', ['id' => 'product_gallery', 'accept' => 'image/*', 'multiple' => true, 'class' => 'form-control']); !!}
  <small>
    <p class="help-block">
      @lang('lang_v1.product_gallery_help')
      <br>@lang('purchase.max_file_size', ['size' => (config('constants.document_size_limit') / 1000000)])
      <br>@lang('lang_v1.aspect_ratio_should_be_1_1')
    </p>
  </small>
</div>

{{-- Media library modal (product gallery — images only) --}}
<style>
.product-gallery-lib-modal{position:fixed;inset:0;z-index:1050;background:rgba(0,0,0,.45);display:flex;align-items:center;justify-content:center;padding:16px;}
.product-gallery-lib-modal[hidden]{display:none!important;}
.product-gallery-lib-dialog{background:#fff;border-radius:6px;max-width:720px;width:100%;max-height:90vh;overflow:auto;padding:12px 14px;box-shadow:0 8px 28px rgba(0,0,0,.2);}
.product-gallery-lib-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;}
.product-gallery-lib-toolbar{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px;}
.product-gallery-lib-toolbar .form-control{max-width:220px;}
.product-gallery-lib-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(110px,1fr));gap:8px;min-height:80px;}
.product-gallery-lib-card{border:1px solid #ddd;border-radius:4px;background:#fafafa;padding:6px;cursor:pointer;text-align:left;}
.product-gallery-lib-card:hover{border-color:#3c8dbc;}
.product-gallery-lib-card img{display:block;width:100%;height:72px;object-fit:cover;border-radius:2px;margin-bottom:4px;}
.product-gallery-lib-name{display:block;font-size:11px;line-height:1.2;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.product-gallery-lib-pager{display:flex;align-items:center;gap:8px;margin-top:10px;}
</style>
<div id="product_gallery_library_modal" class="product-gallery-lib-modal" hidden>
  <div class="product-gallery-lib-dialog" role="dialog" aria-modal="true" aria-label="{{ __('lang_v1.media_library') }}">
    <div class="product-gallery-lib-head">
      <strong>@lang('lang_v1.media_library')</strong>
      <button type="button" class="btn btn-default btn-xs" id="product_gallery_library_close">@lang('messages.close')</button>
    </div>
    <div class="product-gallery-lib-toolbar">
      <input type="search" class="form-control input-sm" id="product_gallery_library_q" placeholder="@lang('lang_v1.search')" />
      <button type="button" class="btn btn-default btn-sm" id="product_gallery_library_search">@lang('lang_v1.search')</button>
      <button type="button" class="btn btn-primary btn-sm" id="product_gallery_library_upload">@lang('lang_v1.upload_new')</button>
      <input type="file" id="product_gallery_library_file" accept="image/jpeg,image/png,image/gif,image/webp" hidden />
    </div>
    <p id="product_gallery_library_status" class="text-muted" style="margin:8px 0;"></p>
    <div id="product_gallery_library_grid" class="product-gallery-lib-grid"></div>
    <div class="product-gallery-lib-pager">
      <button type="button" class="btn btn-default btn-xs" id="product_gallery_library_prev" disabled>Prev</button>
      <span id="product_gallery_library_page" class="text-muted"></span>
      <button type="button" class="btn btn-default btn-xs" id="product_gallery_library_next" disabled>Next</button>
    </div>
  </div>
</div>
