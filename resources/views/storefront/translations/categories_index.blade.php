@extends('layouts.app')
@section('title', 'Storefront category translations')

@section('content')
<section class="content-header">
    <h1>Storefront category translations (Arabic)</h1>
</section>

<section class="content">
    <div class="box box-primary">
        <div class="box-header with-border">
            <div class="box-tools">
                <a href="{{ action([\App\Http\Controllers\Storefront\StorefrontTranslationController::class, 'productsIndex']) }}" class="btn btn-default btn-sm">Products</a>
                <a href="{{ action([\App\Http\Controllers\Storefront\StorefrontTranslationController::class, 'brandsIndex']) }}" class="btn btn-default btn-sm">Brands</a>
            </div>
        </div>
        <div class="box-body table-responsive">
            <table class="table table-bordered table-striped">
                <thead><tr><th>POS name</th><th>Slug</th><th>Arabic</th><th></th></tr></thead>
                <tbody>
                    @foreach ($categories as $category)
                        <tr>
                            <td>{{ $category['name'] }}</td>
                            <td>{{ $category['slug'] }}</td>
                            <td>
                                @if ($category['has_ar'])
                                    <span class="label label-success">Complete</span> {{ $category['ar_name'] }}
                                @else
                                    <span class="label label-warning">Missing</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ action([\App\Http\Controllers\Storefront\StorefrontTranslationController::class, 'categoriesEdit'], $category['id']) }}" class="btn btn-xs btn-primary">Edit AR</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection
