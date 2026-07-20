{{-- Manual reward points credit/debit modal --}}
<div class="modal fade" id="adjust_reward_points_modal" tabindex="-1" role="dialog"
        aria-labelledby="adjustRewardPointsLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            {!! Form::open([
                'url' => action([\App\Http\Controllers\ContactController::class, 'adjustRewardPoints'], [$contact->id]),
                'method' => 'post',
                'id' => 'adjust_reward_points_form',
            ]) !!}
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="adjustRewardPointsLabel">@lang('lang_v1.adjust_reward_points')</h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    {!! Form::label('adjustment_type', __('lang_v1.adjustment_type') . ':*') !!}
                    {!! Form::select('adjustment_type', [
                        'credit' => __('lang_v1.credit_reward_points'),
                        'debit' => __('lang_v1.debit_reward_points'),
                    ], 'credit', ['class' => 'form-control', 'required', 'id' => 'reward_adjustment_type']) !!}
                </div>
                <div class="form-group">
                    {!! Form::label('points', (session('business.rp_name') ?: __('lang_v1.reward_points')) . ':*') !!}
                    {!! Form::number('points', null, [
                        'class' => 'form-control',
                        'required',
                        'min' => 1,
                        'step' => 1,
                        'placeholder' => __('lang_v1.points'),
                        'id' => 'reward_adjustment_points',
                    ]) !!}
                </div>
                <div class="form-group">
                    {!! Form::label('note', __('brand.note') . ':') !!}
                    {!! Form::textarea('note', null, [
                        'class' => 'form-control',
                        'placeholder' => __('brand.note'),
                        'rows' => 3,
                    ]) !!}
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">@lang('messages.submit')</button>
                <button type="button" class="tw-dw-btn tw-dw-btn-neutral tw-text-white" data-dismiss="modal">@lang('messages.close')</button>
            </div>
            {!! Form::close() !!}
        </div>
    </div>
</div>
