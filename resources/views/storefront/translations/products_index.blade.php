@extends('layouts.app')
@section('title', 'Storefront product translations')

@section('content')
<section class="content-header">
    <h1>Storefront product translations (Arabic)</h1>
</section>

<section class="content">
    @if (session('status'))
        <div class="alert alert-success">{{ session('status')['msg'] ?? 'Saved.' }}</div>
    @endif

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
                                <button
                                    type="button"
                                    class="btn btn-xs btn-primary btn-modal"
                                    data-href="{{ action([\App\Http\Controllers\Storefront\StorefrontTranslationController::class, 'productsEdit'], $product->id) }}"
                                    data-container=".view_modal"
                                >
                                    Edit AR
                                </button>
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

@section('javascript')
<script type="text/javascript">
    $(document).on('submit', 'form#storefront_product_translation_form', function (e) {
        e.preventDefault();
        var form = $(this);
        var submitBtn = form.find('button[type="submit"]');

        $.ajax({
            method: 'POST',
            url: form.attr('action'),
            dataType: 'json',
            data: form.serialize(),
            beforeSend: function () {
                if (typeof __disable_submit_button === 'function') {
                    __disable_submit_button(submitBtn);
                } else {
                    submitBtn.prop('disabled', true);
                }
            },
            success: function (result) {
                if (result.success) {
                    $('div.view_modal').modal('hide');
                    toastr.success(result.msg);
                    window.location.reload();
                } else {
                    toastr.error(result.msg || @json(__('messages.something_went_wrong')));
                    submitBtn.prop('disabled', false);
                }
            },
            error: function (xhr) {
                var msg = @json(__('messages.something_went_wrong'));
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                } else if (xhr.responseJSON && xhr.responseJSON.msg) {
                    msg = xhr.responseJSON.msg;
                }
                toastr.error(msg);
                submitBtn.prop('disabled', false);
            },
        });
    });
</script>
@endsection
