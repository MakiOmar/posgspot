<div class="modal fade" id="search_transactions_modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Search Transactions</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <form id="transaction_search_form" class="mb-3">
                    <div class="form-group">
                        <label for="search_by">Search By</label>
                        <select name="search_by" id="search_by" class="form-control">
                            <option value="phone">Phone</option>
                            <option value="invoice_no">Invoice Number</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="transaction_search_input">Search</label>
                        <input type="text" name="query" id="transaction_search_input" class="form-control" placeholder="Enter value...">
                    </div>
                    <button type="submit" class="btn btn-primary">Search</button>
                </form>
                

                <div id="transaction_search_results"></div>
            </div>
        </div>
    </div>
</div>