@extends('layouts.app')
@section('title', 'Community posts')

@section('content')
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">Community posts</h1>
        <p class="text-muted">Tournaments, events, and news for the public storefront (<code>STOREFRONT_COMMUNITY</code>).</p>
    </section>

    <section class="content">
        <div class="tw-mb-3">
            <a href="{{ action([\App\Http\Controllers\StorefrontCommunityPostController::class, 'create']) }}" class="tw-dw-btn tw-dw-btn-primary">
                <i class="fa fa-plus"></i> New post
            </a>
            <a href="{{ action([\App\Http\Controllers\StorefrontCommunityApplicationController::class, 'index']) }}" class="tw-dw-btn tw-dw-btn-outline">
                All applications
            </a>
        </div>
        <div class="tw-transition-all lg:tw-col-span-1 tw-duration-200 tw-bg-white tw-shadow-sm tw-rounded-xl tw-ring-1 hover:tw-shadow-md tw-ring-gray-200">
            <div class="tw-p-4 sm:tw-p-5">
                <div class="tw-flex tw-flex-wrap tw-gap-2 tw-items-end tw-mb-4">
                    <div>
                        <label for="community_type_filter" class="control-label">Type</label>
                        <select id="community_type_filter" class="form-control">
                            <option value="all" selected>All</option>
                            <option value="tournament">Tournament</option>
                            <option value="event">Event</option>
                            <option value="news">News</option>
                        </select>
                    </div>
                    <div>
                        <label for="community_status_filter" class="control-label">Status</label>
                        <select id="community_status_filter" class="form-control">
                            <option value="all" selected>All</option>
                            <option value="draft">Draft</option>
                            <option value="published">Published</option>
                        </select>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="community_posts_table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title (EN)</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Registration</th>
                                <th>Starts</th>
                                <th>Published</th>
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
            var table = $('#community_posts_table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ action([\App\Http\Controllers\StorefrontCommunityPostController::class, "index"]) }}',
                    data: function (d) {
                        d.type = $('#community_type_filter').val();
                        d.status = $('#community_status_filter').val();
                    }
                },
                columns: [
                    { data: 'id', name: 'id' },
                    { data: 'title', name: 'title', orderable: false, searchable: false },
                    { data: 'type', name: 'type' },
                    { data: 'status', name: 'status' },
                    { data: 'registration_mode', name: 'registration_mode' },
                    { data: 'starts_at', name: 'starts_at' },
                    { data: 'published_at', name: 'published_at' },
                    { data: 'action', name: 'action', orderable: false, searchable: false }
                ]
            });

            $('#community_type_filter, #community_status_filter').change(function () {
                table.ajax.reload();
            });

            $(document).on('click', '.delete_community_post_button', function (e) {
                e.preventDefault();
                var url = $(this).data('href');
                swal({
                    title: LANG.sure,
                    icon: 'warning',
                    buttons: true,
                    dangerMode: true
                }).then(function (willDelete) {
                    if (willDelete) {
                        $.ajax({
                            method: 'DELETE',
                            url: url,
                            data: { _token: '{{ csrf_token() }}' },
                            success: function (result) {
                                if (result.success) {
                                    toastr.success(result.msg);
                                    table.ajax.reload();
                                } else {
                                    toastr.error(result.msg || 'Could not delete.');
                                }
                            }
                        });
                    }
                });
            });
        });
    </script>
@endsection
