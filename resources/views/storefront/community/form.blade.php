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
                <div class="col-md-4">
                    <div class="form-group">
                        {!! Form::label('type', 'Type:*') !!}
                        {!! Form::select('type', [
                            'tournament' => 'Tournament',
                            'event' => 'Event',
                            'news' => 'News',
                        ], old('type', $post->type ?? 'news'), ['class' => 'form-control', 'required']) !!}
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        {!! Form::label('status', 'Status:*') !!}
                        {!! Form::select('status', [
                            'draft' => 'Draft',
                            'published' => 'Published',
                        ], old('status', $post->status ?? 'draft'), ['class' => 'form-control', 'required']) !!}
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        {!! Form::label('slug', 'URL slug') !!}
                        {!! Form::text('slug', old('slug', $post->slug ?? ''), ['class' => 'form-control', 'placeholder' => 'auto from EN title']) !!}
                        <p class="help-block">Lowercase letters, numbers, and hyphens only.</p>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        {!! Form::label('starts_at', 'Starts at') !!}
                        <input type="datetime-local" name="starts_at" class="form-control" value="{{ old('starts_at', $post && $post->starts_at ? $post->starts_at->format('Y-m-d\TH:i') : '') }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        {!! Form::label('ends_at', 'Ends at') !!}
                        <input type="datetime-local" name="ends_at" class="form-control" value="{{ old('ends_at', $post && $post->ends_at ? $post->ends_at->format('Y-m-d\TH:i') : '') }}">
                    </div>
                </div>
            </div>

            <div class="form-group">
                {!! Form::label('cover', 'Cover image') !!}
                @if($post && $post->coverUrl())
                    <div class="tw-mb-2">
                        <img src="{{ $post->coverUrl() }}" alt="Cover" style="max-height: 120px; border-radius: 6px;">
                    </div>
                    <label class="checkbox-inline">
                        <input type="checkbox" name="remove_cover" value="1"> Remove current cover
                    </label>
                @endif
                {!! Form::file('cover', ['class' => 'form-control', 'accept' => 'image/*']) !!}
            </div>

            <hr>
            <h4>English content</h4>
            <div class="form-group">
                {!! Form::label('title_en', 'Title (EN):*') !!}
                {!! Form::text('title_en', old('title_en', $translations['en']['title'] ?? ''), ['class' => 'form-control', 'required', 'maxlength' => 191]) !!}
            </div>
            <div class="form-group">
                {!! Form::label('excerpt_en', 'Excerpt (EN)') !!}
                {!! Form::textarea('excerpt_en', old('excerpt_en', $translations['en']['excerpt'] ?? ''), ['class' => 'form-control', 'rows' => 2, 'maxlength' => 500]) !!}
            </div>
            <div class="form-group">
                {!! Form::label('body_en', 'Body (EN)') !!}
                {!! Form::textarea('body_en', old('body_en', $translations['en']['body'] ?? ''), ['class' => 'form-control', 'rows' => 8]) !!}
                <p class="help-block">Basic HTML is allowed on the storefront (sanitized on API output).</p>
            </div>

            <hr>
            <h4>Arabic content</h4>
            <div class="form-group">
                {!! Form::label('title_ar', 'Title (AR)') !!}
                {!! Form::text('title_ar', old('title_ar', $translations['ar']['title'] ?? ''), ['class' => 'form-control', 'maxlength' => 191]) !!}
            </div>
            <div class="form-group">
                {!! Form::label('excerpt_ar', 'Excerpt (AR)') !!}
                {!! Form::textarea('excerpt_ar', old('excerpt_ar', $translations['ar']['excerpt'] ?? ''), ['class' => 'form-control', 'rows' => 2, 'maxlength' => 500]) !!}
            </div>
            <div class="form-group">
                {!! Form::label('body_ar', 'Body (AR)') !!}
                {!! Form::textarea('body_ar', old('body_ar', $translations['ar']['body'] ?? ''), ['class' => 'form-control', 'rows' => 8]) !!}
            </div>

            <button type="submit" class="tw-dw-btn tw-dw-btn-primary">Save</button>
            <a href="{{ action([\App\Http\Controllers\StorefrontCommunityPostController::class, 'index']) }}" class="tw-dw-btn tw-dw-btn-outline">Cancel</a>

            {!! Form::close() !!}
        </div>
    </section>
@endsection
