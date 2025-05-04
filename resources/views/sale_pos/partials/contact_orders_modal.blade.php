<div class="modal-dialog" role="document">
    <div class="modal-content">
        <div class="modal-header">
            <h4 class="modal-title">Account Orders</h4>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
            @if($transactions->isEmpty())
                <p>No draft account orders found.</p>
            @else
                <ul class="list-group">
                    @foreach($transactions as $transaction)
                        <li class="list-group-item">
                            <a href="{{ url('/pos/' . $transaction->id . '/edit') }}">
                                {{ \Carbon::parse($transaction->transaction_date)->format('Y-m-d H:i') }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
