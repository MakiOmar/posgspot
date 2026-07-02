@extends('layouts.app')
@section('title', 'Storefront product translations')

@section('content')
<section class="content-header">
    <h1>Storefront product translations (Arabic)</h1>
</section>

<section class="content">
    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title">Products</h3>
            <div class="box-tools">
                <a href="{{ action([\App\Http\Controllers\Storefront\StorefrontTranslationController::class, 'categoriesIndex']) }}" class="btn btn-default btn-sm">Categories</a>
                <a href="{{ action([\App\Http\Controllers\Storefront\StorefrontTranslationController::class, 'brandsIndex']) }}" class="btn btn-default btn-sm">Brands</a>
            </div>
        </div>
        <div class="box-body table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>POS name (EN)</th>
                        <th>SKU</th>
                        <th>Arabic</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        @php $ar = $product->storefrontTranslations->first(); @endphp
                        <tr>
                            <td>{{ $product->name }}</td>
                            <td>{{ $product->sku }}</td>
                            <td>
                                @if ($ar)
                                    <span class="label label-success">Complete</span> {{ $ar->name }}
                                @else
                                    <span class="label label-warning">Missing</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ action([\App\Http\Controllers\Storefront\StorefrontTranslationController::class, 'productsEdit'], $product->id) }}" class="btn btn-xs btn-primary">Edit AR</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4">No sellable products.</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $products->links() }}
        </div>
    </div>
</section>
@endsection
