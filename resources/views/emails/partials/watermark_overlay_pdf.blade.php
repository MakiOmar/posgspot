<style>
    .email-watermark-layer {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: -1;
    }
    .email-watermark-item {
        position: absolute;
        transform: rotate(-45deg);
        opacity: 0.13;
        color: #999999;
        font-family: Georgia, "Times New Roman", serif;
        font-size: 26px;
        letter-spacing: 3px;
        white-space: nowrap;
    }
    .email-watermark-item img {
        width: 72px;
        height: auto;
    }
</style>
@include('emails.partials.watermark_pattern')
