@extends('layouts.auth2')
@section('title', config('app.name', 'ultimatePOS'))
@inject('request', 'Illuminate\Http\Request')
@section('content')
@php
    // Get logo from settings - check system logo first, then first business logo
    $logo_path = null;
    
    // Check for system logo
    if (file_exists(public_path('uploads/logo.png'))) {
        $logo_path = asset('uploads/logo.png');
    } else {
        // Get first business logo from database
        $business = \App\Business::whereNotNull('logo')->first();
        if ($business && !empty($business->logo)) {
            $logo_path = asset('uploads/business_logos/' . $business->logo);
        }
    }
    
    // Fallback to default logo if none found
    if (!$logo_path) {
        $logo_path = asset('img/logo-small.png');
    }
@endphp

<div class="col-md-12 col-sm-12 col-xs-12 right-col tw-pt-20 tw-pb-10 tw-px-5 tw-flex tw-flex-col tw-items-center tw-justify-center tw-bg-blue-500" style="min-height: 100vh;">
    <div class="tw-flex tw-items-center tw-justify-center">
        <img src="{{ $logo_path }}" alt="{{ config('app.name', 'UltimatePOS') }}" class="tw-max-w-full tw-h-auto tw-max-h-64 tw-object-contain" style="max-width: 400px; height: auto;">
    </div>
</div>

@endsection
            