@extends('layouts.app')
@section('title', __('Мои пары · Наша пара'))
@section('content')
<x-card class="start-card">
    <p class="eyebrow">{{ __('Ваша история') }}</p>
    <h1 class="form-title">{{ __('Мои пары') }}</h1>
    @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
    @if($current)
        <a class="button button--primary" href="{{ route('couples.show', $current->couple_id) }}">{{ __('Открыть текущую пару') }}</a>
    @else
        <p>{{ __('Пока нет активной пары. Создайте приглашение или откройте ссылку партнёра.') }}</p>
        <a class="button button--primary" href="{{ route('couples.create') }}">{{ __('Пригласить партнёра') }}</a>
    @endif
</x-card>
<div class="form-stack">
    @foreach($history as $couple)
        <x-card>
            <h2>@foreach($couple->members as $member){{ $member->user?->name }}@unless($loop->last) {{ __('и') }} @endunless @endforeach</h2>
            <p>{{ __('Вместе с :date', ['date' => $couple->started_on->format('d.m.Y')]) }} · {{ __($couple->status->label()) }}</p>
            <a class="button button--quiet form-footer" href="{{ route('couples.show', $couple) }}">{{ __('Открыть историю') }}</a>
        </x-card>
    @endforeach
</div>
{{ $history->links() }}
@endsection
