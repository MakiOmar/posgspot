@extends('layouts.app')
@section('title', 'Product request #'.$productRequest->id)

@section('content')
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">
            Product request #{{ $productRequest->id }}
        </h1>
        <ol class="breadcrumb">
            <li>
                <a href="{{ action([\App\Http\Controllers\StorefrontProductRequestController::class, 'index']) }}">Product requests</a>
            </li>
            <li class="active">#{{ $productRequest->id }}</li>
        </ol>
    </section>

    <section class="content">
        <div class="row">
            <div class="col-md-8">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Request</h3>
                    </div>
                    <div class="box-body">
                        <dl class="dl-horizontal">
                            <dt>Product</dt><dd>{{ $productRequest->product_name }}</dd>
                            <dt>Platform</dt><dd>{{ $productRequest->platform ? strtoupper($productRequest->platform) : '—' }}</dd>
                            <dt>Notes</dt>
                            <dd>{!! $productRequest->notes ? nl2br(e($productRequest->notes)) : '—' !!}</dd>
                        </dl>
                    </div>
                </div>

                <div class="box box-default">
                    <div class="box-header with-border">
                        <h3 class="box-title">Customer</h3>
                    </div>
                    <div class="box-body">
                        <dl class="dl-horizontal">
                            <dt>Name</dt><dd>{{ $productRequest->name }}</dd>
                            <dt>Email</dt><dd>{{ $productRequest->email }}</dd>
                            <dt>Phone</dt><dd>{{ $productRequest->phone ?: '—' }}</dd>
                            <dt>Contact ID</dt>
                            <dd>
                                @if($productRequest->contact_id)
                                    #{{ $productRequest->contact_id }}
                                    @if($productRequest->contact)
                                        — {{ $productRequest->contact->name }}
                                    @endif
                                @else
                                    —
                                @endif
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="box box-solid">
                    <div class="box-header with-border">
                        <h3 class="box-title">Status</h3>
                    </div>
                    <div class="box-body">
                        {!! Form::open(['url' => action([\App\Http\Controllers\StorefrontProductRequestController::class, 'updateStatus'], $productRequest->id), 'method' => 'post', 'id' => 'product_request_status_form']) !!}
                        <div class="form-group">
                            {!! Form::select('status', array_combine($statuses, array_map('ucfirst', $statuses)), $productRequest->status, ['class' => 'form-control', 'id' => 'product_request_status']) !!}
                        </div>
                        <button type="submit" class="tw-dw-btn tw-dw-btn-primary btn-block">Update status</button>
                        {!! Form::close() !!}
                        <p class="help-block tw-mt-2">Submitted {{ @format_datetime($productRequest->created_at) }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
