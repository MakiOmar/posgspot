<div class="row">
  <div class="col-sm-12">
    <table class="table table-condensed">
      <tr>
        <th>@lang('lang_v1.payment_method')</th>
        <th>@lang('sale.sale')</th>
        <th>@lang('lang_v1.expense')</th>
      </tr>
      <tr>
        <td>
          @lang('cash_register.cash_in_hand'):
        </td>
        <td>
          <span class="display_currency" data-currency_symbol="true">{{ $register_details->cash_in_hand }}</span>
        </td>
        <td>--</td>
      </tr>
      <tr>
        <td>
          @lang('cash_register.cash_payment'):
        </th>
        <td>
          <span class="display_currency" data-currency_symbol="true">{{ $register_details->total_cash }}</span>
        </td>
        <td>
          <span class="display_currency" data-currency_symbol="true">{{ $register_details->total_cash_expense }}</span>
        </td>
      </tr>
      <tr>
        <td>
          @lang('cash_register.checque_payment'):
        </td>
        <td>
          <span class="display_currency" data-currency_symbol="true">{{ $register_details->total_cheque }}</span>
        </td>
        <td>
          <span class="display_currency" data-currency_symbol="true">{{ $register_details->total_cheque_expense }}</span>
        </td>
      </tr>
      <tr>
        <td>
          @lang('cash_register.card_payment'):
        </td>
        <td>
          <span class="display_currency" data-currency_symbol="true">{{ $register_details->total_card }}</span>
        </td>
        <td>
          <span class="display_currency" data-currency_symbol="true">{{ $register_details->total_card_expense }}</span>
        </td>
      </tr>
      <tr>
        <td>
          @lang('cash_register.bank_transfer'):
        </td>
        <td>
          <span class="display_currency" data-currency_symbol="true">{{ $register_details->total_bank_transfer }}</span>
        </td>
        <td>
          <span class="display_currency" data-currency_symbol="true">{{ $register_details->total_bank_transfer_expense }}</span>
        </td>
      </tr>
      <tr>
        <td>
          @lang('lang_v1.advance_payment'):
        </td>
        <td>
          <span class="display_currency" data-currency_symbol="true">{{ $register_details->total_advance }}</span>
        </td>
        <td>
          <span class="display_currency" data-currency_symbol="true">{{ $register_details->total_advance_expense }}</span>
        </td>
      </tr>
      @for($i = 1; $i <= 30; $i++)
      @php $key = 'custom_pay_'.$i; @endphp
      @if(array_key_exists($key, $payment_types))
        <tr>
          <td>
            {{ $payment_types[$key] }}:
          </td>
          <td>
            <span class="display_currency" data-currency_symbol="true">{{ $register_details->{'total_'.$key} ?? 0 }}</span>
          </td>
          <td>
            <span class="display_currency" data-currency_symbol="true">{{ $register_details->{'total_'.$key.'_expense'} ?? 0 }}</span>
          </td>
        </tr>
      @endif
      @endfor
      <tr>
        <td>
          @lang('cash_register.other_payments'):
        </td>
        <td>
          <span class="display_currency" data-currency_symbol="true">{{ $register_details->total_other }}</span>
        </td>
        <td>
          <span class="display_currency" data-currency_symbol="true">{{ $register_details->total_other_expense }}</span>
        </td>
      </tr>
    </table>
    <hr>
    <table class="table table-condensed">
      <tr>
        <td>
          @lang('cash_register.total_sales'):
          @show_tooltip('Total cash payments received from sales. Example: If you made 3 sales of $100, $50, and $25, this shows $175.')
        </td>
        <td>
          <span class="display_currency" data-currency_symbol="true">{{ $register_details->total_sale }}</span>
        </td>
      </tr>
      <tr class="danger">
        <th>
          @lang('cash_register.total_refund')
          @show_tooltip('Total refunds given to customers. Example: If you refunded $30 and $20, this shows $50 total refunds.')
        </th>
        <td>
          <b><span class="display_currency" data-currency_symbol="true">{{ $register_details->total_refund }}</span></b><br>
          <small>
          @if($register_details->total_cash_refund != 0)
            Cash: <span class="display_currency" data-currency_symbol="true">{{ $register_details->total_cash_refund }}</span><br>
          @endif
          @if($register_details->total_cheque_refund != 0) 
            Cheque: <span class="display_currency" data-currency_symbol="true">{{ $register_details->total_cheque_refund }}</span><br>
          @endif
          @if($register_details->total_card_refund != 0) 
            Card: <span class="display_currency" data-currency_symbol="true">{{ $register_details->total_card_refund }}</span><br> 
          @endif
          @if($register_details->total_bank_transfer_refund != 0)
            Bank Transfer: <span class="display_currency" data-currency_symbol="true">{{ $register_details->total_bank_transfer_refund }}</span><br>
          @endif
          @for($i = 1; $i <= 30; $i++)
          @php $key = 'custom_pay_'.$i; $refund_col = 'total_'.$key.'_refund'; @endphp
          @if(array_key_exists($key, $payment_types) && ($register_details->$refund_col ?? 0) != 0)
              {{ $payment_types[$key] }}: <span class="display_currency" data-currency_symbol="true">{{ $register_details->$refund_col }}</span><br>
          @endif
          @endfor
          @if($register_details->total_other_refund != 0)
            Other: <span class="display_currency" data-currency_symbol="true">{{ $register_details->total_other_refund }}</span>
          @endif
          </small>
        </td>
      </tr>
      <tr class="success">
        <th>
          @lang('lang_v1.total_payment')
          @show_tooltip('Cash currently in the register drawer. Formula: Initial Cash + Cash Sales - Cash Refunds. Example: Started with $100, made $200 in cash sales, gave $50 refund = $250 in drawer.')
        </th>
        <td>
          <b><span class="display_currency" data-currency_symbol="true">{{ $register_details->cash_in_hand + $register_details->total_cash - $register_details->total_cash_refund }}</span></b>
        </td>
      </tr>
      <tr class="success">
        <th>
          @lang('lang_v1.credit_sales'):
          @show_tooltip('Sales made on credit (not paid immediately). These are sales where payment is due later. Example: If total sales are $500 and cash sales are $300, then $200 are credit sales.')
        </th>
        <td>
          <b><span class="display_currency" data-currency_symbol="true">{{ $details['transaction_details']->total_sales - $register_details->total_sale }}</span></b>
        </td>
      </tr>
      <tr class="success">
        <th>
          @lang('cash_register.total_sales'):
          @show_tooltip('Sum of all sales transactions (both cash and credit). This includes all sales regardless of payment method. Example: If you made $300 in cash sales and $200 in credit sales, this shows $500 total sales.')
        </th>
        <td>
          <b><span class="display_currency" data-currency_symbol="true">{{ $details['transaction_details']->total_sales }}</span></b>
        </td>
      </tr>
      <tr class="danger">
        <th>
          @lang('report.total_expense'):
          @show_tooltip('Total expenses paid from the register during this session. This includes cash expenses, petty cash, and other business expenses. Example: If you paid $50 for supplies and $25 for utilities, this shows $75 total expenses.')
        </th>
        <td>
          <b><span class="display_currency" data-currency_symbol="true">{{ $register_details->total_expense }}</span></b>
        </td>
      </tr>
    </table>
  </div>
</div>

@include('cash_register.register_product_details')