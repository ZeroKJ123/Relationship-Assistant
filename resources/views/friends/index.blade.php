@extends('layouts.app')
@section('title', __('Мои друзья'))
@section('content')
<h1>{{ __('Мои друзья') }}</h1><p>{{ __('Личный список для приглашения гостей. Партнёр видит только состав отправленной просьбы.') }}</p>
<nav class="confirmation-actions section-space" aria-label="{{ __('Список друзей') }}"><a class="button button--quiet" href="{{ route('friends.index') }}">{{ __('Активные') }}</a><a class="button button--quiet" href="{{ route('friends.index',['archive'=>1]) }}">{{ __('Архив') }}</a><a class="button button--primary" href="{{ route('friends.create') }}">{{ __('Добавить друга') }}</a></nav>
<x-form-feedback />
<div class="product-grid section-space">@forelse($friends as $friend)<x-card class="form-stack"><h2>{{ $friend->name }}</h2>@unless($isArchive)<a class="button button--quiet" href="{{ route('friends.edit',$friend) }}">{{ __('Редактировать') }}</a><form method="post" action="{{ route('friends.destroy',$friend) }}" class="form-stack" data-submit-lock>@csrf @method('delete')<input type="hidden" name="expected_version" value="{{ $friend->version }}"><label class="confirmation-check"><input type="checkbox" name="confirmation" value="1" required>{{ __('Подтверждаю перенос друга в архив') }}</label><x-button type="submit" variant="quiet">{{ __('В архив') }}</x-button></form>@else<x-status>{{ __('В архиве') }}</x-status>@endunless</x-card>@empty<x-card><p>{{ __('В этом списке пока нет друзей.') }}</p></x-card>@endforelse</div>
{{ $friends->links() }}<a class="button button--quiet section-space" href="{{ route('couples.index') }}">{{ __('К моим парам') }}</a>
@endsection
