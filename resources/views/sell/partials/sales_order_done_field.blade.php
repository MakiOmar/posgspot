{{-- Staff "Done" marker for sales orders; the hidden input keeps an unticked box in the request. --}}
<div class="col-sm-3">
	<div class="form-group">
		<div class="checkbox">
			<label>
				<input type="hidden" name="is_done" value="0">
				{!! Form::checkbox('is_done', 1, !empty($is_done), ['class' => 'input-icheck', 'id' => 'is_done']); !!}
				@lang('lang_v1.so_done')
			</label>
			@show_tooltip(__('lang_v1.so_done_help'))
		</div>
	</div>
</div>
