@extends('layouts.app')
@section('title', __('Наша пара'))
@section('content')
<x-card tone="pink" class="start-card">
    <x-status>{{ __($couple->status->label()) }}</x-status>
    <h1 class="form-title">@foreach($couple->members as $member){{ $member->user?->name }}@unless($loop->last) {{ __('и') }} @endunless @endforeach</h1>
    <p>{{ __('Вместе с :date · дней вместе: :days', ['date' => $couple->started_on->format('d.m.Y'), 'days' => $daysTogether]) }} ({{ $durationTogether }})</p>@if($couple->married_on)<p>{{ __('Свадьба: :date', ['date'=>$couple->married_on->format('d.m.Y')]) }}</p>@endif

    @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
    @error('invitation')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    @error('couple')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    <x-form-feedback />
</x-card>
@if($couple->status === \App\Enums\CoupleStatus::Pending)
    <x-card class="section-space form-stack">
        <h2>{{ __('Ждём партнёра') }}</h2>
        @if($invitationLink)
            <div class="field"><label for="invitation-link">{{ __('Ваша ссылка') }}</label><textarea id="invitation-link" readonly rows="3">{{ $invitationLink }}</textarea></div>
            <p>{{ __('Скопируйте ссылку и отправьте партнёру. Она показывается один раз. Делитесь ею только с тем, кого хотите пригласить.') }}</p>
        @endif
        @if($invitation)
            <p>{{ __('Приглашение действует до :date', ['date' => $invitation->expires_at->setTimezone($couple->timezone)->format('d.m.Y H:i')]) }}</p>
            <form method="post" action="{{ route('invitations.destroy', $invitation) }}" data-submit-lock>@csrf @method('delete')<x-button type="submit" variant="quiet">{{ __('Отменить приглашение') }}</x-button></form>
        @else
            <p>{{ __('Действующего приглашения нет.') }}</p>
        @endif
        <form method="post" action="{{ route('invitations.store') }}" data-submit-lock>
            @csrf<input type="hidden" name="started_on" value="{{ $couple->started_on->toDateString() }}"><input type="hidden" name="timezone" value="{{ $couple->timezone }}">
            <x-button type="submit">{{ __('Выпустить новую ссылку') }}</x-button>
        </form>
    </x-card>
@endif
@if($couple->status === \App\Enums\CoupleStatus::Ended)
    <x-card class="section-space"><h2>{{ __('История сохранена') }}</h2><p>{{ __('Пара завершена. История доступна только её прежним участникам. Новые совместные действия закрыты.') }}</p></x-card>
@endif
<x-mood-summary :couple="$couple" :latest-moods="$latestMoods" />
<h2 class="group-label"><span>{{ __('Разделы пары') }}</span></h2>
<nav class="settings-links" aria-label="{{ __('Разделы пары') }}">
<a href="{{ route('reminders.index',$couple) }}">{{ __('Напоминания') }}</a>
<a href="{{ route('friends.index') }}">{{ __('Мои друзья') }}</a>@can('update',$couple)<a href="{{ route('guests.create',$couple) }}">{{ __('Позвать гостей') }}</a><a href="{{ route('dates.create',$couple) }}">{{ __('Свидание / мероприятие') }}</a>@endcan
<a href="{{ route('dishes.index') }}">{{ __('Кухня') }}</a><a href="{{ route('wishlist.index') }}">{{ __('Виш-лист') }}</a>@can('update',$couple)<a href="{{ route('money.create',$couple) }}">{{ __('Попросить деньги') }}</a>@endcan
    <a href="{{ route('catalogs.index') }}">{{ __('Каталоги') }}</a>
    <a href="{{ route('duties.index', $couple) }}">{{ __('Наши дела') }}</a>
    <a href="{{ route('hearts.index') }}">{{ __('Мои сердечки') }}</a>
    <a href="{{ route('requests.index', $couple) }}">{{ __('Наши просьбы') }}</a>
    <a href="{{ route('agreements.show', $couple) }}">{{ __('Наши договорённости') }}</a>
</nav>
<div class="section-space">@can('update', $couple)<a class="button button--danger" href="{{ route('couples.end', $couple) }}">{{ __('Завершить пару') }}</a>@endcan</div>
@if($couple->status === \App\Enums\CoupleStatus::Active)
<x-card class="section-space"><h2>{{ __('Даты нашей истории') }}</h2><p>{{ $daysTogether }} {{ __('дней') }} ({{ $durationTogether }})</p><form method="post" action="{{ route('couples.dates',$couple) }}" class="form-stack" data-submit-lock>@csrf @method('patch')<input type="hidden" name="expected_version" value="{{ $couple->dates_version }}"><x-field name="started_on" type="date" :label="__('Начало отношений')" :value="old('started_on',$couple->started_on->toDateString())" required /><x-field name="married_on" type="date" :label="__('Дата свадьбы — если поженились')" :value="old('married_on',$couple->married_on?->toDateString())" /><p>{{ __('Если пока не поженились, оставьте дату свадьбы пустой.') }}</p><x-button type="submit">{{ __('Сохранить даты') }}</x-button></form></x-card>
@endif
@endsection
