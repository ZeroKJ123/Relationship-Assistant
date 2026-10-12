@extends('layouts.app')
@section('title', __('Комплименты партнёру · Любимость'))
@section('content')
<h1 class="screen-title">{{ __('Комплименты партнёру') }}</h1>
<x-form-feedback />
<p class="lead">{{ __('Эти слова будут появляться у партнёра на главной и меняться каждые пять минут.') }}</p><form method="post" action="{{ route('profile.compliments.store') }}" class="form-stack" data-submit-lock data-repeat-editor data-repeat-limit="50">@csrf
<div data-repeat-fields>@foreach(old('compliments',$user->compliments ?? []) as $text)<x-field name="compliments[]" :id="'compliment-'.$loop->index" :label="__('Комплимент')" :value="$text" maxlength="300" />@endforeach<x-field name="compliments[]" id="compliment-new" :label="__('Новый комплимент')" maxlength="300" /></div>
<x-button type="button" variant="quiet" data-repeat-add>{{ __('Добавить ещё') }}</x-button><x-button type="submit">{{ __('Сохранить комплименты') }}</x-button></form><p class="field-hint section-space">{{ __('Очистите строку, чтобы удалить комплимент.') }}</p>
@endsection
@section('navigation')<x-bottom-navigation :couple="$couple" />@endsection
