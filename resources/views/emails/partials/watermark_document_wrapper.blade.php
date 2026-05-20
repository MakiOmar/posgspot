{{-- Document watermark wrapper for invoices, PDFs, and browser print preview --}}
<div
    style="{{ $background_style }} background-color: #ffffff; position: relative; width: 100%; min-height: 100%;"
    @if(!empty($watermark_background_url)) background="{{ $watermark_background_url }}" @endif
>
    {!! $html !!}
</div>
