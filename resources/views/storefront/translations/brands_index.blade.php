@extends('layouts.app')
@section('title', 'Storefront brand translations')

@section('content')
<section class="content-header"><h1>Storefront brand translations (Arabic)</h1></section>
<section class="content">
    <div class="box box-primary">
        <div class="box-header with-border">
            <div class="box-tools">
                <a href="{{ action([\App\Http\Controllers\Storefront\StorefrontTranslationController::class, 'productsIndex']) }}" class="btn btn-default btn-sm">Products</a>
                <a href="{{ action([\App\Http\Controllers\Storefront\StorefrontTranslationController::class, 'categoriesIndex']) }}" class="btn btn-default btn-sm">Categories</a>
            </div>
        </div>
        <div class="box-body table-responsive">
            <table class="table table-bordered table-striped">
                <thead><tr><th>POS name</th><th>Arabic</th><th></th></tr></thead>
                <tbody>
                    @foreach ($brands as $brand)
                        <tr>
                            <td>{{ $brand['name'] }}</td>
                            <td>
                                @if ($brand['has_ar'])
                                    <span class="label label-success">Complete</span> {{ $brand['ar_name'] }}
                                @else
                                    <span class="label label-warning">Missing</span>
                                @endif
                            </td>
                            <td><a href="{{ action([\App\Http\Controllers\Storefront\StorefrontTranslationController::class, 'brandsEdit'], $brand['id']) }}" class="btn btn-xs btn-primary">Edit AR</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection
