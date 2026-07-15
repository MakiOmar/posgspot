<div class="modal-dialog" role="document">
  <div class="modal-content">

    {!! Form::open(['url' => action([\App\Http\Controllers\TaxonomyController::class, 'update'], [$category->id]), 'method' => 'PUT', 'id' => 'category_edit_form', 'files' => true ]) !!}

    <div class="modal-header">
      <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      <h4 class="modal-title">@lang( 'messages.edit' )</h4>
    </div>

    <div class="modal-body">
      @php
        $name_label = !empty($module_category_data['taxonomy_label']) ? $module_category_data['taxonomy_label'] : __( 'category.category_name' );
        $cat_code_enabled = isset($module_category_data['enable_taxonomy_code']) && !$module_category_data['enable_taxonomy_code'] ? false : true;

        $cat_code_label = !empty($module_category_data['taxonomy_code_label']) ? $module_category_data['taxonomy_code_label'] : __( 'category.code' );

        $enable_sub_category = isset($module_category_data['enable_sub_taxonomy']) && !$module_category_data['enable_sub_taxonomy'] ? false : true;

        $category_code_help_text = !empty($module_category_data['taxonomy_code_help_text']) ? $module_category_data['taxonomy_code_help_text'] : __('lang_v1.category_code_help');
      @endphp
      <div class="form-group">
        {!! Form::label('name', $name_label . ':*') !!}
        {!! Form::text('name', $category->name, ['class' => 'form-control', 'required', 'placeholder' => $name_label]); !!}
      </div>
      @if($cat_code_enabled)
      <div class="form-group">
        {!! Form::label('short_code', $cat_code_label . ':') !!}
        {!! Form::text('short_code', $category->short_code, ['class' => 'form-control', 'placeholder' => $cat_code_label]); !!}
          <p class="help-block">{!! $category_code_help_text !!}</p>
      </div>
      @endif
      <div class="form-group">
        {!! Form::label('description', __( 'lang_v1.description' ) . ':') !!}
        {!! Form::textarea('description', $category->description, ['class' => 'form-control', 'placeholder' => __( 'lang_v1.description'), 'rows' => 3]); !!}
      </div>
      @if(($category->category_type ?? '') === 'product')
      {{-- Editable storefront URL slug --}}
      <div class="form-group">
        {!! Form::label('slug', 'Storefront slug:') !!}
        {!! Form::text('slug', $category->slug, ['class' => 'form-control', 'placeholder' => 'ps4-consoles', 'maxlength' => 191]); !!}
        <p class="help-block">Used in storefront URLs (/category/…). Changing this updates public category links.</p>
      </div>
      {{-- Storefront category card thumbnail --}}
      <div class="form-group">
        {!! Form::label('image', 'Storefront image:') !!}
        @if(!empty($category->image))
          <div style="margin-bottom: 8px;">
            <img src="{{ $category->image_url }}" alt="" style="max-height: 64px; max-width: 120px;">
          </div>
          <label>
            {!! Form::checkbox('clear_image', 1, false) !!} Remove current image
          </label>
        @endif
        {!! Form::file('image', ['id' => 'category_image', 'accept' => 'image/*']); !!}
        <p class="help-block">Optional. Shown on the storefront homepage top categories.</p>
      </div>

      <hr>
      <h4 style="margin-top: 0;">Homepage shelf</h4>
      <p class="help-block">Single place to control the homepage banner + product shelf for this category.</p>
      <div class="checkbox">
        <label>
          {!! Form::checkbox('show_on_homepage_shelf', 1, !empty($category->show_on_homepage_shelf)) !!}
          Show this category as a homepage shelf
        </label>
      </div>
      <div class="form-group">
        {!! Form::label('homepage_shelf_sort', 'Shelf sort order:') !!}
        {!! Form::number('homepage_shelf_sort', (int) ($category->homepage_shelf_sort ?? 0), ['class' => 'form-control', 'min' => 0, 'max' => 999]) !!}
        <p class="help-block">Lower numbers appear first on the homepage.</p>
      </div>
      <div class="form-group">
        {!! Form::label('shelf_heading', 'Section heading:') !!}
        {!! Form::text('shelf_heading', $category->shelf_heading, ['class' => 'form-control', 'placeholder' => $category->name, 'maxlength' => 191]) !!}
        <p class="help-block">Defaults to the category name when empty.</p>
      </div>
      <div class="form-group">
        {!! Form::label('shelf_view_more_label', 'View more label:') !!}
        {!! Form::text('shelf_view_more_label', $category->shelf_view_more_label, ['class' => 'form-control', 'placeholder' => 'View more', 'maxlength' => 80]) !!}
      </div>
      <div class="form-group">
        {!! Form::label('shelf_banner', 'Banner background image:') !!}
        @if(!empty($category->shelf_banner))
          <div style="margin-bottom: 8px;">
            <img src="{{ $category->shelf_banner_url }}" alt="" style="max-height: 120px; max-width: 80px;">
          </div>
          <label>
            {!! Form::checkbox('clear_shelf_banner', 1, false) !!} Remove current background
          </label>
        @endif
        {!! Form::file('shelf_banner', ['id' => 'shelf_banner', 'accept' => 'image/*']); !!}
        <p class="help-block">Full-bleed background behind the shelf banner content.</p>
      </div>
      <div class="form-group">
        {!! Form::label('shelf_banner_kicker', 'Banner eyebrow / kicker:') !!}
        {!! Form::text('shelf_banner_kicker', $category->shelf_banner_kicker, ['class' => 'form-control', 'maxlength' => 191]) !!}
      </div>
      <div class="form-group">
        {!! Form::label('shelf_banner_text', 'Banner title text:') !!}
        {!! Form::text('shelf_banner_text', $category->shelf_banner_text, ['class' => 'form-control', 'maxlength' => 191]) !!}
      </div>
      <div class="form-group">
        {!! Form::label('shelf_fg_image', 'Banner product image:') !!}
        @if(!empty($category->shelf_fg_image))
          <div style="margin-bottom: 8px;">
            <img src="{{ $category->shelf_fg_image_url }}" alt="" style="max-height: 100px; max-width: 140px;">
          </div>
          <label>
            {!! Form::checkbox('clear_shelf_fg_image', 1, false) !!} Remove product image
          </label>
        @endif
        {!! Form::file('shelf_fg_image', ['id' => 'shelf_fg_image', 'accept' => 'image/*']); !!}
        <p class="help-block">Shown between the title text and the Shop Now button.</p>
      </div>
      <div class="form-group">
        {!! Form::label('shelf_button_text', 'Banner button text:') !!}
        {!! Form::text('shelf_button_text', $category->shelf_button_text, ['class' => 'form-control', 'placeholder' => 'Shop now', 'maxlength' => 80]) !!}
      </div>
      <div class="form-group">
        {!! Form::label('shelf_banner_link', 'Banner / button link:') !!}
        {!! Form::text('shelf_banner_link', $category->shelf_banner_link, ['class' => 'form-control', 'placeholder' => '/category/'.$category->slug, 'maxlength' => 500]) !!}
        <p class="help-block">Leave blank to link to this category page.</p>
      </div>
      @endif
      @if(!empty($parent_categories) && $enable_sub_category)
          <div class="form-group">
            <div class="checkbox">
              <label>
                 {!! Form::checkbox('add_as_sub_cat', 1, !$is_parent,[ 'class' => 'toggler', 'data-toggle_id' => 'parent_cat_div' ]); !!} @lang( 'lang_v1.add_as_sub_txonomy' )
              </label>
            </div>
          </div>
          <div class="form-group @if($is_parent) {{'hide' }} @endif" id="parent_cat_div">
            {!! Form::label('parent_id', __( 'lang_v1.select_parent_taxonomy' ) . ':') !!}
            {!! Form::select('parent_id', $parent_categories, $selected_parent, ['class' => 'form-control']); !!}
          </div>
      @endif
    </div>

    <div class="modal-footer">
      <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">@lang( 'messages.update' )</button>
      <button type="button" class="tw-dw-btn tw-dw-btn-neutral tw-text-white" data-dismiss="modal">@lang( 'messages.close' )</button>
    </div>

    {!! Form::close() !!}

  </div><!-- /.modal-content -->
</div><!-- /.modal-dialog -->