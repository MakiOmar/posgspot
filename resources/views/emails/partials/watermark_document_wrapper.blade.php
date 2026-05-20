{{-- Document watermark wrapper for invoices, PDFs, and browser print preview --}}
<style>
    .document-watermark-wrap {
        position: relative;
        width: 100%;
        min-height: 100%;
        background-color: #ffffff;
    }
    .document-watermark-layer {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        min-height: 100%;
        overflow: hidden;
        pointer-events: none;
        z-index: 2;
    }
    .document-watermark-item {
        position: absolute;
        transform: rotate(-45deg);
        -webkit-transform: rotate(-45deg);
        opacity: 0.10;
        color: #aaaaaa;
        font-family: Georgia, "Times New Roman", serif;
        font-size: 18px;
        letter-spacing: 2px;
        white-space: nowrap;
        line-height: 1;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .document-watermark-item img {
        width: 48px;
        height: auto;
        opacity: 0.85;
    }
    .document-watermark-content {
        position: relative;
        z-index: 1;
        background-color: transparent;
    }
    @media print {
        .document-watermark-wrap {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        .document-watermark-layer {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: 100% !important;
            min-height: 100% !important;
            z-index: 9999 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        .document-watermark-item {
            opacity: 0.11 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        .document-watermark-content {
            position: relative !important;
            z-index: 1 !important;
        }
    }
</style>
<div
    class="document-watermark-wrap"
    style="@if(!empty($for_pdf) && !empty($background_style)) {{ $background_style }} @endif"
    @if(!empty($for_pdf) && !empty($watermark_background_url)) background="{{ $watermark_background_url }}" @endif
>
    <div class="document-watermark-content">
        {!! $html !!}
    </div>

    @if(empty($for_pdf) && !empty($watermark['enabled']) && !empty($watermark['items']))
        @include('emails.partials.watermark_document_overlay')
    @endif
</div>
