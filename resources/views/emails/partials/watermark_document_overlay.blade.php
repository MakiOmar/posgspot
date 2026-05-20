{{-- HTML watermark tiles for browser print (background-image is stripped by most browsers when printing) --}}
<div class="document-watermark-layer" aria-hidden="true">
    @foreach($watermark['items'] as $item)
        <span class="document-watermark-item" style="left: {{ $item['left'] }}%; top: {{ $item['top'] }}%;">
            @if(!empty($watermark['type']) && $watermark['type'] === 'logo' && !empty($watermark['logo_url']))
                <img src="{{ $watermark['logo_url'] }}" alt="">
            @elseif(!empty($watermark['business_name']))
                {{ mb_strtoupper($watermark['business_name']) }}
            @endif
        </span>
    @endforeach
</div>
