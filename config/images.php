<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Upload image → WebP
    |--------------------------------------------------------------------------
    |
    | When enabled and PHP GD has imagewebp(), raster uploads (JPEG/PNG/GIF/BMP)
    | are converted to WebP after store. SVG, ICO, animated GIF, and existing
    | WebP are left unchanged.
    |
    */

    'webp_on_upload' => filter_var(
        env('UPLOAD_WEBP_CONVERT', true),
        FILTER_VALIDATE_BOOLEAN
    ),

    /** GD imagewebp quality 1–100 (higher = larger / better). */
    'webp_quality' => (int) env('UPLOAD_WEBP_QUALITY', 82),

];
