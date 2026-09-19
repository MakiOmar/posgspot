@extends('layouts.app')
@section('title', $post ? 'Edit community post' : 'New community post')

@section('content')
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">
            {{ $post ? 'Edit community post' : 'New community post' }}
        </h1>
        <ol class="breadcrumb">
            <li>
                <a href="{{ action([\App\Http\Controllers\StorefrontCommunityPostController::class, 'index']) }}">Community posts</a>
            </li>
            <li class="active">{{ $post ? '#'.$post->id : 'New' }}</li>
        </ol>
    </section>

    <section class="content">
        <div class="tw-bg-white tw-shadow-sm tw-rounded-xl tw-ring-1 tw-ring-gray-200 tw-p-4 sm:tw-p-6">
            {!! Form::open(['url' => $action, 'method' => $method, 'files' => true, 'id' => 'community_post_form']) !!}

            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('type', 'Type:*') !!}
                        {!! Form::select('type', [
                            'tournament' => 'Tournament',
                            'event' => 'Event',
                            'news' => 'News',
                        ], old('type', $post->type ?? 'news'), ['class' => 'form-control', 'required', 'id' => 'community_type']) !!}
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('status', 'Status:*') !!}
                        {!! Form::select('status', [
                            'draft' => 'Draft',
                            'published' => 'Published',
                        ], old('status', $post->status ?? 'draft'), ['class' => 'form-control', 'required']) !!}
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('slug', 'URL slug') !!}
                        {!! Form::text('slug', old('slug', $post->slug ?? ''), ['class' => 'form-control', 'placeholder' => 'auto from EN title']) !!}
                    </div>
                </div>
                <div class="col-md-3 community-field-news">
                    <div class="form-group">
                        <label class="checkbox-inline" style="margin-top: 28px;">
                            <input type="checkbox" name="is_featured" value="1" {{ old('is_featured', $post->is_featured ?? false) ? 'checked' : '' }}>
                            Featured news
                        </label>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 community-field-dated">
                    <div class="form-group">
                        {!! Form::label('starts_at', 'Starts at') !!}
                        <input type="datetime-local" name="starts_at" class="form-control" value="{{ old('starts_at', $post && $post->starts_at ? $post->starts_at->format('Y-m-d\TH:i') : '') }}">
                    </div>
                </div>
                <div class="col-md-4 community-field-dated">
                    <div class="form-group">
                        {!! Form::label('ends_at', 'Ends at') !!}
                        <input type="datetime-local" name="ends_at" class="form-control" value="{{ old('ends_at', $post && $post->ends_at ? $post->ends_at->format('Y-m-d\TH:i') : '') }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        {!! Form::label('published_at', 'Published at') !!}
                        <input type="datetime-local" name="published_at" class="form-control" value="{{ old('published_at', $post && $post->published_at ? $post->published_at->format('Y-m-d\TH:i') : '') }}">
                        <p class="help-block">Leave blank to auto-set on first publish.</p>
                    </div>
                </div>
            </div>

            <div class="row community-field-eventish">
                <div class="col-md-4">
                    <div class="form-group">
                        {!! Form::label('location_id', 'Branch / location') !!}
                        {!! Form::select('location_id', $locations, old('location_id', $post->location_id ?? ''), ['class' => 'form-control']) !!}
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        {!! Form::label('registration_mode', 'Registration mode') !!}
                        {!! Form::select('registration_mode', [
                            'off' => 'Off',
                            'internal' => 'Internal (name + mobile)',
                            'external' => 'External link',
                        ], old('registration_mode', $post->registration_mode ?? 'off'), ['class' => 'form-control', 'id' => 'registration_mode']) !!}
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="checkbox-inline" style="margin-top: 28px;">
                            <input type="checkbox" name="registration_open" value="1" {{ old('registration_open', $post->registration_open ?? false) ? 'checked' : '' }}>
                            Registration open
                        </label>
                    </div>
                </div>
            </div>

            <div class="row community-field-eventish">
                <div class="col-md-6 community-field-external">
                    <div class="form-group">
                        {!! Form::label('registration_url', 'External registration URL') !!}
                        {!! Form::text('registration_url', old('registration_url', $post->registration_url ?? ''), ['class' => 'form-control']) !!}
                    </div>
                </div>
                <div class="col-md-3 community-field-tournament">
                    <div class="form-group">
                        {!! Form::label('prize_pool', 'Prize pool') !!}
                        {!! Form::text('prize_pool', old('prize_pool', $post->prize_pool ?? ''), ['class' => 'form-control']) !!}
                    </div>
                </div>
                <div class="col-md-3 community-field-tournament">
                    <div class="form-group">
                        {!! Form::label('entry_fee', 'Entry fee') !!}
                        {!! Form::text('entry_fee', old('entry_fee', $post->entry_fee ?? ''), ['class' => 'form-control']) !!}
                    </div>
                </div>
                <div class="col-md-3 community-field-tournament">
                    <div class="form-group">
                        {!! Form::label('available_spots', 'Available spots') !!}
                        {!! Form::number('available_spots', old('available_spots', $post->available_spots ?? ''), ['class' => 'form-control', 'min' => 0]) !!}
                    </div>
                </div>
                <div class="col-md-3 community-field-tournament">
                    <div class="form-group">
                        {!! Form::label('winner', 'Winner (past)') !!}
                        {!! Form::text('winner', old('winner', $post->winner ?? ''), ['class' => 'form-control']) !!}
                    </div>
                </div>
            </div>

            <div class="form-group" id="community_media_field"
                 data-media-url="{{ action([\App\Http\Controllers\StorefrontSettingController::class, 'listMedia']) }}"
                 data-upload-url="{{ action([\App\Http\Controllers\StorefrontSettingController::class, 'uploadHomepageMedia']) }}">
                {!! Form::label('cover_path', 'Cover / main image') !!}
                <div id="community_cover_preview" class="tw-mb-2">
                    @if($post && $post->coverUrl())
                        <img src="{{ $post->coverUrl() }}" alt="Cover" style="max-height: 120px; border-radius: 6px;">
                    @endif
                </div>
                <input type="hidden" name="cover_path" id="community_cover_path" value="{{ old('cover_path', $post->cover_path ?? '') }}">
                <div class="btn-group" style="margin-bottom:8px;">
                    <button type="button" class="btn btn-default btn-sm" id="community_cover_library_btn">
                        <i class="fas fa-images"></i> Choose cover from library
                    </button>
                </div>
                <label class="checkbox-inline" style="display:block;margin-top:6px;">
                    <input type="checkbox" name="remove_cover" id="community_remove_cover" value="1"> Remove current cover
                </label>

                <hr style="margin:14px 0;">
                {!! Form::label('gallery', 'Gallery images') !!}
                @if(isset($media) && $media->count())
                    <ul class="list-unstyled tw-mb-2">
                        @foreach($media as $item)
                            <li>
                                <label class="checkbox-inline">
                                    <input type="checkbox" name="remove_media[]" value="{{ $item->id }}">
                                    Remove #{{ $item->id }}
                                </label>
                                @if($item->publicUrl((int) ($post->business_id ?? 0)))
                                    — <img src="{{ $item->publicUrl((int) $post->business_id) }}" alt="" style="height:36px;border-radius:3px;vertical-align:middle;">
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
                <div id="community_gallery_pending" class="row" style="margin-bottom:8px;"></div>
                <button type="button" class="btn btn-default btn-sm" id="community_gallery_library_btn">
                    <i class="fas fa-images"></i> Add gallery images from library
                </button>
            </div>

            <style>
            .community-lib-modal{position:fixed;inset:0;z-index:1050;background:rgba(0,0,0,.45);display:flex;align-items:center;justify-content:center;padding:16px;}
            .community-lib-modal[hidden]{display:none!important;}
            .community-lib-dialog{background:#fff;border-radius:6px;max-width:720px;width:100%;max-height:90vh;overflow:auto;padding:12px 14px;box-shadow:0 8px 28px rgba(0,0,0,.2);}
            .community-lib-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;}
            .community-lib-toolbar{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px;}
            .community-lib-toolbar .form-control{max-width:220px;}
            .community-lib-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(110px,1fr));gap:8px;min-height:80px;}
            .community-lib-card{border:1px solid #ddd;border-radius:4px;background:#fafafa;padding:6px;cursor:pointer;text-align:left;}
            .community-lib-card:hover{border-color:#3c8dbc;}
            .community-lib-card img{display:block;width:100%;height:72px;object-fit:cover;border-radius:2px;margin-bottom:4px;}
            .community-lib-name{display:block;font-size:11px;line-height:1.2;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
            .community-lib-pager{display:flex;align-items:center;gap:8px;margin-top:10px;}
            </style>
            <div id="community_library_modal" class="community-lib-modal" hidden>
                <div class="community-lib-dialog" role="dialog" aria-modal="true" aria-label="Media library">
                    <div class="community-lib-head">
                        <strong>Media library</strong>
                        <button type="button" class="btn btn-default btn-xs" id="community_library_close">Close</button>
                    </div>
                    <div class="community-lib-toolbar">
                        <input type="search" class="form-control input-sm" id="community_library_q" placeholder="Search" />
                        <button type="button" class="btn btn-default btn-sm" id="community_library_search">Search</button>
                        <button type="button" class="btn btn-primary btn-sm" id="community_library_upload">Upload new</button>
                        <input type="file" id="community_library_file" accept="image/jpeg,image/png,image/gif,image/webp" hidden />
                    </div>
                    <p id="community_library_status" class="text-muted" style="margin:8px 0;"></p>
                    <div id="community_library_grid" class="community-lib-grid"></div>
                    <div class="community-lib-pager">
                        <button type="button" class="btn btn-default btn-xs" id="community_library_prev" disabled>Prev</button>
                        <span id="community_library_page" class="text-muted"></span>
                        <button type="button" class="btn btn-default btn-xs" id="community_library_next" disabled>Next</button>
                    </div>
                </div>
            </div>

            <hr>
            <h4>English content</h4>
            <div class="form-group">
                {!! Form::label('title_en', 'Title (EN):*') !!}
                {!! Form::text('title_en', old('title_en', $translations['en']['title'] ?? ''), ['class' => 'form-control', 'required', 'maxlength' => 191]) !!}
            </div>
            <div class="form-group community-field-tournament">
                {!! Form::label('game_title_en', 'Game (EN)') !!}
                {!! Form::text('game_title_en', old('game_title_en', $translations['en']['game_title'] ?? ''), ['class' => 'form-control', 'maxlength' => 191]) !!}
            </div>
            <div class="form-group">
                {!! Form::label('excerpt_en', 'Short description (EN)') !!}
                {!! Form::textarea('excerpt_en', old('excerpt_en', $translations['en']['excerpt'] ?? ''), ['class' => 'form-control', 'rows' => 2, 'maxlength' => 500]) !!}
            </div>
            <div class="form-group">
                {!! Form::label('body_en', 'Full article / body (EN)') !!}
                {!! Form::textarea('body_en', old('body_en', $translations['en']['body'] ?? ''), ['class' => 'form-control', 'rows' => 8]) !!}
            </div>
            <div class="form-group community-field-eventish">
                {!! Form::label('registration_details_en', 'Registration details (EN)') !!}
                {!! Form::textarea('registration_details_en', old('registration_details_en', $translations['en']['registration_details'] ?? ''), ['class' => 'form-control', 'rows' => 2]) !!}
            </div>
            <div class="form-group community-field-tournament">
                {!! Form::label('rules_en', 'Rules (EN)') !!}
                {!! Form::textarea('rules_en', old('rules_en', $translations['en']['rules'] ?? ''), ['class' => 'form-control', 'rows' => 4]) !!}
            </div>
            <div class="form-group community-field-tournament">
                {!! Form::label('results_en', 'Results (EN)') !!}
                {!! Form::textarea('results_en', old('results_en', $translations['en']['results'] ?? ''), ['class' => 'form-control', 'rows' => 3]) !!}
            </div>
            <div class="form-group community-field-eventish">
                {!! Form::label('highlights_en', 'Highlights (EN)') !!}
                {!! Form::textarea('highlights_en', old('highlights_en', $translations['en']['highlights'] ?? ''), ['class' => 'form-control', 'rows' => 3]) !!}
            </div>
            <div class="form-group community-field-event">
                {!! Form::label('recap_en', 'Recap (EN)') !!}
                {!! Form::textarea('recap_en', old('recap_en', $translations['en']['recap'] ?? ''), ['class' => 'form-control', 'rows' => 3]) !!}
            </div>

            <hr>
            <h4>Arabic content</h4>
            <div class="form-group">
                {!! Form::label('title_ar', 'Title (AR)') !!}
                {!! Form::text('title_ar', old('title_ar', $translations['ar']['title'] ?? ''), ['class' => 'form-control', 'maxlength' => 191]) !!}
            </div>
            <div class="form-group community-field-tournament">
                {!! Form::label('game_title_ar', 'Game (AR)') !!}
                {!! Form::text('game_title_ar', old('game_title_ar', $translations['ar']['game_title'] ?? ''), ['class' => 'form-control', 'maxlength' => 191]) !!}
            </div>
            <div class="form-group">
                {!! Form::label('excerpt_ar', 'Short description (AR)') !!}
                {!! Form::textarea('excerpt_ar', old('excerpt_ar', $translations['ar']['excerpt'] ?? ''), ['class' => 'form-control', 'rows' => 2, 'maxlength' => 500]) !!}
            </div>
            <div class="form-group">
                {!! Form::label('body_ar', 'Full article / body (AR)') !!}
                {!! Form::textarea('body_ar', old('body_ar', $translations['ar']['body'] ?? ''), ['class' => 'form-control', 'rows' => 8]) !!}
            </div>
            <div class="form-group community-field-eventish">
                {!! Form::label('registration_details_ar', 'Registration details (AR)') !!}
                {!! Form::textarea('registration_details_ar', old('registration_details_ar', $translations['ar']['registration_details'] ?? ''), ['class' => 'form-control', 'rows' => 2]) !!}
            </div>
            <div class="form-group community-field-tournament">
                {!! Form::label('rules_ar', 'Rules (AR)') !!}
                {!! Form::textarea('rules_ar', old('rules_ar', $translations['ar']['rules'] ?? ''), ['class' => 'form-control', 'rows' => 4]) !!}
            </div>
            <div class="form-group community-field-tournament">
                {!! Form::label('results_ar', 'Results (AR)') !!}
                {!! Form::textarea('results_ar', old('results_ar', $translations['ar']['results'] ?? ''), ['class' => 'form-control', 'rows' => 3]) !!}
            </div>
            <div class="form-group community-field-eventish">
                {!! Form::label('highlights_ar', 'Highlights (AR)') !!}
                {!! Form::textarea('highlights_ar', old('highlights_ar', $translations['ar']['highlights'] ?? ''), ['class' => 'form-control', 'rows' => 3]) !!}
            </div>
            <div class="form-group community-field-event">
                {!! Form::label('recap_ar', 'Recap (AR)') !!}
                {!! Form::textarea('recap_ar', old('recap_ar', $translations['ar']['recap'] ?? ''), ['class' => 'form-control', 'rows' => 3]) !!}
            </div>

            <button type="submit" class="tw-dw-btn tw-dw-btn-primary">Save</button>
            <a href="{{ action([\App\Http\Controllers\StorefrontCommunityPostController::class, 'index']) }}" class="tw-dw-btn tw-dw-btn-outline">Cancel</a>

            {!! Form::close() !!}
        </div>
    </section>
@endsection

@section('javascript')
<script src="{{ asset('js/community-media-library.js') }}"></script>
<script>
(function () {
    function syncCommunityForm() {
        var type = $('#community_type').val();
        var mode = $('#registration_mode').val();
        $('.community-field-news').toggle(type === 'news');
        $('.community-field-tournament').toggle(type === 'tournament');
        $('.community-field-event').toggle(type === 'event');
        $('.community-field-eventish').toggle(type === 'tournament' || type === 'event');
        $('.community-field-dated').toggle(type === 'tournament' || type === 'event');
        $('.community-field-external').toggle(mode === 'external');
    }
    $(document).ready(function () {
        $('#community_type, #registration_mode').on('change', syncCommunityForm);
        syncCommunityForm();
    });
})();
</script>
@endsection
