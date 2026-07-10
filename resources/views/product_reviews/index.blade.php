@extends('layouts.app')
@section('title', 'Product reviews')

@section('content')
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">Product reviews</h1>
        <p class="text-muted">Moderate storefront customer reviews before they appear on product pages.</p>
    </section>

    <section class="content">
        <div class="tw-transition-all lg:tw-col-span-1 tw-duration-200 tw-bg-white tw-shadow-sm tw-rounded-xl tw-ring-1 hover:tw-shadow-md tw-ring-gray-200">
            <div class="tw-p-4 sm:tw-p-5">
                <div class="tw-flex tw-flex-wrap tw-gap-2 tw-items-end tw-mb-4">
                    <div>
                        <label for="review_status_filter" class="control-label">Status</label>
                        <select id="review_status_filter" class="form-control">
                            <option value="all">All</option>
                            <option value="pending" selected>Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                </div>
                <div class="tw-flow-root tw-mt-2 tw-border-b tw-border-gray-200">
                    <div class="tw-mx-4 tw--my-2 tw-overflow-x-auto sm:tw--mx-5">
                        <div class="tw-inline-block tw-min-w-full tw-py-2 tw-align-middle sm:tw-px-5">
                            @can('product_review.access')
                                <table class="table table-bordered table-striped" id="product_reviews_table">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Customer</th>
                                            <th>Rating</th>
                                            <th>Review</th>
                                            <th>Status</th>
                                            <th>Submitted</th>
                                            <th>@lang('messages.action')</th>
                                        </tr>
                                    </thead>
                                </table>
                            @endcan
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@stop

@section('javascript')
    <script type="text/javascript">
        $(document).ready(function() {
            var reviews_table = $('#product_reviews_table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ action([\App\Http\Controllers\ProductReviewController::class, 'index']) }}",
                    data: function(d) {
                        d.status = $('#review_status_filter').val();
                    }
                },
                columns: [
                    { data: 'product_name', name: 'products.name' },
                    { data: 'contact_name', name: 'contacts.name' },
                    { data: 'rating', name: 'product_reviews.rating' },
                    { data: 'body', name: 'product_reviews.body' },
                    { data: 'status', name: 'product_reviews.status' },
                    { data: 'created_at', name: 'product_reviews.created_at' },
                    { data: 'action', orderable: false, searchable: false }
                ],
                order: [[5, 'desc']]
            });

            $('#review_status_filter').on('change', function() {
                reviews_table.ajax.reload();
            });

            $(document).on('click', '.review-approve-btn', function(e) {
                e.preventDefault();
                var href = $(this).data('href');
                swal({
                    title: LANG.sure,
                    text: 'Approve this review for the storefront?',
                    icon: 'warning',
                    buttons: true,
                }).then(function(willApprove) {
                    if (!willApprove) {
                        return;
                    }
                    $.ajax({
                        method: 'POST',
                        url: href,
                        dataType: 'json',
                        data: { _token: '{{ csrf_token() }}' },
                        success: function(result) {
                            if (result.success) {
                                toastr.success(result.msg);
                                reviews_table.ajax.reload();
                            } else {
                                toastr.error(result.msg || 'Could not approve review.');
                            }
                        }
                    });
                });
            });

            $(document).on('click', '.review-reject-btn', function(e) {
                e.preventDefault();
                var href = $(this).data('href');
                swal({
                    title: LANG.sure,
                    text: 'Reject this review?',
                    icon: 'warning',
                    buttons: true,
                    dangerMode: true,
                }).then(function(willReject) {
                    if (!willReject) {
                        return;
                    }
                    $.ajax({
                        method: 'POST',
                        url: href,
                        dataType: 'json',
                        data: { _token: '{{ csrf_token() }}' },
                        success: function(result) {
                            if (result.success) {
                                toastr.success(result.msg);
                                reviews_table.ajax.reload();
                            } else {
                                toastr.error(result.msg || 'Could not reject review.');
                            }
                        }
                    });
                });
            });
        });
    </script>
@endsection
