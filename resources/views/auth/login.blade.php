@extends('layouts.auth2')
@section('title', __('lang_v1.login'))
@inject('request', 'Illuminate\Http\Request')
@section('content')
    @php
        $loginStep = $loginStep ?? 'email';
        $otpEmail = $otpEmail ?? old('email', session('login_otp_email'));
        $resendAvailableIn = max(0, (int) ($resendAvailableIn ?? 0));
        $resendCooldownSeconds = max(1, (int) ($resendCooldownSeconds ?? 60));
    @endphp
    <div class="row">
        <div class="col-md-4"></div>
        <div class="col-md-4">
            <div
                class="tw-p-5 md:tw-p-6 tw-mb-4 tw-rounded-2xl tw-transition-all tw-duration-200 tw-bg-white tw-shadow-sm tw-ring-1 tw-ring-gray-200">
                <div class="tw-flex tw-flex-col tw-gap-4 tw-dw-rounded-box tw-dw-p-6 tw-dw-max-w-md">
                    <div class="tw-flex tw-items-center tw-flex-col">
                        <h1 class="tw-text-lg md:tw-text-xl tw-font-semibold tw-text-[#1e1e1e]">
                            @lang('lang_v1.welcome_back')
                        </h1>
                        <h2 class="tw-text-sm tw-font-medium tw-text-gray-500">
                            @lang('lang_v1.login_to_your') {{ config('app.name', 'ultimatePOS') }}
                        </h2>
                    </div>

                    @if ($loginStep === 'code')
                        <p class="tw-text-xs md:tw-text-sm tw-text-gray-500 tw-text-center tw-m-0">
                            @lang('lang_v1.login_code_inbox_hint')
                        </p>

                        <form method="POST" action="{{ route('login.otp.verify') }}" id="login-form" data-otp-form>
                            {{ csrf_field() }}
                            <input type="hidden" name="email" value="{{ $otpEmail }}">

                            <div class="form-group has-feedback">
                                <label class="tw-dw-form-control">
                                    <div class="tw-dw-label">
                                        <span class="tw-text-xs md:tw-text-sm tw-font-medium tw-text-black">@lang('business.email')</span>
                                    </div>
                                    <input
                                        class="tw-border tw-border-[#D1D5DA] tw-outline-none tw-h-12 tw-bg-transparent tw-rounded-lg tw-px-3 tw-font-medium tw-text-black"
                                        type="email" value="{{ $otpEmail }}" disabled />
                                </label>
                            </div>

                            <div class="form-group has-feedback {{ $errors->has('code') ? ' has-error' : '' }}">
                                <label class="tw-dw-form-control">
                                    <div class="tw-dw-label">
                                        <span class="tw-text-xs md:tw-text-sm tw-font-medium tw-text-black">@lang('lang_v1.login_code')</span>
                                    </div>
                                    <input
                                        class="tw-border tw-border-[#D1D5DA] tw-outline-none tw-h-12 tw-bg-transparent tw-rounded-lg tw-px-3 tw-font-medium tw-text-black placeholder:tw-text-gray-500 placeholder:tw-font-medium"
                                        id="code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                                        name="code" value="{{ old('code') }}" required autofocus
                                        autocomplete="one-time-code"
                                        placeholder="@lang('lang_v1.login_code_placeholder')" />
                                    @if ($errors->has('code'))
                                        <span class="help-block">
                                            <strong>{{ $errors->first('code') }}</strong>
                                        </span>
                                    @endif
                                </label>
                            </div>

                            <button type="submit"
                                class="tw-bg-gradient-to-r tw-from-indigo-500 tw-to-blue-500 tw-h-12 tw-rounded-xl tw-text-sm md:tw-text-base tw-text-white tw-font-semibold tw-w-full tw-max-w-full mt-2 hover:tw-from-indigo-600 hover:tw-to-blue-600 focus:tw-outline-none focus:tw-ring-2 focus:tw-ring-blue-500 focus:tw-ring-offset-2 active:tw-from-indigo-700 active:tw-to-blue-700">
                                @lang('lang_v1.verify_and_sign_in')
                            </button>
                        </form>

                        <form method="POST" action="{{ route('login.otp.request') }}" class="tw-mt-2" id="login-otp-resend-form" data-otp-form>
                            {{ csrf_field() }}
                            <input type="hidden" name="email" value="{{ $otpEmail }}">
                            <button type="submit" id="login-otp-resend-btn"
                                class="tw-h-12 tw-rounded-xl tw-text-sm md:tw-text-base tw-font-semibold tw-w-full tw-max-w-full tw-border tw-border-[#D1D5DA] tw-bg-white tw-text-black hover:tw-bg-gray-50 disabled:tw-opacity-60 disabled:tw-cursor-not-allowed"
                                @if ($resendAvailableIn > 0) disabled @endif
                                data-resend-label="{{ __('lang_v1.resend_login_code') }}"
                                data-wait-label="{{ __('lang_v1.login_otp_resend_in', ['seconds' => '__S__']) }}"
                                data-available-in="{{ $resendAvailableIn }}">
                                @if ($resendAvailableIn > 0)
                                    @lang('lang_v1.login_otp_resend_in', ['seconds' => $resendAvailableIn])
                                @else
                                    @lang('lang_v1.resend_login_code')
                                @endif
                            </button>
                        </form>

                        <div class="tw-flex tw-items-center tw-flex-col">
                            <a href="{{ route('login') }}"
                                class="tw-text-sm tw-font-medium tw-text-gray-500 hover:tw-text-gray-700 tw-mt-2">@lang('lang_v1.use_different_email')</a>
                        </div>
                    @else
                        <form method="POST" action="{{ route('login.otp.request') }}" id="login-form" data-otp-form>
                            {{ csrf_field() }}
                            <div class="form-group has-feedback {{ $errors->has('email') ? ' has-error' : '' }}">
                                <label class="tw-dw-form-control">
                                    <div class="tw-dw-label">
                                        <span class="tw-text-xs md:tw-text-sm tw-font-medium tw-text-black">@lang('business.email')</span>
                                    </div>
                                    <input
                                        class="tw-border tw-border-[#D1D5DA] tw-outline-none tw-h-12 tw-bg-transparent tw-rounded-lg tw-px-3 tw-font-medium tw-text-black placeholder:tw-text-gray-500 placeholder:tw-font-medium"
                                        name="email" required autofocus placeholder="you@example.com"
                                        id="email" type="email" autocomplete="email"
                                        value="{{ old('email', $otpEmail) }}" />
                                    @if ($errors->has('email'))
                                        <span class="help-block">
                                            <strong>{{ $errors->first('email') }}</strong>
                                        </span>
                                    @endif
                                </label>
                            </div>

                            @if(config('constants.enable_recaptcha'))
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <div class="g-recaptcha" data-sitekey="{{ config('constants.google_recaptcha_key') }}"></div>
                                        @if ($errors->has('g-recaptcha-response'))
                                            <span class="text-danger">{{ $errors->first('g-recaptcha-response') }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            @endif

                            <button type="submit"
                                class="tw-bg-gradient-to-r tw-from-indigo-500 tw-to-blue-500 tw-h-12 tw-rounded-xl tw-text-sm md:tw-text-base tw-text-white tw-font-semibold tw-w-full tw-max-w-full mt-2 hover:tw-from-indigo-600 hover:tw-to-blue-600 focus:tw-outline-none focus:tw-ring-2 focus:tw-ring-blue-500 focus:tw-ring-offset-2 active:tw-from-indigo-700 active:tw-to-blue-700">
                                @lang('lang_v1.send_login_code')
                            </button>
                        </form>
                    @endif

                    <div class="tw-flex tw-items-center tw-flex-col">
                        @if (!($request->segment(1) == 'business' && $request->segment(2) == 'register'))
                            @if (config('constants.allow_registration'))
                                <a href="{{ route('business.getRegister') }}@if (!empty(request()->lang)) {{ '?lang=' . request()->lang }} @endif"
                                    class="tw-text-sm tw-font-medium tw-text-gray-500 hover:tw-text-gray-500 tw-mt-2">{{ __('business.not_yet_registered') }}
                                    <span
                                        class="tw-text-sm tw-font-medium tw-bg-gradient-to-r tw-from-indigo-500 tw-to-blue-500 tw-inline-block tw-text-transparent tw-bg-clip-text hover:tw-text-[#fc8200] hover:tw-underline">{{ __('business.register_now') }}</span></a>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4"></div>
    </div>

@stop
@section('javascript')
    <script type="text/javascript">
        $(document).ready(function() {
            $('.change_lang').click(function() {
                window.location = "{{ route('login') }}?lang=" + $(this).attr('value');
            });

            // Prevent double-submit on OTP send / verify / resend.
            // Namespaced bind so a duplicate script include cannot stack handlers
            // (a second handler would see the flag and cancel the POST).
            // Do not disable the submitter — Chromium cancels navigation if it is.
            $(document).off('submit.otpLock', 'form[data-otp-form]')
                .on('submit.otpLock', 'form[data-otp-form]', function(e) {
                    var $form = $(this);
                    if ($form.data('otp-submitting')) {
                        e.preventDefault();
                        return false;
                    }
                    $form.data('otp-submitting', true);
                    $form.find('button[type="submit"]')
                        .attr('aria-busy', 'true')
                        .css('pointer-events', 'none');
                });

            var $resend = $('#login-otp-resend-btn');
            if ($resend.length) {
                var remaining = parseInt($resend.attr('data-available-in'), 10) || 0;
                var resendLabel = $resend.attr('data-resend-label') || '';
                var waitTemplate = $resend.attr('data-wait-label') || ':seconds';

                function paintResend() {
                    if (remaining <= 0) {
                        $resend.prop('disabled', false).text(resendLabel);
                        return;
                    }
                    $resend.prop('disabled', true).text(
                        waitTemplate.split('__S__').join(String(remaining))
                    );
                    remaining -= 1;
                    window.setTimeout(paintResend, 1000);
                }

                if (remaining > 0) {
                    paintResend();
                }
            }
        })
    </script>
@endsection
