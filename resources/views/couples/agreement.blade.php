@extends('layouts.app')
@section('title', __('Наши договорённости · Наша пара'))
@section('content')
<x-card tone="yellow" class="start-card">
    <p class="eyebrow">{{ __('Договоримся вместе') }}</p>
    <h1 class="form-title">{{ __('Лимит «Очень хочу»') }}</h1>
    <p>{{ __('Сколько особо желанных просьб каждый из вас может отправить за неделю? Неделя начинается в понедельник по часовому поясу вашей пары: :zone.', ['zone' => $couple->timezone]) }}</p>
    @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
    @foreach($errors->all() as $error)<p class="field-error" role="alert">{{ $error }}</p>@endforeach
    @if($agreement?->active_weekly_limit !== null)
        <x-status tone="success">{{ __('Согласовано: :limit в неделю каждому · версия :version', ['limit' => $agreement->active_weekly_limit, 'version' => $agreement->active_version]) }}</x-status>
        <p>{{ __('При обсуждении нового варианта действует прежнее правило до двух согласий.') }}</p>
    @else
        <x-status tone="warning">{{ __('Лимит ещё не согласован') }}</x-status>
        <p>{{ __('До двух согласий особо желанные просьбы недоступны.') }}</p>
    @endif
    @unless($isEditable)<p>{{ __('В этой паре согласование недоступно. Сохранённая история остаётся для её участников.') }}</p>@endunless
</x-card>
@if($isEditable)
    <x-card class="section-space">
        <h2>{{ __('Ваш ответ') }}</h2>
        <p>{{ __('Выберите число от :min до :max. Когда ответят оба, предложим среднее с округлением вверх. Даже одинаковые ответы требуют двух подтверждений.', ['min' => $minimum, 'max' => $maximum]) }}</p>
        <form method="post" action="{{ route('agreements.answers.store', $couple) }}" class="form-stack" data-submit-lock>
            @csrf<input type="hidden" name="form_kind" value="answer"><input type="hidden" name="expected_version" value="{{ $agreement?->current_version ?? 0 }}">
            <x-weekly-limit-field id="answer-weekly-limit" :value="old('form_kind') === 'answer' ? old('weekly_limit') : $ownAnswer?->weekly_limit" :minimum="$minimum" :maximum="$maximum" :error="$errors->first('weekly_limit')" />
            <x-button type="submit">{{ __('Сохранить мой ответ') }}</x-button>
        </form>
    </x-card>
@endif
@if($agreement)
    <x-card class="section-space form-stack">
        <h2>{{ __('Ответы партнёров') }}</h2>
        @foreach($agreement->answers as $answer)<p>{{ __(':name: :limit в неделю', ['name' => $answer->user?->name, 'limit' => $answer->weekly_limit]) }}</p>@endforeach
        @if($agreement->answers->count() < 2)<p>{{ __('Ждём ответ второго партнёра.') }}</p>@endif
    </x-card>
@endif
@if($proposal)
    <x-card tone="purple" class="section-space form-stack">
        <h2>{{ __('Вариант :version: :limit в неделю', ['version' => $proposal->version, 'limit' => $proposal->weekly_limit]) }}</h2>
        <x-status>{{ __($proposal->status->label()) }}</x-status>
        <p>{{ __('Согласий: :count из 2', ['count' => $proposal->approvals->count()]) }}</p>
        @if($isEditable && $proposal->status === \App\Enums\AgreementProposalStatus::Proposed)
            @if($hasApproved)
                <p>{{ __('Вы уже согласились с этой версией. Ждём партнёра.') }}</p>
            @else
                <form method="post" action="{{ route('agreements.approvals.store', [$couple, $proposal]) }}" class="form-stack" data-submit-lock>
                    @csrf<label class="confirmation-check"><input type="checkbox" name="confirmation" value="1" required> {{ __('Согласен с :limit просьбами в неделю для каждого', ['limit' => $proposal->weekly_limit]) }}</label>
                    <x-button type="submit">{{ __('Подтвердить этот вариант') }}</x-button>
                </form>
            @endif
            <form method="post" action="{{ route('agreements.approvals.destroy', [$couple, $proposal]) }}" class="form-stack" data-submit-lock>
                @csrf @method('delete')<label class="confirmation-check"><input type="checkbox" name="confirmation" value="1" required> {{ __('Хочу отклонить этот вариант и обсудить другой') }}</label>
                <x-button type="submit" variant="quiet">{{ __('Отклонить вариант') }}</x-button>
            </form>
        @endif
    </x-card>
@endif
@if($isEditable && $agreement?->answers->count() === 2)
    <x-card class="section-space">
        <h2>{{ __('Предложить другой вариант') }}</h2>
        <p>{{ __('Новый вариант заменит обсуждаемый. Каждый подтвердит его заново, включая автора предложения.') }}</p>
        <form method="post" action="{{ route('agreements.proposals.store', $couple) }}" class="form-stack" data-submit-lock>
            @csrf<input type="hidden" name="form_kind" value="proposal"><input type="hidden" name="expected_version" value="{{ $agreement->current_version }}">
            <x-weekly-limit-field id="proposal-weekly-limit" :value="old('form_kind') === 'proposal' ? old('weekly_limit') : null" :minimum="$minimum" :maximum="$maximum" />
            <x-button type="submit" variant="quiet">{{ __('Предложить вариант') }}</x-button>
        </form>
    </x-card>
@endif
@if($history?->isNotEmpty())
    <div class="form-stack section-space"><h2>{{ __('История вариантов') }}</h2>
        @foreach($history as $item)
            <x-card><h3>{{ __('Версия :version · :limit в неделю', ['version' => $item->version, 'limit' => $item->weekly_limit]) }}</h3><p>{{ __($item->status->label()) }} · {{ $item->author?->name ?? __('Предложенный компромисс') }}</p></x-card>
        @endforeach
    </div>
    {{ $history->links() }}
@endif
<p class="form-footer"><a href="{{ route('couples.show', $couple) }}">{{ __('Вернуться к паре') }}</a></p>
@endsection
