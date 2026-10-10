@extends('layouts.app')
@section('title', __('Наша пара'))
@section('content')
<div class="dashboard" data-dashboard>
    <section class="dashboard-greeting" aria-labelledby="greeting-title">
        <div><p class="eyebrow">{{ __('Для вас двоих') }}</p><h1 id="greeting-title">{{ __('Привет, :name', ['name'=>$dashboard->actor->name]) }} <span aria-hidden="true">♡</span></h1><p>{{ __('Забота начинается с маленьких вещей') }}</p></div>
        <div class="partner-summary">
            <x-avatar :name="$dashboard->partner?->name ?? $dashboard->actor->name" :src="!$dashboard->partner && $dashboard->actor->avatar_path ? route('profile.avatar') : null" />
            <strong>{{ $dashboard->partner?->name ?? __('Ваша история впереди') }}</strong>
            @if($dashboard->partner)
                <p>{{ __('Вместе :days дн.', ['days'=>$dashboard->togetherDays]) }} ({{ $dashboard->togetherDuration }})</p>
                @if($dashboard->couple->married_on)
                    <p>{{ __('Свадьба: :date', ['date'=>$dashboard->couple->married_on->format('d.m.Y')]) }}</p>
                @endif
            @else
                <p>{{ __('Пригласите партнёра в ваше пространство') }}</p>
            @endif
        </div>
    </section>
    <x-form-feedback />
    @if($dashboard->pendingMoodPrompt?->is_manual)<x-card tone="purple" class="section-space form-stack"><p>{{ __('Партнёр спрашивает, как вы себя чувствуете.') }}</p><a class="button button--primary" href="{{ route('mood.index',$dashboard->couple) }}">{{ __('Ответить о настроении') }}</a></x-card>@endif
    <div class="dashboard-summary">
        <x-card tone="purple" class="form-stack">
            <h2>{{ __('Настроение партнёра') }}</h2>
            @forelse($dashboard->latestMoods as $entry)
                <div class="partner-mood">
                    @if($entry->emoji)<span class="partner-mood__emoji" aria-label="{{ __('Эмоджи настроения партнёра') }}">{{ $entry->emoji }}</span>@endif
                    <div><p class="partner-mood__level">{{ $entry->level->label() }}</p><p>{{ __('Так сейчас чувствует себя :name', ['name'=>$entry->user->name]) }}</p></div>
                </div>
                <p class="field-hint">{{ __('Партнёр поделился настроением :date', ['date'=>$entry->recorded_at->setTimezone($dashboard->couple->timezone)->format('d.m H:i')]) }}</p>
            @empty<p>{{ __('Партнёр пока не поделился настроением. Спросите, как он себя чувствует.') }}</p>@endforelse
            @if($dashboard->couple)<x-mood-request :couple="$dashboard->couple" compact />@endif
        </x-card>
        <x-card tone="yellow" class="form-stack">
            <h2>{{ __('Мои сердечки') }}</h2><a class="wallet-total" href="{{ route('hearts.index') }}" aria-label="{{ __('Баланс :amount сердечек. Открыть историю', ['amount'=>$dashboard->wallet->balance]) }}"><span aria-hidden="true">♥</span> {{ $dashboard->wallet->balance }}</a>
            <p>{{ __('Свободно: :amount', ['amount'=>$dashboard->wallet->available]) }}<br>{{ __('В резерве: :amount', ['amount'=>$dashboard->wallet->reserved]) }}</p>
            @if($dashboard->wallet->balance < 0)<p>{{ __('Баланс в минусе. Обычная бесплатная еда и выполнение дел доступны.') }}</p>@endif
        </x-card>
    </div>
    @if(!$dashboard->couple || !$dashboard->partner)<x-card class="section-space form-stack"><h2>{{ __('Начните вашу историю') }}</h2><p>{{ __('Создайте пару или отправьте приглашение. Личные блюда и желания можно добавлять уже сейчас.') }}</p><a class="button button--primary" href="{{ $dashboard->couple ? route('couples.show',$dashboard->couple) : route('couples.index') }}">{{ $dashboard->couple ? __('Пригласить партнёра') : __('Создать пару') }}</a></x-card>@endif
    <nav class="dashboard-tiles" aria-label="{{ __('Быстрые действия') }}">
        <a class="card card--yellow" href="{{ route('dishes.index',$dashboard->partner ? ['owner_id'=>$dashboard->partner->id] : []) }}"><span aria-hidden="true">♨</span><strong>{{ __('Кухня') }}</strong></a>
        <a class="card card--purple" href="{{ $dashboard->partner ? route('requests.create',$dashboard->couple) : route('couples.index') }}"><span aria-hidden="true">☏</span><strong>{{ __('Попросить') }}</strong></a>
        <a class="card card--pink" href="{{ $dashboard->partner ? route('dates.create',$dashboard->couple) : route('couples.index') }}"><span aria-hidden="true">♥</span><strong>{{ __('Свидание') }}</strong></a>
        <a class="card card--yellow" href="{{ route('wishlist.index') }}"><span aria-hidden="true">♧</span><strong>{{ __('Виш-лист') }}</strong></a>
        <a class="card card--purple" href="{{ route('movies.index') }}"><span aria-hidden="true">🎬</span><strong>{{ __('Фильмы') }}</strong></a>
        <a class="card card--yellow" href="{{ route('cars.index') }}"><span aria-hidden="true">🚗</span><strong>{{ __('Мои машины') }}</strong></a>
        @if($dashboard->partner)<a class="card card--pink" href="{{ route('requests.create',[$dashboard->couple, 'type'=>'rating']) }}"><span aria-hidden="true">⭐</span><strong>{{ __('Оценить поступок') }}</strong></a>@endif
    </nav>
    @if($dashboard->isAgreementPending && $dashboard->partner)<x-card class="section-space form-stack"><h2>{{ __('Осталось согласовать недельный лимит') }}</h2><p>{{ __('Обычные просьбы уже доступны. Для «Очень хочу» нужно общее решение.') }}</p><a href="{{ route('agreements.show',$dashboard->couple) }}">{{ __('Согласовать лимит') }}</a></x-card>@endif
    <section class="section-space" aria-labelledby="my-duties-title"><div class="section-heading"><h2 id="my-duties-title">{{ __('Мои дела') }}</h2>@if($dashboard->couple)<a href="{{ route('duties.index',$dashboard->couple) }}">{{ __('Все') }} →</a>@endif</div>
        <div class="form-stack">@forelse($dashboard->duties as $duty)<x-card class="dashboard-duty"><div><h3>{{ $duty->description }}</h3><p>{{ __($duty->status->label()) }} · {{ $duty->due_at->setTimezone($dashboard->couple->timezone)->format('d.m H:i') }}</p></div><a class="button button--quiet" href="{{ route('duties.show',[$dashboard->couple,$duty]) }}">{{ __('Открыть') }}</a></x-card>@empty<x-card><p>{{ __('Срочных дел нет. Можно запланировать что-нибудь приятное.') }}</p></x-card>@endforelse</div>
    </section>
    @if($dashboard->newRequest)<x-card class="section-space dashboard-duty"><div><h2>{{ $dashboard->newRequest->status === \App\Enums\RequestStatus::Conditional ? __('Условие от :name', ['name'=>$dashboard->newRequest->recipient->name]) : __('Новая просьба от :name', ['name'=>$dashboard->newRequest->author->name]) }}</h2><p class="request-description">{{ $dashboard->newRequest->description }}</p></div><a class="button button--primary" href="{{ route('requests.show',[$dashboard->couple,$dashboard->newRequest]) }}">{{ __('Открыть') }}</a></x-card>@endif
    <p class="page-transition" role="status" hidden data-page-transition>{{ __('Открываем страницу…') }}</p>
</div>
@endsection
@section('navigation')<x-bottom-navigation :couple="$dashboard->couple" />@endsection
