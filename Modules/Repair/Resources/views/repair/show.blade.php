<div class="modal-dialog modal-xl no-print" role="document">
  <div class="modal-content">
    <div class="modal-header">
    <button type="button" class="close no-print" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    <h4 class="modal-title" id="modalTitle"> @lang('repair::lang.repair_details') (<b>@lang('sale.invoice_no'):</b> {{ $sell->invoice_no }})
    </h4>
</div>
<div class="modal-body">
    @php
      $custom_labels = json_decode(session('business.custom_labels'), true);
    @endphp
    <div class="row">
      <div class="col-xs-12">
          <p class="pull-right"><b>@lang('messages.date'):</b> {{ @format_date($sell->transaction_date) }}</p>
      </div>
    </div>
    <div class="row">
      <div class="col-sm-4">
        <b>{{ __('sale.invoice_no') }}:</b> #{{ $sell->invoice_no }}<br>
        <b>{{ __('sale.status') }}:</b> <span class="label" style="background-color: {{$sell->repair_status_color}};">{{$sell->repair_status}}</span>
        <br>
        <b>{{ __('sale.payment_status') }}:</b> {{ ucfirst( $sell->payment_status ) }}<br>
        @if(!empty($sell->repair_due_date))
          <b>@lang('repair::lang.delivery_date'):</b> {{ @format_datetime($sell->repair_due_date) }}<br>
        @endif
        @if(!empty($sell->repair_completed_on))
          <b>@lang('repair::lang.repair_completed_on'):</b> {{ @format_datetime($sell->repair_completed_on) }}<br>
        @endif
        @if(!empty($custom_labels['sell']['custom_field_1']))
          <strong>{{$custom_labels['sell']['custom_field_1'] ?? ''}}: </strong> {{$sell->custom_field_1}}<br>
        @endif
        @if(!empty($custom_labels['sell']['custom_field_2']))
          <strong>{{$custom_labels['sell']['custom_field_2'] ?? ''}}: </strong> {{$sell->custom_field_2}}<br>
        @endif
        @if(!empty($custom_labels['sell']['custom_field_3']))
          <strong>{{$custom_labels['sell']['custom_field_3'] ?? ''}}: </strong> {{$sell->custom_field_3}}<br>
        @endif
        @if(!empty($custom_labels['sell']['custom_field_4']))
          <strong>{{$custom_labels['sell']['custom_field_4'] ?? ''}}: </strong> {{$sell->custom_field_4}}<br>
        @endif
        @if($sell->document_path)
          <br>
          <a href="{{$sell->document_path}}"
          download="{{$sell->document_name}}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-accent no-print">
            <i class="fa fa-download"></i>
            &nbsp;{{ __('purchase.download_document') }}
          </a>
        @endif
      </div>
      <div class="col-sm-4">
        @if(!empty($sell->contact->supplier_business_name))
          {{ $sell->contact->supplier_business_name }}<br>
        @endif
        <b>{{ __('sale.customer_name') }}:</b> {{ $sell->contact->name }}<br>
        <b>{{ __('business.address') }}:</b><br>
        @if(!empty($sell->billing_address()))
          {{$sell->billing_address()}}
        @else
          {!! $sell->contact->contact_address !!}
          @if($sell->contact->mobile)
          <br>
              {{__('contact.mobile')}}: {{ $sell->contact->mobile }}
          @endif
          @if($sell->contact->alternate_number)
          <br>
              {{__('contact.alternate_contact_number')}}: {{ $sell->contact->alternate_number }}
          @endif
          @if($sell->contact->landline)
            <br>
              {{__('contact.landline')}}: {{ $sell->contact->landline }}
          @endif
          @if($sell->contact->email)
            <br>
              {{__('business.email')}}: {{ $sell->contact->email }}
          @endif
        @endif
      </div>
      <div class="col-sm-4">
        <strong>@lang('product.brand'): </strong> {{$sell->manufacturer}}<br>
        <strong>@lang('repair::lang.device'): </strong> {{$sell->repair_device}}<br>
        <strong>@lang('repair::lang.model'): </strong> {{$sell->repair_model}}<br>
        <strong>@lang('repair::lang.serial_no'): </strong> {{$sell->repair_serial_no}}<br>
        @if(in_array('service_staff' ,$enabled_modules))
          <strong>@lang('repair::lang.technician'): </strong> {{$sell->service_staff}}<br>
        @endif

        @if(!empty($warranty_expires_in))
          <strong>@lang('repair::lang.warranty'): </strong> {{$sell->warranty_name}}
          <small class="help-block">( @lang('repair::lang.expires_in') {{$warranty_expires_in}} )</small>
        @endif
        @if(in_array('types_of_service' ,$enabled_modules) && !empty($sell->types_of_service))
          <strong>@lang('lang_v1.types_of_service'):</strong>
          {{$sell->types_of_service->name}}<br>
        @endif
        @if(in_array('types_of_service' ,$enabled_modules) && !empty($sell->types_of_service->enable_custom_fields))
          <strong>{{ $custom_labels['types_of_service']['custom_field_1'] ?? __('lang_v1.service_custom_field_1' )}}:</strong>
          {{$sell->service_custom_field_1}}<br>
          <strong>{{ $custom_labels['types_of_service']['custom_field_2'] ?? __('lang_v1.service_custom_field_2' )}}:</strong>
          {{$sell->service_custom_field_2}}<br>
          <strong>{{ $custom_labels['types_of_service']['custom_field_3'] ?? __('lang_v1.service_custom_field_3' )}}:</strong>
          {{$sell->service_custom_field_3}}<br>
          <strong>{{ $custom_labels['types_of_service']['custom_field_4'] ?? __('lang_v1.service_custom_field_4' )}}:</strong>
          {{$sell->service_custom_field_4}}<br>
          <strong>{{ $custom_labels['types_of_service']['custom_field_5'] ?? __('lang_v1.custom_field', ['number' => 5])}}:</strong>
          {{$sell->service_custom_field_5}}<br>
          <strong>{{ $custom_labels['types_of_service']['custom_field_6'] ?? __('lang_v1.custom_field', ['number' => 6])}}:</strong>
          {{$sell->service_custom_field_6}}
        @endif
      </div>
    </div>
    <div class="row">
      <div class="col-sm-12 col-xs-12">
        <strong>@lang('sale.shipping'):</strong>
        <span class="label @if(!empty($sell->shipping_status) && !empty($shipping_status_colors[$sell->shipping_status])) {{ $shipping_status_colors[$sell->shipping_status] }} @else bg-gray @endif">{{ $shipping_statuses[$sell->shipping_status] ?? '' }}</span><br>
        @if(!empty($sell->shipping_address()))
          {{$sell->shipping_address()}}
        @else
          {{$sell->shipping_address ?? '--'}}
        @endif
        @if(!empty($sell->delivered_to))
          <br><strong>@lang('lang_v1.delivered_to'): </strong> {{$sell->delivered_to}}
        @endif
      </div>
    </div>
    <br>
    <div class="row">
      <div class="col-sm-12 col-xs-12">
        <h4>{{ __('sale.products') }}:</h4>
      </div>

      <div class="col-sm-12 col-xs-12">
        <div class="table-responsive">
          @include('sale_pos.partials.sale_line_details')
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-sm-12">
        <strong>{{ __('repair::lang.defect')}}:</strong><br>
        <p class="well well-sm no-shadow">
            @php
                $defects = json_decode($sell->repair_defects, true);
            @endphp
            @if(!empty($defects))
                @foreach($defects as $product_defect)
                    {{$product_defect['value']}}
                    @if(!$loop->last)
                        {{','}}
                    @endif
                @endforeach
            @endif
        </p>
      </div>
      <div class="clearfix"></div>
      <div class="col-sm-6">
        <!-- Pre-repair checklist: expanded so invoice view matches printed details -->
        <div class="box box-default box-solid">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('repair::lang.pre_repair_checklist') }}:</h3>

                <div class="box-tools pull-right">
                    <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
                    </button>
                </div>
                <!-- /.box-tools -->
            </div>
            <!-- /.box-header -->
            <div class="box-body">
                @if(!empty($sell->repair_checklist))
                    @php
                        $selected_checklist = json_decode($sell->repair_checklist, true);
                    @endphp
                    <div class="row">
                        @foreach($checklists as $check)
                            <div class="col-xs-4">
                                @if(isset($selected_checklist[$check]))
                                  @if($selected_checklist[$check] == 'yes')
                                      <i class="fas fa-check-square text-success"></i>
                                  @elseif($selected_checklist[$check] == 'no')
                                    <i class="fas fa-window-close text-danger"></i>
                                  @elseif($selected_checklist[$check] == 'not_applicable')
                                    <i class="fas fa-square"></i>
                                  @endif
                                  @else
                                  <i class="fas fa-square"></i>
                                @endif
                                {{$check}}
                                <br>
                                <br>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
            <!-- /.box-body -->
        </div>
      </div>
      <div class="col-sm-6">
        <!-- Payment lines and totals: expanded so amounts and methods are visible without extra clicks -->
        <div class="box box-default box-solid">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('sale.payment_info') }}:</h3>

                <div class="box-tools pull-right">
                    <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
                    </button>
                </div>
                <!-- /.box-tools -->
            </div>
            <!-- /.box-header -->
            <div class="box-body">
                <div class="table-responsive">
                    <table class="table bg-gray">
                        <tr>
                            <th>#</th>
                            <th>{{ __('messages.date') }}</th>
                            <th>{{ __('purchase.ref_no') }}</th>
                            <th>{{ __('sale.amount') }}</th>
                            <th>{{ __('sale.payment_mode') }}</th>
                            <th>{{ __('sale.payment_note') }}</th>
                        </tr>
                        @php
                          $total_paid = 0;
                        @endphp
                        @foreach($sell->payment_lines as $payment_line)
                            @php
                                if($payment_line->is_return == 1){
                                    $total_paid -= $payment_line->amount;
                                } else {
                                    $total_paid += $payment_line->amount;
                                }
                            @endphp
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ @format_date($payment_line->paid_on) }}</td>
                                <td>{{ $payment_line->payment_ref_no }}</td>
                                <td><span class="display_currency" data-currency_symbol="true">{{ $payment_line->amount }}</span></td>
                                <td>
                                  {{ $payment_types[$payment_line->method] ?? $payment_line->method }}
                                  @if($payment_line->is_return == 1)
                                    <br/>
                                    ( {{ __('lang_v1.change_return') }} )
                                  @endif
                                </td>
                                <td>@if($payment_line->note) 
                                  {{ ucfirst($payment_line->note) }}
                                  @else
                                  --
                                  @endif
                                </td>
                            </tr>
                        @endforeach
                    </table>
                </div>
                <div class="table-responsive">
                    <table class="table bg-gray">
                        <tr>
                            <th>{{ __('sale.total') }}: </th>
                            <td></td>
                            <td><span class="display_currency pull-right" data-currency_symbol="true">{{ $sell->total_before_tax }}</span></td>
                        </tr>
                        <tr>
                            <th>{{ __('sale.discount') }}:</th>
                            <td><b>(-)</b></td>
                            <td><div class="pull-right"><span class="display_currency" @if( $sell->discount_type == 'fixed') data-currency_symbol="true" @endif>{{ $sell->discount_amount }}</span> @if( $sell->discount_type == 'percentage') {{ '%'}} @endif</div></td>
                        </tr>
                        @if(in_array('types_of_service' ,$enabled_modules) && !empty($sell->packing_charge))
                          <tr>
                            <th>{{ __('lang_v1.packing_charge') }}:</th>
                            <td><b>(+)</b></td>
                            <td><div class="pull-right"><span class="display_currency" @if( $sell->packing_charge_type == 'fixed') data-currency_symbol="true" @endif>{{ $sell->packing_charge }}</span> @if( $sell->packing_charge_type == 'percent') {{ '%'}} @endif </div></td>
                          </tr>
                        @endif
                        @if(session('business.enable_rp') == 1 && !empty($sell->rp_redeemed) )
                          <tr>
                            <th>{{session('business.rp_name')}}:</th>
                            <td><b>(-)</b></td>
                            <td> <span class="display_currency pull-right" data-currency_symbol="true">{{ $sell->rp_redeemed_amount }}</span></td>
                          </tr>
                        @endif
                        <tr>
                            <th>{{ __('sale.order_tax') }}:</th>
                            <td><b>(+)</b></td>
                            <td class="text-right">
                                @if(!empty($order_taxes))
                                  @foreach($order_taxes as $k => $v)
                                    <strong><small>{{$k}}</small></strong> - <span class="display_currency pull-right" data-currency_symbol="true">{{ $v }}</span><br>
                                  @endforeach
                                @else
                                0.00
                                @endif
                            </td>
                        </tr>
                        @if(!empty($line_taxes))
                        <tr>
                            <th>{{ __('lang_v1.line_taxes') }}:</th>
                            <td></td>
                            <td class="text-right">
                                @foreach($line_taxes as $k => $v)
                                  <strong><small>{{$k}}</small></strong> - <span class="display_currency pull-right" data-currency_symbol="true">{{ $v }}</span><br>
                                @endforeach
                            </td>
                        </tr>
                        @endif
                        <tr>
                            <th>{{ __('sale.shipping') }}: @if($sell->shipping_details)({{$sell->shipping_details}}) @endif</th>
                            <td><b>(+)</b></td>
                            <td><span class="display_currency pull-right" data-currency_symbol="true">{{ $sell->shipping_charges }}</span></td>
                        </tr>
                        @if( !empty( $sell->additional_expense_value_1 )  && !empty( $sell->additional_expense_key_1 ))
                          <tr>
                            <th>{{ $sell->additional_expense_key_1 }}:</th>
                            <td><b>(+)</b></td>
                            <td><span class="display_currency pull-right" data-currency_symbol="true">{{ $sell->additional_expense_value_1 }}</span></td>
                          </tr>
                        @endif
                        @if( !empty( $sell->additional_expense_value_2 )  && !empty( $sell->additional_expense_key_2 ))
                          <tr>
                            <th>{{ $sell->additional_expense_key_2 }}:</th>
                            <td><b>(+)</b></td>
                            <td><span class="display_currency pull-right" data-currency_symbol="true">{{ $sell->additional_expense_value_2 }}</span></td>
                          </tr>
                        @endif
                        @if( !empty( $sell->additional_expense_value_3 )  && !empty( $sell->additional_expense_key_3 ))
                          <tr>
                            <th>{{ $sell->additional_expense_key_3 }}:</th>
                            <td><b>(+)</b></td>
                            <td><span class="display_currency pull-right" data-currency_symbol="true">{{ $sell->additional_expense_value_3 }}</span></td>
                          </tr>
                        @endif
                        @if( !empty( $sell->additional_expense_value_4 ) && !empty( $sell->additional_expense_key_4 ))
                          <tr>
                            <th>{{ $sell->additional_expense_key_4 }}:</th>
                            <td><b>(+)</b></td>
                            <td><span class="display_currency pull-right" data-currency_symbol="true">{{ $sell->additional_expense_value_4 }}</span></td>
                          </tr>
                        @endif
                        <tr>
                            <th>{{ __('lang_v1.round_off') }}: </th>
                            <td></td>
                            <td><span class="display_currency pull-right" data-currency_symbol="true">{{ $sell->round_off_amount }}</span></td>
                        </tr>
                        <tr>
                            <th>{{ __('sale.total_payable') }}: </th>
                            <td></td>
                            <td><span class="display_currency pull-right" data-currency_symbol="true">{{ $sell->final_total }}</span></td>
                        </tr>
                        <tr>
                            <th>{{ __('sale.total_paid') }}:</th>
                            <td></td>
                            <td><span class="display_currency pull-right" data-currency_symbol="true" >{{ $total_paid }}</span></td>
                        </tr>
                        <tr>
                            <th>{{ __('sale.total_remaining') }}:</th>
                            <td></td>
                            <td>
                              @php
                                $total_paid = (string) $total_paid;
                              @endphp
                              <span class="display_currency pull-right" data-currency_symbol="true" >{{ $sell->final_total - $total_paid }}</span></td>
                        </tr>
                    </table>
                </div>

            </div>
            <!-- /.box-body -->
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-sm-6">
        <strong>{{ __( 'sale.sell_note')}}:</strong><br>
        <p class="well well-sm no-shadow bg-gray">
          @if($sell->additional_notes)
            {!! nl2br($sell->additional_notes) !!}
          @else
            --
          @endif
        </p>
      </div>
      <div class="col-sm-6">
        <strong>{{ __( 'sale.staff_note')}}:</strong><br>
        <p class="well well-sm no-shadow bg-gray">
          @if($sell->staff_note)
            {!! nl2br($sell->staff_note) !!}
          @else
            --
          @endif
        </p>
      </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="box box-default box-solid">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('repair::lang.activities') }}:</h3>

                    <div class="box-tools pull-right">
                        <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
                        </button>
                    </div>
                    <!-- /.box-tools -->
                </div>
                <!-- /.box-header -->
                @includeIf('repair::repair.partials.activities')
            </div>
        </div>
        <div class="col-md-6">
            <div class="box box-default box-solid">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('lang_v1.documents') }}:</h3>

                    <div class="box-tools pull-right">
                        <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
                        </button>
                    </div>
                    <!-- /.box-tools -->
                </div>
                <!-- /.box-header -->
                <div class="box-body">
                    <table class="table table-condensed bg-gray">
                        <tr>
                            <th>@lang('lang_v1.name')</th>
                            <th>@lang('messages.view')</th>
                            <th>@lang('messages.delete')</th>
                        </tr>
                        @forelse($sell->media as $media)
                        <tr>
                            <td>{{$media->display_name}}</td>
                            <td><a href="{{$media->display_url}}" target="_blank" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline  tw-dw-btn-info"><i class="fa fa-external-link"></i></a></td>
                            <td><a href="{{action([\Modules\Repair\Http\Controllers\RepairController::class, 'deleteMedia'], $media->id)}}"" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline  tw-dw-btn-error delete_media"><i class="fa fa-trash"></i></a></td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3">@lang('purchase.no_records_found')</td>
                        </tr>
                        @endforelse
                    </table>
                </div>
                <!-- /.box-body -->
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6">
            <div class="box box-default box-solid">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('repair::lang.pass_code_of_device') }}:</h3>

                    <div class="box-tools pull-right">
                        <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
                        </button>
                    </div>
                    <!-- /.box-tools -->
                </div>
                <!-- /.box-header -->
                <div class="box-body">
                    <div class="row">
                        <div class="col-md-12">
                            <b>@lang('lang_v1.password'):</b>
                            {{$sell->repair_security_pwd}}
                        </div>
                    </div>
                    <div class="row mt-10">
                        <div class="col-md-6">
                            <b>@lang('repair::lang.security_pattern_code'):</b>
                            <!-- data-pattern used after AJAX modal load (see app.js view_modal handler) -->
                            <div id="security_pattern_container" @if(!empty($sell->repair_security_pattern)) data-pattern="{{ $sell->repair_security_pattern }}" @endif></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
  </div>
  <div class="modal-footer">
    <a href="#" class="print-invoice tw-dw-btn tw-dw-btn-primary tw-text-white" data-href="{{route('repair.customerCopy', [$sell->id])}}">
        <i class="fa fa-print" aria-hidden="true"></i>
        @lang("repair::lang.print_customer_copy")
    </a>
    <a href="#" class="print-invoice tw-dw-btn tw-dw-btn-primary tw-text-white" data-href="{{route('sell.printInvoice', [$sell->id])}}"><i class="fa fa-print" aria-hidden="true"></i> @lang("messages.print")</a>
      <button type="button" class="tw-dw-btn tw-dw-btn-neutral tw-text-white no-print" data-dismiss="modal">@lang( 'messages.close' )</button>
    </div>
  </div>
</div>

{{-- Currency conversion and PatternLock run on shown.bs.modal for .view_modal (AJAX-loaded HTML does not reliably execute inline scripts). --}}
