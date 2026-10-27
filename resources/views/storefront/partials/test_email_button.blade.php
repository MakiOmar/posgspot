{{-- Test one storefront notification inbox; $input is the email field name to test (null = saved/fallback inbox) --}}
<div class="form-group">
    <p class="help-block">
        @lang('lang_v1.storefront_test_email_current_recipient'):
        <strong>{{ $notify_recipients[$type] ?? '' }}</strong>
    </p>
    <button type="button"
            class="btn btn-default btn-sm js-storefront-test-email"
            data-type="{{ $type }}"
            @if(! empty($input)) data-input="{{ $input }}" @endif>
        <i class="fa fa-paper-plane"></i> @lang('lang_v1.storefront_test_email_send')
    </button>
</div>
