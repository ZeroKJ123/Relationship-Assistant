@extends('layouts.app')
@section('title', __('Выбор фильма'))
@section('content')
<h1>{{ __('Что посмотрим вместе?') }}</h1><x-form-feedback />
@if($choice?->status === \App\Enums\SelectionStatus::Waiting)
<x-card class="form-stack"><h2>{{ __('Ждём согласия обоих') }}</h2><p>{{ __('После двух подтверждений карточки откроются у вас одновременно. Вправо — нравится, влево — пропустить. Остановимся на первом совпадении.') }}</p>
<p>{{ __('Готовы: :count из 2',['count'=>(int)$choice->first_ready+(int)$choice->second_ready]) }}</p>
@if(($actor->id === $choice->first_user_id && !$choice->first_ready) || ($actor->id === $choice->second_user_id && !$choice->second_ready))
<form method="post" action="{{ route('movies.selections.update',[$couple,$choice]) }}" class="form-stack" data-submit-lock>@csrf @method('patch')<input type="hidden" name="command" value="answer"><label class="confirmation-check"><input type="checkbox" name="confirmation" value="1" required>{{ __('Готов начать выбор') }}</label><x-button name="answer" value="accept" type="submit">{{ __('Подтвердить') }}</x-button></form>
@endif
<form method="post" action="{{ route('movies.selections.update',[$couple,$choice]) }}" data-submit-lock>@csrf @method('patch')<input type="hidden" name="command" value="answer"><x-button name="answer" value="decline" type="submit" variant="quiet">{{ __('Отменить выбор') }}</x-button></form></x-card>
@elseif($choice?->status === \App\Enums\SelectionStatus::Active && $choice->mode === 'swipe')
@if($nextMovie)<x-card class="section-space movie-swipe" data-movie-swipe><x-movie-details :movie="$nextMovie" /><p>{{ $nextMovie->description }}</p><form method="post" action="{{ route('movies.selections.update',[$couple,$choice]) }}" class="confirmation-actions" data-submit-lock>@csrf @method('patch')<input type="hidden" name="command" value="vote"><input type="hidden" name="movie_id" value="{{ $nextMovie->id }}"><x-button type="submit" name="liked" value="0" variant="quiet" data-movie-skip>{{ __('Пропустить') }}</x-button><x-button type="submit" name="liked" value="1" data-movie-like>{{ __('Хочу посмотреть') }} ♥</x-button></form></x-card>@else<x-card><p>{{ __('Вы всё просмотрели. Ждём выбора партнёра.') }}</p></x-card>@endif
@elseif($choice?->status === \App\Enums\SelectionStatus::Matched)
<x-card class="section-space form-stack" tone="pink"><h2>{{ $choice->mode === 'swipe' ? __('У вас совпало желание!') : __('Рулетка выбрала фильм') }}</h2>@if($selected)<x-movie-details :movie="$selected" />@endif</x-card>
@elseif($choice?->status === \App\Enums\SelectionStatus::Exhausted)<x-card><p>{{ __('Вы просмотрели все фильмы. Совпадения не нашлось — можно попробовать рулетку.') }}</p></x-card>
@elseif($choice?->status === \App\Enums\SelectionStatus::Declined)<x-card><p>{{ __('Совместный выбор отменён') }}</p></x-card>@endif
@if($choice?->mode === 'roulette')
<x-card class="section-space"><div class="wheel-pointer" aria-hidden="true">▼</div><div class="movie-wheel movie-wheel--films @if($choice->selected_movie_id) movie-wheel--result @endif" @if($choice->selected_movie_id) data-wheel-result="movie-{{ $choice->id }}" @endif style="--film-count:{{ max(1,$films->count()) }};--result-rotation:{{ $rotation }}deg;" aria-label="{{ __('Колесо фильмов') }}">@foreach($films as $film)<span class="wheel-label" style="--index:{{ $loop->index }}">{{ \Illuminate\Support\Str::limit($film->title,14) }}</span>@endforeach</div>
@if($choice->status === \App\Enums\SelectionStatus::Active)<form method="post" action="{{ route('movies.selections.update',[$couple,$choice]) }}" data-submit-lock>@csrf @method('patch')<input type="hidden" name="command" value="spin"><x-button type="submit">{{ __('Крутить колесо') }}</x-button></form>@endif</x-card>
@endif
@if(!$choice || !in_array($choice->status,[\App\Enums\SelectionStatus::Waiting,\App\Enums\SelectionStatus::Active],true))
<x-card class="section-space form-stack"><h2>{{ __('Новый выбор') }}</h2><p>{{ __('Все фильмы из ваших двух списков. До 100 разных фильмов за один выбор.') }}</p><form method="post" action="{{ route('movies.selections.store',$couple) }}" class="confirmation-actions" data-submit-lock>@csrf<input type="hidden" name="operation_key" value="{{ $operationKey }}"><x-button type="submit" name="mode" value="swipe">{{ __('Пригласить к свайпам') }}</x-button><x-button type="submit" name="mode" value="roulette" variant="quiet">{{ __('Выбрать рулеткой') }}</x-button></form></x-card>@endif
<a class="button button--quiet section-space" href="{{ route('movies.index') }}">{{ __('Мои фильмы') }}</a>
@endsection
