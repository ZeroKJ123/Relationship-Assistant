@extends('layouts.app')
@section('title', __('Мои сердечки'))
@section('content')
<x-card class="start-card form-stack">
    <h1 class="form-title">{{ __('Мои сердечки') }}</h1><p class="wallet-total"><span aria-hidden="true">♥</span> {{ $wallet->balance }}</p>
    <p>{{ __('Свободно: :available · В резерве: :reserved', ['available'=>$available,'reserved'=>$reserved]) }}</p>
    <p>{{ __('Баланс личный и сохраняется при смене пары. Все изменения видны в истории.') }}</p>
    <x-form-feedback />
</x-card>
@if($couple?->status === \App\Enums\CoupleStatus::Active)
    <x-card class="section-space settings-card"><h2>{{ __('Сердечки партнёру') }}</h2>
        <form method="post" action="{{ route('hearts.store',$couple) }}" class="form-stack" data-submit-lock>
            @csrf
            <input type="hidden" name="operation_key" value="{{ old('operation_key',$operationKey) }}">
            <div class="field"><label for="kind">{{ __('Действие') }}</label><select name="kind" id="kind"><option value="thanks">{{ __('Поблагодарить') }}</option><option value="deduction" @selected(old('kind') === 'deduction')>{{ __('Списать сердечки') }}</option></select></div>
            <x-field name="amount" :label="__('Количество сердечек')" type="number" :value="old('amount')" :error="$errors->first('amount')" min="1" :max="$maximum" step="1" required />
            <x-textarea-field name="reason" :label="__('Причина для партнёра')" :value="old('reason')" :error="$errors->first('reason')" maxlength="500" required />
            <p>{{ __('Благодарность: до :daily в день и :weekly в неделю.', ['daily'=>$thanksDaily,'weekly'=>$thanksWeekly]) }}</p>
            <p>{{ __('Списание: до :daily в день и :weekly в неделю.', ['daily'=>$deductionDaily,'weekly'=>$deductionWeekly]) }}</p>
            <p>{{ __('Дневные и недельные ограничения проверяются перед сохранением. Положительный баланс ограничен; начисление у потолка может быть частичным.') }}</p>
            <label class="confirmation-check"><input type="checkbox" name="confirmation" value="1" required>{{ __('Подтверждаю изменение сердечек партнёра') }}</label>
            <x-button type="submit">{{ __('Сохранить') }}</x-button>
        </form>
    </x-card>
@endif
<x-card class="section-space form-stack"><h2>{{ __('История сердечек') }}</h2>
    @forelse($history as $entry)
        <div class="ledger-entry"><strong>{{ __($entry->type->label()) }} · {{ sprintf('%+d',$entry->amount) }}</strong><p>{{ $entry->created_at->setTimezone($timezone)->format('d.m.Y H:i') }} · {{ __('Баланс после: :balance', ['balance'=>$entry->balance_after]) }}</p>@if($entry->reason)<p class="request-description">{{ $entry->reason }}</p>@endif</div>
    @empty<p>{{ __('История пока пуста.') }}</p>@endforelse
</x-card>
{{ $history->links() }}

@endsection

@section('navigation')<x-bottom-navigation :couple="$couple" />@endsection
