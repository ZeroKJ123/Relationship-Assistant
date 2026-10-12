@extends('layouts.app')
@section('title', __('Колесо свиданий'))
@section('content')
<h1 class="screen-title">{{ __('Колесо свиданий') }}</h1>
<p class="lead">{{ __('Соберите идеи и доверьте выбор колесу. Выпавшую идею можно сразу превратить в приглашение.') }}</p>
<x-form-feedback />
@if($canSpin)
<div class="section-space"><div class="wheel-pointer" aria-hidden="true">▼</div>
<div class="movie-wheel movie-wheel--films @if($picked) movie-wheel--result @endif" style="--film-count:{{ $ideas->count() }};--result-rotation:{{ $rotation }}deg;" role="img" aria-label="{{ __('Колесо свиданий') }}">@foreach($ideas as $idea)<span class="wheel-label" style="--index:{{ $loop->index }}">{{ \Illuminate\Support\Str::limit($idea->title,14) }}</span>@endforeach</div></div>
@if($picked)<x-card tone="yellow" class="form-stack wheel-outcome" role="status"><h2>{{ __('Выпало: :title', ['title'=>$picked->title]) }}</h2>@if($canEdit)<a class="button button--primary" href="{{ route('dates.create',[$couple,'idea_id'=>$picked->id]) }}">{{ __('Пригласить на это свидание') }}</a>@endif</x-card>@endif
@if($canEdit)<form method="post" action="{{ route('date_ideas.spin',$couple) }}" class="section-space" data-submit-lock>@csrf<x-button type="submit" :variant="$picked ? 'quiet' : 'primary'" class="button--wide">{{ $picked ? __('Крутить ещё раз') : __('Крутить колесо') }}</x-button></form>@endif
@else<p class="hint section-space"><x-icon name="info" /><span>{{ __('Добавьте хотя бы две идеи, чтобы появилось колесо.') }}</span></p>@endif
<h2 class="group-label"><span>{{ __('Идеи') }}</span><span>{{ __(':count из :maximum', ['count'=>$ideas->count(),'maximum'=>$maximum]) }}</span></h2>
@forelse($ideas as $idea)
<details class="settings-section"><summary><x-icon name="date" /><span class="row__body"><span class="row__title">{{ $idea->title }}</span></span></summary>
@if($canEdit)<div class="form-stack"><a class="button button--quiet" href="{{ route('dates.create',[$couple,'idea_id'=>$idea->id]) }}">{{ __('Пригласить без колеса') }}</a><form method="post" action="{{ route('date_ideas.destroy',[$couple,$idea]) }}" class="form-stack" data-submit-lock>@csrf @method('delete')<label class="confirmation-check"><input type="checkbox" name="confirmation" value="1" required>{{ __('Подтверждаю удаление идеи') }}</label><x-button type="submit" variant="quiet">{{ __('Удалить') }}</x-button></form></div>@endif
</details>
@empty<p class="row row--empty">{{ __('Идей пока нет. Например: «Пикник в парке», «Настолки дома», «Кино под пледом».') }}</p>@endforelse
@if($canEdit && $ideas->count() < $maximum)
<form method="post" action="{{ route('date_ideas.store',$couple) }}" class="form-stack" data-submit-lock>@csrf
<input type="hidden" name="operation_key" value="{{ old('operation_key',$operationKey) }}">
<x-field name="title" :label="__('Новая идея')" :value="old('title')" :error="$errors->first('title')" maxlength="120" required />
<x-button type="submit" variant="quiet">{{ __('Добавить идею') }}</x-button></form>
@endif
@endsection

@section('navigation')<x-bottom-navigation :couple="$couple" />@endsection
