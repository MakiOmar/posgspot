@extends('layouts.app')
@section('title', 'Sell request #'.$sellRequest->id)

@section('content')
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">
            Sell request #{{ $sellRequest->id }}
            <small class="text-muted">{{ ucfirst($sellRequest->type) }}</small>
        </h1>
        <ol class="breadcrumb">
            <li>
                <a href="{{ action([\App\Http\Controllers\StorefrontSellRequestController::class, 'index']) }}">Sell to us</a>
            </li>
            <li class="active">#{{ $sellRequest->id }}</li>
        </ol>
    </section>

    <section class="content">
        <div class="row">
            <div class="col-md-8">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Customer</h3>
                    </div>
                    <div class="box-body">
                        <dl class="dl-horizontal">
                            <dt>Name</dt><dd>{{ $sellRequest->name }}</dd>
                            <dt>Email</dt><dd>{{ $sellRequest->email ?: '—' }}</dd>
                            <dt>Phone</dt><dd>{{ $sellRequest->phone ?: '—' }}</dd>
                            <dt>City</dt><dd>{{ $sellRequest->city ?: '—' }}</dd>
                            <dt>Contact ID</dt>
                            <dd>
                                @if($sellRequest->contact)
                                    #{{ $sellRequest->contact_id }} — {{ $sellRequest->contact->name }}
                                @else
                                    #{{ $sellRequest->contact_id }}
                                @endif
                            </dd>
                        </dl>
                    </div>
                </div>

                <div class="box box-default">
                    <div class="box-header with-border">
                        <h3 class="box-title">Request details</h3>
                    </div>
                    <div class="box-body">
                        <dl class="dl-horizontal">
                            <dt>Purchased from us</dt>
                            <dd>{{ $sellRequest->purchased_from_us ? 'Yes' : 'No' }}</dd>
                            <dt>Invoice</dt>
                            <dd>{{ $sellRequest->invoice_no ?: '—' }}</dd>
                            <dt>Linked order</dt>
                            <dd>
                                @if($sellRequest->transaction_id)
                                    Transaction #{{ $sellRequest->transaction_id }}
                                    @if($sellRequest->transaction)
                                        ({{ $sellRequest->transaction->invoice_no }})
                                    @endif
                                @else
                                    —
                                @endif
                            </dd>
                            <dt>Notes</dt>
                            <dd>{!! $sellRequest->notes ? nl2br(e($sellRequest->notes)) : '—' !!}</dd>
                        </dl>
                        @if(!empty($sellRequest->details) && is_array($sellRequest->details))
                            <h4>Type-specific</h4>
                            <ul>
                                @foreach($sellRequest->details as $key => $value)
                                    <li><strong>{{ $key }}:</strong> {{ is_scalar($value) ? $value : json_encode($value) }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>

                @if($sellRequest->media->isNotEmpty())
                    <div class="box box-default">
                        <div class="box-header with-border">
                            <h3 class="box-title">Photos</h3>
                        </div>
                        <div class="box-body">
                            <div class="row">
                                @foreach($sellRequest->media as $media)
                                    <div class="col-sm-4" style="margin-bottom: 12px;">
                                        <a href="{{ $media->url }}" target="_blank" rel="noopener">
                                            <img src="{{ $media->url }}" alt="Photo {{ $media->id }}" class="img-responsive img-thumbnail" style="max-height: 180px;">
                                        </a>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <div class="col-md-4">
                <div class="box box-success">
                    <div class="box-header with-border">
                        <h3 class="box-title">Status</h3>
                    </div>
                    <div class="box-body">
                        {!! Form::open(['url' => action([\App\Http\Controllers\StorefrontSellRequestController::class, 'updateStatus'], [$sellRequest->id]), 'method' => 'POST']) !!}
                            <div class="form-group">
                                {!! Form::label('status', 'Update status') !!}
                                {!! Form::select('status', array_combine($statuses, array_map('ucfirst', $statuses)), $sellRequest->status, ['class' => 'form-control']) !!}
                            </div>
                            <button type="submit" class="tw-dw-btn tw-dw-btn-primary">Save</button>
                        {!! Form::close() !!}
                        <p class="help-block" style="margin-top: 12px;">
                            Submitted {{ @format_datetime($sellRequest->created_at) }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
