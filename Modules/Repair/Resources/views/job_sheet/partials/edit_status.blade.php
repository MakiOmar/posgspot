<div class="modal-dialog" role="document">
	<div class="modal-content">
		{!! Form::open(['url' => action([\Modules\Repair\Http\Controllers\JobSheetController::class, 'updateStatus'], [$job_sheet->id]), 'method' => 'put', 'id' => 'update_status_form']) !!}
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title">@lang( 'repair::lang.edit_status' )</h4>
			</div>

			<div class="modal-body">
				<div class="row">
					<div class="col-md-12">
						<strong>
							@lang('repair::lang.job_sheet_no'):
						</strong>
						<span id="job_sheet_no">
							{{$job_sheet->job_sheet_no}}
						</span>
					</div>
				</div>
				@include('repair::job_sheet.partials.edit_status_form')
			</div>
			<div class="modal-footer">
				{{-- Client redirect after AJAX; defer_complete tells backend to stash completed status until parts are saved --}}
				<input type="hidden" id="status_form_redirect" value="">
				<input type="hidden" name="defer_complete" id="defer_complete" value="0">
				@if(empty($job_sheet->status) || $job_sheet->status->is_completed_status != 1)
					<button type="submit" class="btn btn-danger ladda-button update_status_button hide mark-as-complete-btn" data-href="{{action([\Modules\Repair\Http\Controllers\JobSheetController::class, 'addParts'], [$job_sheet->id])}}" data-defer-complete="1">@lang( 'repair::lang.add_parts_and_mark_complete' )</button>
				@endif

				<button type="submit" class="tw-dw-btn tw-dw-btn-success tw-text-white ladda-button update_status_button mark-as-incomplete-btn" data-href="{{action([\Modules\Repair\Http\Controllers\JobSheetController::class, 'addParts'], [$job_sheet->id])}}" data-defer-complete="1">@lang( 'repair::lang.save_and_add_parts' )</button>
				{{-- Update always visible: persists status immediately, including completed statuses --}}
				<button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white ladda-button update_status_button" data-href="" data-defer-complete="0">@lang( 'messages.update' )</button>
				<button type="button" class="tw-dw-btn tw-dw-btn-neutral tw-text-white" data-dismiss="modal">@lang( 'messages.close' )</button>
			</div>

		{!! Form::close() !!}
	</div><!-- /.modal-content -->
</div><!-- /.modal-dialog -->