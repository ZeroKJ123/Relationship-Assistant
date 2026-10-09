@extends('layouts.app')
@section('title', __('Наша пара'))
@section('content')
<x-card tone="pink" class="start-card">
    <x-status>{{ __($couple->status->label()) }}</x-status>
    <h1 class="form-title">@foreach($couple->members as $member){{ $member->user?->name }}@unless($loop->last) {{ __('и') }} @endunless @endforeach</h1>
    <p>{{ __('Вместе с :date · дней вместе: :days', ['date' => $couple->started_on->format('d.m.Y'), 'days' => $daysTogether]) }}</p>
    <p>{{ __('Часовой пояс: :zone', ['zone' => $couple->timezone]) }}</p>
    @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
    @error('invitation')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    @error('couple')<p class="field-error" role="alert">{{ $message }}</p>@enderror
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
<div class="confirmation-actions section-space">
    <a class="button button--quiet" href="{{ route('catalogs.index') }}">{{ __('Каталоги') }}</a>
    <a class="button button--primary" href="{{ route('duties.index', $couple) }}">{{ __('Наши дела') }}</a>
    <a class="button button--quiet" href="{{ route('hearts.index') }}">{{ __('Мои сердечки') }}</a>
    <a class="button button--primary" href="{{ route('requests.index', $couple) }}">{{ __('Наши просьбы') }}</a>
    <a class="button button--primary" href="{{ route('agreements.show', $couple) }}">{{ __('Наши договорённости') }}</a>
    <a class="button button--quiet" href="{{ route('couples.index') }}">{{ __('Все мои пары') }}</a>
    @can('update', $couple)<a class="button button--danger" href="{{ route('couples.end', $couple) }}">{{ __('Завершить пару') }}</a>@endcan
</div>
@endsection
