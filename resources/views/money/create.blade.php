@extends('layouts.app')
@section('title', __('Попросить деньги'))
@section('content')
<x-card class="auth-card"><h1>{{ __('Попросить деньги') }}</h1><p>{{ __('Это договорённость между вами. Перевод вы делаете самостоятельно; сначала плательщик отмечает оплату, затем получатель подтверждает поступление.') }}</p>
<a href="{{ route('profile.show') }}">{{ __('Проверить свои реквизиты') }}</a><x-form-feedback />
<form method="post" action="{{ route('money.store',$couple) }}" class="form-stack" data-submit-lock>@csrf
<input type="hidden" name="request_template_id" value="{{ old('request_template_id',$preset?->id) }}"><input type="hidden" name="operation_key" value="{{ old('operation_key',$operationKey) }}">
<x-field name="amount" :label="__('Сумма')" :value="old('amount','')" inputmode="decimal" required />
<p>{{ __('Используйте точку, не более двух знаков после неё. Сумма не округляется.') }}</p>
<div class="field"><label for="currency">{{ __('Валюта') }}</label><select id="currency" name="currency">@foreach($currencies as $currency)<option value="{{ $currency->value }}" @selected(old('currency',$couple->currency->value) === $currency->value)>{{ $currency->value }}</option>@endforeach</select></div>
<div class="field"><label for="purpose">{{ __('Назначение — если нужно') }}</label><textarea id="purpose" name="purpose" rows="3" maxlength="2000">{{ old('purpose',$preset?->name) }}</textarea></div>
<div class="field"><label for="expense_category_id">{{ __('Категория — если нужна') }}</label><select id="expense_category_id" name="expense_category_id"><option value="">{{ __('Без категории') }}</option>@foreach($expenses as $category)<option value="{{ $category->id }}" @selected((string)old('expense_category_id') === (string)$category->id)>{{ __($category->name) }}</option>@endforeach</select></div>
<x-field name="due_at" :label="__('Оплатить до')" type="datetime-local" :value="old('due_at','')" required /><p>{{ __('Часовой пояс: :zone',['zone'=>$couple->timezone]) }}</p>
<label class="confirmation-check"><input type="checkbox" name="confirmation" value="1" required>{{ __('Подтверждаю сумму, валюту и срок') }}</label><x-button type="submit">{{ __('Отправить запрос денег') }}</x-button>
</form></x-card><a class="button button--quiet section-space" href="{{ route('requests.index',$couple) }}">{{ __('Все просьбы') }}</a>
@endsection
