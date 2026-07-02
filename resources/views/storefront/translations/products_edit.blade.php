@extends('layouts.app')
@section('title', 'Edit product Arabic')

@section('content')
<section class="content-header">
    <h1>Arabic translation — {{ $product->name }}</h1>
</section>

<section class="content">
    @if (session('status'))
        <div class="alert alert-success">{{ session('status')['msg'] ?? 'Saved.' }}</div>
    @endif

    <div class="row">
        <div class="col-md-6">
            <div class="box box-default">
                <div class="box-header"><h3 class="box-title">POS (read-only)</h3></div>
                <div class="box-body">
                    <p><strong>Name:</strong> {{ $product->name }}</p>
                    <p><strong>Slug:</strong> {{ $product->slug }}</p>
                    <p><strong>Description:</strong></p>
                    <div>{!! nl2br(e($product->product_description ?? '')) !!}</div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="box box-primary">
                <div class="box-header"><h3 class="box-title">Arabic overlay</h3></div>
                {!! Form::open(['url' => action([\App\Http\Controllers\Storefront\StorefrontTranslationController::class, 'productsUpdate'], $product->id), 'method' => 'post']) !!}
                <div class="box-body">
                    <div class="form-group">
                        {!! Form::label('name', 'Arabic name') !!}
                        {!! Form::text('name', $ar->name ?? '', ['class' => 'form-control']) !!}
                    </div>
                    <div class="form-group">
                        {!! Form::label('slug', 'Arabic slug (optional)') !!}
                        {!! Form::text('slug', $ar->slug ?? '', ['class' => 'form-control']) !!}
                    </div>
                    <div class="form-group">
                        {!! Form::label('product_description', 'Arabic description') !!}
                        {!! Form::textarea('product_description', $ar->product_description ?? '', ['class' => 'form-control', 'rows' => 6]) !!}
                    </div>

                    @if ($product->type === 'variable')
                        <h4>Variation names</h4>
                        @foreach ($product->product_variations as $pv)
                            @foreach ($pv->variations as $variation)
                                @if ($variation->name !== 'DUMMY')
                                    @php $varAr = $variation->storefrontTranslations->firstWhere('locale', 'ar'); @endphp
                                    <div class="form-group">
                                        <label>{{ $variation->name }} ({{ $variation->sub_sku }})</label>
                                        <input type="text" name="variations[{{ $variation->id }}]" class="form-control" value="{{ $varAr->name ?? '' }}">
                                    </div>
                                @endif
                            @endforeach
                        @endforeach
                    @endif
                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-primary">Save Arabic</button>
                    <a href="{{ action([\App\Http\Controllers\Storefront\StorefrontTranslationController::class, 'productsIndex']) }}" class="btn btn-default">Back</a>
                </div>
                {!! Form::close() !!}
            </div>
        </div>
    </div>
</section>
@endsection
