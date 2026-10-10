@extends('layouts.app')
@section('title', __('Новая просьба'))
@section('content')
<x-card class="auth-card">
    <h1 class="form-title">{{ __('Новая просьба') }}</h1>
    <x-form-feedback />
    @unless($dish)
        <details class="section-space">
            <summary>{{ __('Выбрать шаблон') }}</summary>
            <form method="get" action="{{ route('requests.create',$couple) }}" class="form-stack section-space">
                <div class="field"><label for="template_choice">{{ __('Шаблон просьбы') }}</label><select id="template_choice" name="request_template_id"><option value="">{{ __('Без шаблона') }}</option>@foreach($templates as $template)<option value="{{ $template->id }}" @selected((string)old('request_template_id',$preset?->id) === (string)$template->id)>{{ __($template->category->name) }} · {{ __($template->name) }}</option>@endforeach</select></div>
                <x-button type="submit" variant="quiet">{{ __('Применить шаблон') }}</x-button>
            </form>
        </details>
    @endunless
    <form method="post" action="{{ route('requests.store',$couple) }}" class="form-stack section-space" data-submit-lock data-request-editor>
        @csrf
        <input type="hidden" name="operation_key" value="{{ old('operation_key',$operationKey) }}">
        <input type="hidden" name="request_template_id" value="{{ old('request_template_id',$preset?->id) }}">
        @if($dish)
            <input type="hidden" name="dish_id" value="{{ $dish->id }}"><input type="hidden" name="dish_version" value="{{ $dish->version }}"><input type="hidden" name="type" value="food"><input type="hidden" name="heart_price" value="{{ $dish->heart_price }}">
            <h2>{{ $dish->title }}</h2><p>{{ __('Цена: :amount сердечек · продуктов ≈ :estimate :currency · порций: :portions',['amount'=>$dish->heart_price,'estimate'=>$dish->estimate,'currency'=>$dish->currency->value,'portions'=>$dish->portions]) }}</p>
        @else
            <div class="field"><label for="type">{{ __('О чём просьба') }}</label><select id="type" name="type">@foreach($types as $type)<option value="{{ $type->value }}" @selected(old('type',$selectedType) === $type->value)>{{ __($type->label()) }}</option>@endforeach</select></div>
            <p data-request-rating-hint @if(old('type',$selectedType) !== 'rating') hidden @endif>{{ __('Опишите поступок. Партнёр поставит оценку 1–5; это бесплатно, без штрафов и наград.') }}</p>
        @endif
        <div class="field"><label for="description">{{ __('Ваша просьба') }}</label><textarea id="description" name="description" required maxlength="2000" rows="3">{{ old('description',$dish?->title ?? $preset?->name) }}</textarea></div>
        <x-field name="due_at" :label="__('Ответить и выполнить до')" type="datetime-local" :value="old('due_at',$defaultDueAt)" required />
        <p class="form-hint">{{ __('Время пары: :zone', ['zone'=>$couple->timezone]) }}</p>
        <details @if($errors->hasAny(['heart_price','expense_category_id','is_very_wanted','partner_request'])) open @endif>
            <summary>{{ __('Дополнительно') }}</summary>
            <div class="form-stack section-space">
                @unless($dish)
                    <div data-request-price @if(old('type',$selectedType) !== 'service') hidden @endif><x-field name="heart_price" :label="__('Цена услуги в сердечках')" type="number" :value="old('heart_price',0)" min="0" max="1000" step="1" /><p>{{ __('0 — бесплатно. Сердечки спишутся только после проверки выполнения.') }}</p></div>
                @endunless
                <div data-request-very-wanted @if(old('type',$selectedType) === 'rating') hidden @endif><label class="confirmation-check"><input type="checkbox" name="is_very_wanted" value="1" @checked(old('is_very_wanted'))>{{ __('Очень хочу') }}</label><p>{{ __('Обычный отказ уменьшает сердечки партнёра. Нужны согласованный недельный лимит и баланс без минуса.') }} <a href="{{ route('agreements.show',$couple) }}">{{ __('Наш лимит') }}</a></p></div>
                <div class="field"><label for="expense_category_id">{{ __('Категория расходов') }}</label><select id="expense_category_id" name="expense_category_id"><option value="">{{ __('Без категории') }}</option>@foreach($expenses as $category)<option value="{{ $category->id }}" @selected((string)old('expense_category_id') === (string)$category->id)>{{ __($category->name) }}</option>@endforeach</select></div>
            </div>
        </details>
        <x-button type="submit">{{ __('Отправить просьбу') }}</x-button>
    </form>
    @unless($dish)
        <details class="section-space"><summary>{{ __('Другие просьбы') }}</summary><div class="confirmation-actions section-space"><a class="button button--quiet" href="{{ route('guests.create',$couple) }}">{{ __('Позвать друзей') }}</a><a class="button button--quiet" href="{{ route('dates.create',$couple) }}">{{ __('Свидание или событие') }}</a><a class="button button--quiet" href="{{ route('money.create',$couple) }}">{{ __('Попросить деньги') }}</a></div></details>
    @endunless
</x-card>
<a class="button button--quiet section-space" href="{{ route('requests.index',$couple) }}">{{ __('Все просьбы') }}</a>
@endsection
