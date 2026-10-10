@extends('layouts.app')
@section('title', __('Не удалось открыть главную'))
@section('content')
<x-card class="form-stack" role="alert"><h1 class="form-title">{{ __('Не удалось загрузить главную') }}</h1><p>{{ __('Ваши данные сохранены. Попробуйте открыть страницу ещё раз.') }}</p><a class="button button--primary" href="{{ route('home') }}">{{ __('Повторить') }}</a><p>{{ __('Номер обращения: :reference', ['reference'=>$reference]) }}</p><a href="{{ route('profile.show') }}">{{ __('Мой профиль') }}</a></x-card>
@endsection
