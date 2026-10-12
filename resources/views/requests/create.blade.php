@extends('layouts.app')
@section('title', __('Новая просьба'))
@section('back', $reward ? route('rewards.index',['owner_id'=>$reward->user_id]) : route('requests.index',$couple))
@section('back-label', __('Просьбы'))
@section('content')
<div class="auth-card">
    <h1 class="form-title">{{ __('Новая просьба') }}</h1>
    <x-form-feedback />
    <form method="post" action="{{ route('requests.store',$couple) }}" class="form-stack section-space" data-submit-lock data-request-editor>
        @csrf
        <input type="hidden" name="operation_key" value="{{ old('operation_key',$operationKey) }}">
        @if($dish)
            <input type="hidden" name="dish_id" value="{{ $dish->id }}"><input type="hidden" name="dish_version" value="{{ $dish->version }}"><input type="hidden" name="type" value="food"><input type="hidden" name="heart_price" value="{{ $dish->heart_price }}">
            <h2>{{ $dish->title }}</h2><p>{{ __('Цена: :amount сердечек · продуктов ≈ :estimate :currency · порций: :portions',['amount'=>$dish->heart_price,'estimate'=>$dish->estimate,'currency'=>$dish->currency->value,'portions'=>$dish->portions]) }}</p>
        @elseif($reward)
            <input type="hidden" name="reward_id" value="{{ $reward->id }}"><input type="hidden" name="type" value="service"><input type="hidden" name="heart_price" value="{{ $reward->heart_price }}">
            <p class="hint"><x-icon name="gift" /><span><strong>{{ $reward->title }}</strong> · {{ __('Цена: :amount сердечек', ['amount'=>$reward->heart_price]) }}<br>{{ __('Сердечки резервируются после согласия партнёра и списываются после проверки выполнения.') }}</span></p>
        @else
            <x-request-topic :couple="$couple" :selected="$selectedType" />
            <p data-request-rating-hint @if(old('type',$selectedType) !== 'rating') hidden @endif>{{ __('Опишите поступок. Партнёр поставит оценку 1–5; это бесплатно, без штрафов и наград.') }}</p>
        @endif
        <div class="field"><label for="description">{{ __('Ваша просьба') }}</label><textarea id="description" name="description" required maxlength="2000" rows="3">{{ old('description',$dish?->title ?? $reward?->title) }}</textarea></div>
        <x-field name="due_at" :label="__('Ответить и выполнить до')" type="datetime-local" :value="old('due_at',$defaultDueAt)" required />
        <div class="quick-dates" data-due-shortcuts="due_at" hidden>@foreach($dueShortcuts as $label => $value)<button type="button" data-due-value="{{ $value }}">{{ $label }}</button>@endforeach</div>

        <details class="settings-section" @if($errors->hasAny(['heart_price','expense_category_id','is_very_wanted','partner_request'])) open @endif>
            <summary><span class="row__body"><span class="row__title">{{ __('Дополнительно') }}</span><span class="row__meta">{{ __('Цена, «Очень хочу», категория расходов') }}</span></span></summary>
            <div class="form-stack">
                @unless($dish || $reward)
                    <div data-request-price @if(old('type',$selectedType) !== 'service') hidden @endif><x-field name="heart_price" :label="__('Цена услуги в сердечках')" type="number" :value="old('heart_price',0)" min="0" max="1000" step="1" /><p class="field-hint">{{ __('0 — бесплатно. Сердечки спишутся только после проверки выполнения.') }}</p></div>
                @endunless
                <div data-request-very-wanted @if(old('type',$selectedType) === 'rating') hidden @endif><label class="confirmation-check"><input type="checkbox" name="is_very_wanted" value="1" @checked(old('is_very_wanted'))>{{ __('Очень хочу') }}</label><p class="field-hint">{{ __('Обычный отказ уменьшает сердечки партнёра. Нужны согласованный недельный лимит и баланс без минуса.') }} <a href="{{ route('agreements.show',$couple) }}">{{ __('Наш лимит') }}</a></p></div>
                <div class="field"><label for="expense_category_id">{{ __('Категория расходов') }}</label><select id="expense_category_id" name="expense_category_id"><option value="">{{ __('Без категории') }}</option>@foreach($expenses as $category)<option value="{{ $category->id }}" @selected((string)old('expense_category_id') === (string)$category->id)>{{ __($category->name) }}</option>@endforeach</select></div>
            </div>
        </details>
        <div class="sticky-action"><x-button type="submit" class="button--wide">{{ __('Отправить просьбу') }}</x-button><small>{{ __('Партнёр получит уведомление и ответит отдельно.') }}</small></div>
    </form>
</div>
@endsection

@section('navigation')<x-bottom-navigation :couple="$couple" />@endsection
