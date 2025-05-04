@if($transactions->isEmpty())
    <p>No transactions found.</p>
@else
    <ul class="list-group">
        @foreach($transactions as $transaction)
            <li class="list-group-item">
                <a href="#" class="contact-transaction" data-href="{{ url('/sells/' . $transaction->id ) }}">
                    {{ \Carbon\Carbon::parse($transaction->transaction_date)->format('Y-m-d H:i') }}
                </a>
            </li>
        @endforeach
    </ul>

    <div class="mt-3" id="pagination_links">
        {!! $transactions->links('vendor.pagination.bootstrap-4') !!}
    </div>
@endif