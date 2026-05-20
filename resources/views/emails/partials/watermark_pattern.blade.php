{{-- Repeating staggered watermark pattern (ZARA-style tiled layout) --}}
@if(!empty($watermark['enabled']) && !empty($watermark['items']))
<div class="email-watermark-layer">
    @foreach($watermark['items'] as $item)
        <span class="email-watermark-item" style="left: {{ $item['left'] }}%; top: {{ $item['top'] }}%;">
            @if(!empty($watermark['type']) && $watermark['type'] === 'logo' && !empty($watermark['logo_url']))
                <img src="{{ $watermark['logo_url'] }}" alt="Watermark">
            @elseif(!empty($watermark['business_name']))
                {{ mb_strtoupper($watermark['business_name']) }}
            @endif
        </span>
    @endforeach
</div>
@endif
