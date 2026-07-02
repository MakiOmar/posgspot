@extends('layouts.app')
@section('title', 'Edit category Arabic')

@section('content')
<section class="content-header"><h1>Arabic — {{ $category->name }}</h1></section>
<section class="content">
    @if (session('status'))<div class="alert alert-success">{{ session('status')['msg'] ?? 'Saved.' }}</div>@endif
    <div class="row">
        <div class="col-md-6">
            <div class="box box-default">
                <div class="box-body">
                    <p><strong>Name:</strong> {{ $category->name }}</p>
                    <p><strong>Slug:</strong> {{ $category->slug }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="box box-primary">
                {!! Form::open(['url' => action([\App\Http\Controllers\Storefront\StorefrontTranslationController::class, 'categoriesUpdate'], $category->id), 'method' => 'post']) !!}
                <div class="box-body">
                    <div class="form-group">
                        {!! Form::label('name', 'Arabic name') !!}
                        {!! Form::text('name', $ar->name ?? '', ['class' => 'form-control']) !!}
                    </div>
                    <div class="form-group">
                        {!! Form::label('slug', 'Arabic slug') !!}
                        {!! Form::text('slug', $ar->slug ?? '', ['class' => 'form-control']) !!}
                    </div>
                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-primary">Save</button>
                    <a href="{{ action([\App\Http\Controllers\Storefront\StorefrontTranslationController::class, 'categoriesIndex']) }}" class="btn btn-default">Back</a>
                </div>
                {!! Form::close() !!}
            </div>
        </div>
    </div>
</section>
@endsection
