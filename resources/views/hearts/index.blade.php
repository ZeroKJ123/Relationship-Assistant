@extends('layouts.app')
@section('title', __('Мои сердечки'))
@section('content')
<h1 class="form-title">{{ __('Мои сердечки') }}</h1>
<p class="wallet-total wallet-total--large"><x-icon name="heart" /> {{ $wallet->balance }}</p>
<p class="lead">{{ __('Свободно: :available · В резерве: :reserved', ['available'=>$available,'reserved'=>$reserved]) }}</p>
<p class="field-hint">{{ __('Баланс личный и сохраняется при смене пары. Все изменения видны в истории.') }}</p>
<x-form-feedback />
@if($couple?->status === \App\Enums\CoupleStatus::Active)
    <details class="settings-section section-space" @if($errors->any() || old('kind')) open @endif><summary><x-icon name="gift" /><span class="row__body"><span class="row__title">{{ __('Сердечки партнёру') }}</span><span class="row__meta">{{ __('Поблагодарить или списать с подтверждением') }}</span></span></summary>
        <form method="post" action="{{ route('hearts.store',$couple) }}" class="form-stack" data-submit-lock>
            @csrf
            <input type="hidden" name="operation_key" value="{{ old('operation_key',$operationKey) }}">
            <fieldset><legend>{{ __('Действие') }}</legend><div class="confirmation-actions"><label class="choice-chip choice-chip--radio"><input type="radio" name="kind" value="thanks" @checked(old('kind','thanks') === 'thanks')>{{ __('Поблагодарить') }}</label><label class="choice-chip choice-chip--radio"><input type="radio" name="kind" value="deduction" @checked(old('kind') === 'deduction')>{{ __('Списать сердечки') }}</label></div></fieldset>
            <x-field name="amount" :label="__('Количество сердечек')" type="number" :value="old('amount')" :error="$errors->first('amount')" min="1" :max="$maximum" step="1" required />
            <x-textarea-field name="reason" :label="__('Причина для партнёра')" :value="old('reason')" :error="$errors->first('reason')" maxlength="500" required />
            <p class="field-hint">{{ __('Благодарность: до :daily в день и :weekly в неделю.', ['daily'=>$thanksDaily,'weekly'=>$thanksWeekly]) }}</p>
            <p class="field-hint">{{ __('Списание: до :daily в день и :weekly в неделю.', ['daily'=>$deductionDaily,'weekly'=>$deductionWeekly]) }}</p>
            <p class="field-hint">{{ __('Дневные и недельные ограничения проверяются перед сохранением. Положительный баланс ограничен; начисление у потолка может быть частичным.') }}</p>
            <label class="confirmation-check"><input type="checkbox" name="confirmation" value="1" required>{{ __('Подтверждаю изменение сердечек партнёра') }}</label>
            <x-button type="submit">{{ __('Сохранить') }}</x-button>
        </form>
    </details>
@endif
<h2 class="group-label"><span>{{ __('История сердечек') }}</span></h2>
@forelse($history as $entry)
    <div class="row ledger-entry"><div class="row__body"><strong class="row__title">{{ __($entry->type->label()) }}</strong><span class="row__meta">{{ $entry->created_at->setTimezone($timezone)->format('d.m.Y H:i') }} · {{ __('Баланс после: :balance', ['balance'=>$entry->balance_after]) }}</span>@if($entry->reason)<span class="row__meta request-description">{{ $entry->reason }}</span>@endif</div><strong @class(['amount', 'amount--plus' => $entry->amount > 0, 'amount--minus' => $entry->amount < 0])>{{ sprintf('%+d',$entry->amount) }}</strong></div>
@empty<p class="row row--empty">{{ __('История пока пуста.') }}</p>@endforelse
{{ $history->links() }}

@endsection

@section('navigation')<x-bottom-navigation :couple="$couple" />@endsection
