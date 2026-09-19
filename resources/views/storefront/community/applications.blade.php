@extends('layouts.app')
@section('title', 'Community applications')

@section('content')
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">
            Community applications
            @if($post)
                <small>{{ optional($post->translationFor('en'))->title }}</small>
            @endif
        </h1>
    </section>

    <section class="content">
        <div class="tw-mb-3 tw-flex tw-gap-2">
            <a href="{{ action([\App\Http\Controllers\StorefrontCommunityPostController::class, 'index']) }}" class="tw-dw-btn tw-dw-btn-outline">
                Back to posts
            </a>
            <a href="{{ action([\App\Http\Controllers\StorefrontCommunityApplicationController::class, 'export'], array_filter(['post_id' => $postId])) }}" class="tw-dw-btn tw-dw-btn-primary">
                Export CSV
            </a>
        </div>
        <div class="tw-bg-white tw-shadow-sm tw-rounded-xl tw-ring-1 tw-ring-gray-200 tw-p-4">
            <div class="tw-mb-3">
                <label for="app_status_filter">Status</label>
                <select id="app_status_filter" class="form-control" style="max-width: 200px;">
                    <option value="all" selected>All</option>
                    <option value="new">New</option>
                    <option value="reviewed">Reviewed</option>
                    <option value="accepted">Accepted</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="community_apps_table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Post</th>
                            <th>Name</th>
                            <th>Mobile</th>
                            <th>Source</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Update status</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </section>
@endsection

@section('javascript')
<script>
$(document).ready(function () {
    var table = $('#community_apps_table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ action([\App\Http\Controllers\StorefrontCommunityApplicationController::class, "index"]) }}',
            data: function (d) {
                d.status = $('#app_status_filter').val();
                @if($postId)
                d.post_id = {{ (int) $postId }};
                @endif
            }
        },
        columns: [
            { data: 'id', name: 'id' },
            { data: 'post_title', name: 'post_title', orderable: false, searchable: false },
            { data: 'name', name: 'name' },
            { data: 'mobile', name: 'mobile' },
            { data: 'source', name: 'source' },
            { data: 'status', name: 'status' },
            { data: 'created_at', name: 'created_at' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    });

    $('#app_status_filter').change(function () { table.ajax.reload(); });

    $(document).on('change', '.community-app-status', function () {
        var $el = $(this);
        $.ajax({
            method: 'PUT',
            url: $el.data('href'),
            data: { _token: '{{ csrf_token() }}', status: $el.val() },
            success: function (result) {
                if (result.success) {
                    toastr.success(result.msg);
                    table.ajax.reload(null, false);
                } else {
                    toastr.error(result.msg || 'Could not update.');
                }
            }
        });
    });
});
</script>
@endsection
