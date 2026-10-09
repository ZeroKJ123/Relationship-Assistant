@extends('layouts.app')
@section('title', __('Наши дела'))
@section('content')
<x-card class="start-card"><h1>{{ __('Наши дела') }}</h1><p>{{ __('Согласие создаёт дело. Отметьте выполнение, затем дождитесь проверки партнёром.') }}</p></x-card>
@forelse($duties as $duty)
    <x-card class="section-space form-stack">
        <x-status>{{ __($duty->status->label()) }}</x-status>
        <h2>{{ __($duty->kind->label()) }}</h2>
        <p>{{ __('Исполнитель: :name', ['name'=>$duty->executor->name]) }}</p>
        <p class="request-description">{{ $duty->description }}</p>
        <p>{{ __('Срок: :date', ['date'=>$duty->due_at->setTimezone($duty->timezone)->format('d.m.Y H:i')]) }}</p>
        <a class="button button--quiet" href="{{ route('duties.show',[$couple,$duty]) }}">{{ __('Открыть дело') }}</a>
    </x-card>
@empty
    <x-card class="section-space"><p>{{ __('Дела появятся после принятия просьбы или встречного условия.') }}</p></x-card>
@endforelse
{{ $duties->links() }}
<a class="button button--quiet section-space" href="{{ route('couples.show',$couple) }}">{{ __('Наша пара') }}</a>
@endsection
