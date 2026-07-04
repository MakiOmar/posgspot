{{-- Modal partial loaded via .btn-modal into .view_modal --}}
<div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
        {!! Form::open([
            'url' => action([\App\Http\Controllers\Storefront\StorefrontTranslationController::class, 'productsUpdate'], $product->id),
            'method' => 'post',
            'id' => 'storefront_product_translation_form',
        ]) !!}

        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
            <h4 class="modal-title">Arabic translation — {{ $product->name }}</h4>
        </div>

        <div class="modal-body">
            <div class="row">
                <div class="col-md-5">
                    <h4 class="text-muted" style="margin-top: 0;">POS (read-only)</h4>
                    <p><strong>Name:</strong> {{ $product->name }}</p>
                    <p><strong>Slug:</strong> {{ $product->slug }}</p>
                    <p><strong>Description:</strong></p>
                    <div class="well well-sm" style="max-height: 160px; overflow-y: auto;">
                        {!! nl2br(e($product->product_description ?? '')) !!}
                    </div>
                </div>
                <div class="col-md-7">
                    <h4 class="text-muted" style="margin-top: 0;">Arabic overlay</h4>
                    <div class="form-group">
                        {!! Form::label('name', 'Arabic name') !!}
                        {!! Form::text('name', $ar->name ?? '', ['class' => 'form-control', 'placeholder' => 'Arabic name']) !!}
                    </div>
                    <div class="form-group">
                        {!! Form::label('slug', 'Arabic slug (optional)') !!}
                        {!! Form::text('slug', $ar->slug ?? '', ['class' => 'form-control', 'placeholder' => 'Optional']) !!}
                    </div>
                    <div class="form-group">
                        {!! Form::label('product_description', 'Arabic description') !!}
                        {!! Form::textarea('product_description', $ar->product_description ?? '', ['class' => 'form-control', 'rows' => 5]) !!}
                    </div>

                    @if ($product->type === 'variable')
                        <h4>Variation names</h4>
                        @foreach ($product->product_variations as $pv)
                            @foreach ($pv->variations as $variation)
                                @if ($variation->name !== 'DUMMY')
                                    @php $varAr = $variation->storefrontTranslations->firstWhere('locale', 'ar'); @endphp
                                    <div class="form-group">
                                        <label>{{ $variation->name }} ({{ $variation->sub_sku }})</label>
                                        <input
                                            type="text"
                                            name="variations[{{ $variation->id }}]"
                                            class="form-control"
                                            value="{{ $varAr->name ?? '' }}"
                                        >
                                    </div>
                                @endif
                            @endforeach
                        @endforeach
                    @endif
                </div>
            </div>
        </div>

        <div class="modal-footer">
            <button type="submit" class="btn btn-primary">Save Arabic</button>
            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
        </div>

        {!! Form::close() !!}
    </div>
</div>
