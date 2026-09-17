@extends('layouts.app')
@section('title', 'Sell to us requests')

@section('content')
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">Sell to us requests</h1>
        <p class="text-muted">Trade-in submissions from the storefront (account / disc / device).</p>
    </section>

    <section class="content">
        <div class="tw-transition-all lg:tw-col-span-1 tw-duration-200 tw-bg-white tw-shadow-sm tw-rounded-xl tw-ring-1 hover:tw-shadow-md tw-ring-gray-200">
            <div class="tw-p-4 sm:tw-p-5">
                <div class="tw-flex tw-flex-wrap tw-gap-2 tw-items-end tw-mb-4">
                    <div>
                        <label for="sell_status_filter" class="control-label">Status</label>
                        <select id="sell_status_filter" class="form-control">
                            <option value="all">All</option>
                            <option value="new" selected>New</option>
                            <option value="contacted">Contacted</option>
                            <option value="accepted">Accepted</option>
                            <option value="rejected">Rejected</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>
                    <div>
                        <label for="sell_type_filter" class="control-label">Type</label>
                        <select id="sell_type_filter" class="form-control">
                            <option value="all" selected>All</option>
                            <option value="account">Account</option>
                            <option value="disc">Disc</option>
                            <option value="device">Device</option>
                        </select>
                    </div>
                </div>
                <div class="tw-flow-root tw-mt-2 tw-border-b tw-border-gray-200">
                    <div class="tw-mx-4 tw--my-2 tw-overflow-x-auto sm:tw--mx-5">
                        <div class="tw-inline-block tw-min-w-full tw-py-2 tw-align-middle sm:tw-px-5">
                            <table class="table table-bordered table-striped" id="sell_requests_table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Type</th>
                                        <th>Customer</th>
                                        <th>City</th>
                                        <th>From us?</th>
                                        <th>Invoice</th>
                                        <th>Status</th>
                                        <th>Submitted</th>
                                        <th>@lang('messages.action')</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('javascript')
    <script type="text/javascript">
        $(document).ready(function () {
            var table = $('#sell_requests_table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ action([\App\Http\Controllers\StorefrontSellRequestController::class, "index"]) }}',
                    data: function (d) {
                        d.status = $('#sell_status_filter').val();
                        d.type = $('#sell_type_filter').val();
                    }
                },
                columns: [
                    { data: 'id', name: 'id' },
                    { data: 'type', name: 'type' },
                    { data: 'name', name: 'name' },
                    { data: 'city', name: 'city' },
                    { data: 'purchased_from_us', name: 'purchased_from_us' },
                    { data: 'invoice_no', name: 'invoice_no' },
                    { data: 'status', name: 'status' },
                    { data: 'created_at', name: 'created_at' },
                    { data: 'action', name: 'action', orderable: false, searchable: false }
                ],
                order: [[0, 'desc']]
            });

            $('#sell_status_filter, #sell_type_filter').on('change', function () {
                table.ajax.reload();
            });
        });
    </script>
@endsection
