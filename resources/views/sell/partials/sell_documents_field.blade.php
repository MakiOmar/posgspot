{{-- Multiple "Attach Documents" for sells / sales orders. Optional: $transaction (edit form) lists saved files with delete. --}}
@php
	$sell_doc_accept = implode(',', array_keys(config('constants.document_upload_mimes_types')));
	$sell_doc_saved = !empty($transaction) ? app(\App\Services\SellDocumentService::class)->documentsFor($transaction) : collect();
@endphp
<div class="form-group sell-documents-field"
	data-max-files="{{ \App\Services\SellDocumentService::MAX_FILES_PER_REQUEST }}"
	data-max-bytes="{{ (int) config('constants.document_size_limit') }}"
	data-msg-too-large="{{ __('lang_v1.sell_document_too_large', ['size' => config('constants.document_size_limit') / 1000000]) }}"
	data-msg-max-files="{{ __('lang_v1.sell_document_max_files', ['count' => \App\Services\SellDocumentService::MAX_FILES_PER_REQUEST]) }}"
	data-msg-remove="{{ __('messages.delete') }}">
	{!! Form::label('sell_documents_picker', __('lang_v1.attach_documents') . ':') !!}

	{{-- Visible picker (no name); selected files are copied into the hidden named input below --}}
	<div>
		<label for="sell_documents_picker" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-primary tw-text-white" style="cursor: pointer;">
			<i class="fa fa-folder-open"></i> @lang('lang_v1.add_files')
		</label>
		<input type="file" id="sell_documents_picker" class="sell-documents-picker" multiple accept="{{ $sell_doc_accept }}" style="display: none;">
		<input type="file" name="sell_documents[]" class="sell-documents-input" multiple accept="{{ $sell_doc_accept }}" style="display: none;">
	</div>

	{{-- Files chosen in this form session, each removable before saving --}}
	<ul class="list-unstyled sell-documents-selected" style="margin-top: 8px;"></ul>

	<p class="help-block">
		@lang('purchase.max_file_size', ['size' => (config('constants.document_size_limit') / 1000000)])
		@includeIf('components.document_help_text')
	</p>

	@if(!empty($transaction))
		{{-- Already saved attachments (edit form) --}}
		<table class="table table-condensed sell-documents-saved">
			@if(!empty($transaction->document))
				<tr>
					<td>{{ $transaction->document_name }}</td>
					<td class="text-right">
						<a href="{{ $transaction->document_path }}" download="{{ $transaction->document_name }}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-accent"><i class="fas fa-download"></i> @lang('lang_v1.download')</a>
						<button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-error delete-sell-document" data-href="{{ route('sells.documents.destroy-legacy', ['transaction_id' => $transaction->id]) }}"><i class="fas fa-trash"></i> @lang('messages.delete')</button>
					</td>
				</tr>
			@endif
			@foreach($sell_doc_saved as $media)
				<tr>
					<td>{{ $media->display_name }}</td>
					<td class="text-right">
						<a href="{{ $media->display_url }}" download="{{ $media->display_name }}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-accent"><i class="fas fa-download"></i> @lang('lang_v1.download')</a>
						<button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-error delete-sell-document" data-href="{{ route('sells.documents.destroy', ['transaction_id' => $transaction->id, 'media_id' => $media->id]) }}"><i class="fas fa-trash"></i> @lang('messages.delete')</button>
					</td>
				</tr>
			@endforeach
		</table>
	@endif
</div>
