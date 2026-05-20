<div class="header">{{ $business_name }}</div>
<div class="content">
    <h2>@lang('lang_v1.test_email_sample_heading')</h2>
    <p>@lang('lang_v1.test_email_sample_body')</p>
    <div class="invoice-details">
        <p><strong>@lang('sale.invoice_no'):</strong> TEST-001</p>
        <p><strong>@lang('sale.total'):</strong> 1,250.00</p>
        <p><strong>@lang('lang_v1.payment_status'):</strong> @lang('lang_v1.paid')</p>
    </div>
    <a href="#" class="btn">@lang('lang_v1.view_invoice')</a>
</div>
<div class="footer">
    <p>@lang('lang_v1.test_email_sample_footer', ['business' => $business_name])</p>
</div>
