@extends('layouts.app')
@section('title', __('Мои сердечки'))
@section('content')
<x-card class="start-card form-stack">
    <h1>{{ __('Мои сердечки') }}</h1><p class="form-title">♥ {{ $wallet->balance }}</p>
    <p>{{ __('Баланс личный и сохраняется при смене пары. Все изменения видны в истории.') }}</p>
    @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
    @if($errors->any())<div role="alert">@foreach($errors->all() as $error)<p class="field-error">{{ $error }}</p>@endforeach</div>@endif
</x-card>
@if($couple?->status === \App\Enums\CoupleStatus::Active)
    <x-card class="section-space"><h2>{{ __('Сердечки партнёру') }}</h2>
        <form method="post" action="{{ route('hearts.store',$couple) }}" class="form-stack" data-submit-lock>
            @csrf
            <input type="hidden" name="operation_key" value="{{ old('operation_key',$operationKey) }}">
            <div class="field"><label for="kind">{{ __('Действие') }}</label><select name="kind" id="kind"><option value="thanks">{{ __('Поблагодарить') }}</option><option value="deduction" @selected(old('kind') === 'deduction')>{{ __('Списать сердечки') }}</option></select></div>
            <x-field name="amount" :label="__('Количество сердечек')" type="number" :value="old('amount',5)" min="1" :max="$maximum" step="1" required />
            <div class="field"><label for="reason">{{ __('Причина для партнёра') }}</label><textarea name="reason" id="reason" rows="3" maxlength="500" required>{{ old('reason') }}</textarea></div>
            <p>{{ __('Благодарность: до :daily в день и :weekly в неделю.', ['daily'=>$thanksDaily,'weekly'=>$thanksWeekly]) }}</p>
            <p>{{ __('Списание: до :daily в день и :weekly в неделю.', ['daily'=>$deductionDaily,'weekly'=>$deductionWeekly]) }}</p>
            <p>{{ __('Дневные и недельные ограничения проверяются перед сохранением. Положительный баланс ограничен; начисление у потолка может быть частичным.') }}</p>
            <label class="confirmation-check"><input type="checkbox" name="confirmation" value="1" required>{{ __('Подтверждаю изменение сердечек партнёра') }}</label>
            <x-button type="submit">{{ __('Сохранить') }}</x-button>
        </form>
    </x-card>
@endif
<x-card class="section-space form-stack"><h2>{{ __('История сердечек') }}</h2>
    @foreach($history as $entry)
        <div><strong>{{ __($entry->type->label()) }} · {{ sprintf('%+d',$entry->amount) }}</strong><p>{{ $entry->created_at->setTimezone($timezone)->format('d.m.Y H:i') }} · {{ __('Баланс после: :balance', ['balance'=>$entry->balance_after]) }}</p>@if($entry->reason)<p class="request-description">{{ $entry->reason }}</p>@endif</div>
    @endforeach
</x-card>
{{ $history->links() }}
<a class="button button--quiet section-space" href="{{ route('couples.index') }}">{{ __('Мои пары') }}</a>
@endsection
