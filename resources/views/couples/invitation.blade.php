@extends('layouts.app')
@section('title', __('Приглашение · Любимость'))
@section('content')
<x-card class="auth-card">
    <p class="eyebrow">{{ __('Для вас двоих') }}</p>
    <h1 class="form-title">{{ __('Приглашение в пару') }}</h1>
    @if($available)
        <p>{{ __(':name приглашает вас создать общее пространство.', ['name' => $invitation->inviter?->name]) }}</p>
        @if($isOwn)
            <p>{{ __('Это ваше приглашение. Отправьте ссылку партнёру.') }}</p>
        @else
            <form method="post" action="{{ route('invitations.accept', $token) }}" class="form-stack" data-submit-lock>
                @csrf
                <label class="confirmation-check"><input type="checkbox" name="confirmation" value="1" required> {{ __('Хочу принять приглашение и создать пару с этим человеком') }}</label>
                @error('confirmation')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                @error('invitation')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                <x-button type="submit">{{ __('Принять приглашение') }}</x-button>
            </form>
        @endif
    @else
        <p>{{ __('Приглашение уже использовано, отменено или срок его действия истёк. Попросите партнёра выпустить новую ссылку.') }}</p>
    @endif
    <p class="form-footer"></p>
</x-card>
@endsection
