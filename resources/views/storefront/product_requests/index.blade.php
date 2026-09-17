@extends('layouts.app')
@section('title', 'Product requests')

@section('content')
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">Product requests</h1>
        <p class="text-muted">Customer “request a product” submissions (<code>STOREFRONT_REQUEST_PRODUCT</code>).</p>
    </section>

    <section class="content">
        <div class="tw-transition-all lg:tw-col-span-1 tw-duration-200 tw-bg-white tw-shadow-sm tw-rounded-xl tw-ring-1 hover:tw-shadow-md tw-ring-gray-200">
            <div class="tw-p-4 sm:tw-p-5">
                <div class="tw-flex tw-flex-wrap tw-gap-2 tw-items-end tw-mb-4">
                    <div>
                        <label for="product_request_status_filter" class="control-label">Status</label>
                        <select id="product_request_status_filter" class="form-control">
                            <option value="all">All</option>
                            <option value="new" selected>New</option>
                            <option value="contacted">Contacted</option>
                            <option value="fulfilled">Fulfilled</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="product_requests_table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Product</th>
                                <th>Platform</th>
                                <th>Customer</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Submitted</th>
                                <th>@lang('messages.action')</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('javascript')
    <script type="text/javascript">
        $(document).ready(function () {
            var table = $('#product_requests_table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ action([\App\Http\Controllers\StorefrontProductRequestController::class, "index"]) }}',
                    data: function (d) {
                        d.status = $('#product_request_status_filter').val();
                    }
                },
                columns: [
                    { data: 'id', name: 'id' },
                    { data: 'product_name', name: 'product_name' },
                    { data: 'platform', name: 'platform' },
                    { data: 'name', name: 'name' },
                    { data: 'email', name: 'email' },
                    { data: 'status', name: 'status' },
                    { data: 'created_at', name: 'created_at' },
                    { data: 'action', name: 'action', orderable: false, searchable: false }
                ]
            });

            $('#product_request_status_filter').change(function () {
                table.ajax.reload();
            });
        });
    </script>
@endsection
