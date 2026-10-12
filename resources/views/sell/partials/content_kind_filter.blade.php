{{-- Accounts content filter (transactions.content_kind), shared by the sells and sales-order lists --}}
<div class="col-md-3">
    <div class="form-group">
        {!! Form::label('sell_list_filter_content_kind', __('lang_v1.content_kind') . ':') !!}
        {!! Form::select('sell_list_filter_content_kind', [
            'accounts_only' => __('lang_v1.content_kind_accounts_only'),
            'mixed' => __('lang_v1.content_kind_mixed'),
            'pos_only' => __('lang_v1.content_kind_pos_only'),
        ], null, [
            'class' => 'form-control select2',
            'style' => 'width:100%',
            'placeholder' => __('lang_v1.all'),
        ]) !!}
    </div>
</div>
