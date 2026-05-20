<div class="row text-center">
	@php
		$logo_path = business_logo_url();
	@endphp
	@if(!empty($logo_path))
		<div class="col-xs-12">
			<img src="{{ $logo_path }}" class="img-rounded" alt="Logo" width="150" style="margin-bottom: 30px;">
		</div>
	@elseif(file_exists(public_path('uploads/logo.png')))
		<div class="col-xs-12">
			<img src="/uploads/logo.png" class="img-rounded" alt="Logo" width="150" style="margin-bottom: 30px;">
		</div>
	@else
    	<h1 class="text-center page-header">{{ config('app.name', 'ultimatePOS') }}</h1>
    @endif
</div>
