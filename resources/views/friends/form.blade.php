@extends('layouts.app')
@section('title', __('Друг'))
@section('content')
<x-card class="auth-card"><h1>{{ $friend ? __('Редактировать друга') : __('Добавить друга') }}</h1><x-form-feedback /><form method="post" action="{{ $friend ? route('friends.update',$friend) : route('friends.store') }}" class="form-stack" data-submit-lock>@csrf @if($friend) @method('put') @endif<input type="hidden" name="operation_key" value="{{ old('operation_key',$operationKey) }}"><input type="hidden" name="expected_version" value="{{ old('expected_version',$friend?->version ?? 0) }}"><x-field name="name" :label="__('Имя друга')" :value="old('name',$friend?->name ?? '')" maxlength="120" required /><x-button type="submit">{{ __('Сохранить') }}</x-button></form></x-card><a class="button button--quiet section-space" href="{{ route('friends.index') }}">{{ __('К друзьям') }}</a>
@endsection
