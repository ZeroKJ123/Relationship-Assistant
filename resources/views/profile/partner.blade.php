@extends('layouts.app')
@section('title', __('Профиль партнёра'))
@section('content')
<x-card class="auth-card profile-summary form-stack">
<x-avatar :name="$profile->name" :src="$profile->avatar_path ? route('profiles.avatar', [$profile, 'v'=>$profile->updated_at?->timestamp]) : null" />
<h1>{{ $profile->name }}</h1><p class="request-description">{{ $profile->bio ?: __('Пока без описания') }}</p>
<p>{{ __('Рецептов: :count', ['count'=>$recipeCount]) }}</p>
<a class="button button--primary" href="{{ route('dishes.index', ['owner_id'=>$profile->id]) }}">{{ __('Кухня партнёра') }}</a>
<a class="button button--quiet" href="{{ route('home') }}">{{ __('На главную') }}</a>
</x-card>
@endsection
@section('navigation')<x-bottom-navigation :couple="$couple" />@endsection
