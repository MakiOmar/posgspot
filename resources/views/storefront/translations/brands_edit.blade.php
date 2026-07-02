@extends('layouts.app')
@section('title', 'Edit brand Arabic')

@section('content')
<section class="content-header"><h1>Arabic — {{ $brand->name }}</h1></section>
<section class="content">
    @if (session('status'))<div class="alert alert-success">{{ session('status')['msg'] ?? 'Saved.' }}</div>@endif
    <div class="row">
        <div class="col-md-6"><div class="box box-default"><div class="box-body"><p><strong>Name:</strong> {{ $brand->name }}</p></div></div></div>
        <div class="col-md-6">
            <div class="box box-primary">
                {!! Form::open(['url' => action([\App\Http\Controllers\Storefront\StorefrontTranslationController::class, 'brandsUpdate'], $brand->id), 'method' => 'post']) !!}
                <div class="box-body">
                    <div class="form-group">
                        {!! Form::label('name', 'Arabic name') !!}
                        {!! Form::text('name', $ar->name ?? '', ['class' => 'form-control']) !!}
                    </div>
                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-primary">Save</button>
                    <a href="{{ action([\App\Http\Controllers\Storefront\StorefrontTranslationController::class, 'brandsIndex']) }}" class="btn btn-default">Back</a>
                </div>
                {!! Form::close() !!}
            </div>
        </div>
    </div>
</section>
@endsection
