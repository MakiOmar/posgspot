@extends('layouts.app')
@section('title', __('lang_v1.backup'))

@section('content')

{{-- Page header --}}
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.backup')</h1>
</section>

<section class="content">

    @if (session('notification') || !empty($notification))
        {{-- Demo / permission notice from notAllowedInDemo() --}}
        <div class="row">
            <div class="col-sm-12">
                <div class="alert alert-danger alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                    {{ $notification['msg'] ?? session('notification.msg') }}
                </div>
            </div>
        </div>
    @endif

    <div class="row">
        {{-- Automatic schedule settings --}}
        <div class="col-md-5">
            @component('components.widget', ['class' => 'box-primary', 'title' => __('backup.automatic_backups')])
                <form method="POST" action="{{ route('backup.settings.update') }}" id="backup-settings-form">
                    @csrf
                    @method('PUT')

                    <div class="form-group">
                        <div class="checkbox">
                            <label>
                                <input type="hidden" name="enabled" value="0">
                                <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $settings['enabled']))>
                                @lang('backup.enabled')
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="backup-interval">@lang('backup.interval')</label>
                        <select name="interval" id="backup-interval" class="form-control">
                            @foreach ($intervals as $interval)
                                <option value="{{ $interval }}" @selected(old('interval', $settings['interval']) === $interval)>
                                    @lang('backup.intervals.'.$interval)
                                </option>
                            @endforeach
                        </select>
                        @error('interval')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group">
                        <label for="backup-scope">@lang('backup.scope')</label>
                        <select name="scope" id="backup-scope" class="form-control">
                            @foreach ($scopes as $scope)
                                <option value="{{ $scope }}" @selected(old('scope', $settings['scope']) === $scope)>
                                    @lang('backup.scopes.'.$scope)
                                </option>
                            @endforeach
                        </select>
                        @error('scope')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>

                    <p class="help-block">@lang('backup.kept_forever_help')</p>

                    <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">@lang('messages.save')</button>
                </form>
            @endcomponent

            {{-- Last / next run --}}
            @component('components.widget', ['class' => 'box-default', 'title' => __('backup.status')])
                <dl class="dl-horizontal tw-mb-0">
                    <dt>@lang('backup.last_run')</dt>
                    <dd>
                        @if ($last_run['at'])
                            {{ $last_run['at']->toDateTimeString() }}
                            @if ($last_run['status'] === 'success')
                                <span class="label label-success">@lang('backup.status_success')</span>
                            @else
                                <span class="label label-danger">@lang('backup.status_failed')</span>
                            @endif
                            @if ($last_run['status'] !== 'success' && $last_run['message'])
                                <br><small class="text-danger">{{ $last_run['message'] }}</small>
                            @endif
                        @else
                            @lang('backup.never')
                        @endif
                    </dd>
                    <dt>@lang('backup.next_run')</dt>
                    <dd>{{ $next_run ? $next_run->toDateTimeString() : __('backup.disabled') }}</dd>
                    {{-- Storage: backups are never auto-deleted, so show growth --}}
                    <dt>@lang('backup.storage_used')</dt>
                    <dd>{{ __('backup.storage_used_value', ['size' => humanFilesize($total_size), 'count' => count($backups)]) }}</dd>
                    @if ($free_space !== null)
                        <dt>@lang('backup.free_space')</dt>
                        <dd>{{ humanFilesize($free_space) }}</dd>
                    @endif
                </dl>
                @if ($cron_job_command)
                    <p class="tw-mt-3 tw-mb-1"><strong>@lang('backup.cron_required')</strong></p>
                    <code class="tw-break-all">{{ $cron_job_command }}</code>
                @endif
            @endcomponent
        </div>

        {{-- Archive list --}}
        <div class="col-md-7">
            @component('components.widget', ['class' => 'box-primary', 'title' => __('backup.backups')])
                @slot('tool')
                    <div class="box-tools">
                        <form method="POST" action="{{ route('backup.store') }}" class="js-backup-confirm"
                              data-confirm="{{ __('backup.backup_now_confirm') }}">
                            @csrf
                            <button type="submit" class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full">
                                <i class="fa fa-cloud-upload"></i> @lang('backup.backup_now')
                            </button>
                        </form>
                    </div>
                @endslot

                @if (count($backups))
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>@lang('lang_v1.file')</th>
                                    <th>@lang('lang_v1.size')</th>
                                    <th>@lang('lang_v1.date')</th>
                                    <th>@lang('lang_v1.age')</th>
                                    <th>@lang('messages.actions')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($backups as $backup)
                                    <tr>
                                        <td>{{ $backup['file_name'] }}</td>
                                        <td>{{ humanFilesize($backup['file_size']) }}</td>
                                        <td>{{ \Carbon::createFromTimestamp($backup['last_modified'])->toDateTimeString() }}</td>
                                        <td>{{ \Carbon::createFromTimestamp($backup['last_modified'])->diffForHumans() }}</td>
                                        <td class="tw-whitespace-nowrap">
                                            <a class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-accent"
                                               href="{{ route('backup.download', $backup['file_name']) }}">
                                                <i class="fa fa-cloud-download"></i> @lang('lang_v1.download')
                                            </a>
                                            <form method="POST" action="{{ route('backup.destroy', $backup['file_name']) }}"
                                                  class="js-backup-confirm tw-inline" data-confirm="{{ __('backup.delete_confirm') }}" data-danger="1">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="tw-dw-btn tw-dw-btn-outline tw-dw-btn-xs tw-dw-btn-error">
                                                    <i class="fa fa-trash-o"></i> @lang('messages.delete')
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    {{-- Empty state --}}
                    <div class="well tw-mb-0">@lang('backup.no_backups')</div>
                @endif
            @endcomponent
        </div>
    </div>
</section>
@endsection

@section('javascript')
<script>
    // SweetAlert confirmation, then disable the button while the request runs.
    var backupRunningLabel = @json(__('backup.running'));
    $(document).on('submit', 'form.js-backup-confirm', function (e) {
        var form = this;
        if (form.dataset.confirmed === '1') {
            return;
        }
        e.preventDefault();
        swal({
            title: LANG.sure,
            text: form.dataset.confirm,
            icon: 'warning',
            buttons: true,
            dangerMode: form.dataset.danger === '1',
        }).then(function (confirmed) {
            if (!confirmed) {
                return;
            }
            form.dataset.confirmed = '1';
            var $button = $(form).find('button[type="submit"]');
            $button.prop('disabled', true);
            if (!form.dataset.danger) {
                $button.html('<i class="fa fa-spinner fa-spin"></i> ').append(document.createTextNode(backupRunningLabel));
            }
            form.submit();
        });
    });
</script>
@endsection
