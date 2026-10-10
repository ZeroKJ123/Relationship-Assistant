@extends('layouts.app')
@section('title', __('Новая просьба'))
@section('content')
<x-card class="auth-card">
<h1 class="form-title">{{ __('Новая просьба') }}</h1>
<p>{{ __('Срок указывается по часовому поясу пары: :zone', ['zone'=>$couple->timezone]) }}</p>
@if($errors->any())<div role="alert">
@foreach($errors->all() as $error)<p class="field-error">{{ $error }}</p>
@endforeach</div>
@endif
<form method="get" action="{{ route('requests.create',$couple) }}" class="form-stack section-space"><div class="field"><label for="template_choice">{{ __('Шаблон просьбы') }}</label><select id="template_choice" name="request_template_id"><option value="">{{ __('Без шаблона') }}</option>@foreach($templates as $template)<option value="{{ $template->id }}" @selected((string)old('request_template_id',$preset?->id) === (string)$template->id)>{{ __($template->category->name) }} · {{ __($template->name) }}</option>@endforeach</select></div><x-button type="submit" variant="quiet">{{ __('Применить шаблон') }}</x-button></form><p>{{ __('Шаблон заполняет вид и описание. Затем уточните детали и обязательный срок.') }}</p>
<form method="post" action="{{ route('requests.store',$couple) }}" class="form-stack" data-submit-lock data-request-editor>
@csrf
<input type="hidden" name="operation_key" value="{{ old('operation_key',$operationKey) }}">
@if($dish)<input type="hidden" name="dish_id" value="{{ $dish->id }}"><input type="hidden" name="dish_version" value="{{ $dish->version }}"><input type="hidden" name="type" value="food"><input type="hidden" name="heart_price" value="{{ $dish->heart_price }}"><h2>{{ $dish->title }}</h2><p>{{ __('Цена: :amount сердечек. Ориентир продуктов: :estimate :currency. Порций: :portions.',['amount'=>$dish->heart_price,'estimate'=>$dish->estimate,'currency'=>$dish->currency->value,'portions'=>$dish->portions]) }}</p>@else
<div class="confirmation-actions"><a class="button button--quiet" href="{{ route('guests.create',$couple) }}">{{ __('Позвать друзей') }}</a><a class="button button--quiet" href="{{ route('dates.create',$couple) }}">{{ __('Свидание или событие') }}</a><a class="button button--quiet" href="{{ route('money.create',$couple) }}">{{ __('Попросить деньги') }}</a></div>
<div class="field">
<label for="type">{{ __('О чём просьба') }}</label>
<select id="type" name="type">
@foreach($types as $type)<option value="{{ $type->value }}" @selected(old('type',$preset?->type->value) === $type->value)>{{ __($type->label()) }}</option>
@endforeach</select>
</div>
@endif
<div class="field">
<label for="description">{{ __('Что нужно сделать') }}</label>
<textarea id="description" name="description" required maxlength="2000" rows="4">{{ old('description',$dish?->title ?? $preset?->name) }}</textarea>
</div>
<input type="hidden" name="request_template_id" value="{{ old('request_template_id',$preset?->id) }}">
<div class="field"><label for="expense_category_id">{{ __('Категория расходов — если нужна') }}</label><select id="expense_category_id" name="expense_category_id"><option value="">{{ __('Без категории расходов') }}</option>@foreach($expenses as $category)<option value="{{ $category->id }}" @selected((string)old('expense_category_id') === (string)$category->id)>{{ __($category->name) }}</option>@endforeach</select></div>
@unless($dish)<x-field name="heart_price" :label="__('Цена услуги в сердечках')" type="number" :value="old('heart_price',0)" min="0" max="1000" step="1" required /><p>{{ __('Для услуги можно согласовать цену; 0 — бесплатно. Резерв появится после согласия, списание — после проверки. Стоимость блюд выбирается в кухне. Запрос с точной денежной суммой создаётся в разделе «Попросить деньги».') }}</p>
@endunless
<x-field name="due_at" :label="__('Выполнить до')" type="datetime-local" :value="old('due_at','')" required />
<label class="confirmation-check"><input type="checkbox" name="is_very_wanted" value="1" @checked(old('is_very_wanted'))>{{ __('Очень хочу') }}</label>
<p>{{ __('Для «Очень хочу» нужны согласованный недельный лимит и баланс без минуса. Обычный отказ партнёра уменьшает его сердечки; отсутствие денег на продукты — без штрафа.') }}</p>
<a href="{{ route('agreements.show',$couple) }}">{{ __('Наш недельный лимит') }}</a>
<x-button type="submit">{{ __('Отправить просьбу') }}</x-button>
</form>
</x-card>
<a class="button button--quiet section-space" href="{{ route('requests.index',$couple) }}">{{ __('Все просьбы') }}</a>
@endsection
