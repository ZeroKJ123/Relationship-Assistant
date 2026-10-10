@extends('layouts.app')
@section('title', __('Попросить деньги'))
@section('content')
<x-card class="auth-card">
    <x-status>{{ __('Денежная договорённость') }}</x-status>
    <h1 class="form-title">{{ __('Попросить деньги') }}</h1>
    <p>{{ __('Перевод вы делаете самостоятельно. Плательщик отмечает отправку, затем получатель подтверждает поступление. Приложение не списывает деньги со счёта.') }}</p>
    <p class="form-footer"><a href="{{ route('profile.show') }}">{{ __('Проверить свои реквизиты') }}</a></p>
    <x-form-feedback />
    <form method="post" action="{{ route('money.store', $couple) }}" class="form-stack" data-submit-lock>
        @csrf
        <input type="hidden" name="request_template_id" value="{{ old('request_template_id', $preset?->id) }}">
        <input type="hidden" name="operation_key" value="{{ old('operation_key', $operationKey) }}">
        <fieldset class="settings-group">
            <legend>{{ __('Сумма и назначение') }}</legend>
            <x-field name="amount" :label="__('Сумма')" :value="old('amount', '')" :error="$errors->first('amount')" inputmode="decimal" maxlength="16" aria-describedby="amount-hint" required />
            <p class="field-hint" id="amount-hint">{{ __('Используйте точку, не более двух знаков после неё. Сумма не округляется и не пересчитывается по курсу.') }}</p>
            <x-currency-field :currencies="$currencies" :value="old('currency', $couple->currency->value)" :error="$errors->first('currency')" />
            <x-textarea-field name="purpose" :label="__('Назначение — если нужно')" :value="old('purpose', $preset?->name)" :error="$errors->first('purpose')" maxlength="2000" />
            <div class="field">
                <label for="expense_category_id">{{ __('Категория — если нужна') }}</label>
                <select id="expense_category_id" name="expense_category_id" @if($errors->has('expense_category_id')) aria-invalid="true" aria-describedby="expense-category-error" @endif>
                    <option value="">{{ __('Без категории') }}</option>
                    @foreach($expenses as $category)<option value="{{ $category->id }}" @selected((string)old('expense_category_id') === (string)$category->id)>{{ __($category->name) }}</option>@endforeach
                </select>
                @error('expense_category_id')<p class="field-error" id="expense-category-error">{{ $message }}</p>@enderror
            </div>
        </fieldset>
        <fieldset class="settings-group">
            <legend>{{ __('Срок') }}</legend>
            <x-field name="due_at" :label="__('Оплатить до')" type="datetime-local" :value="old('due_at', '')" :error="$errors->first('due_at')" aria-describedby="due-at-hint" required />
            <p class="field-hint" id="due-at-hint">{{ __('Часовой пояс: :zone', ['zone' => $couple->timezone]) }}</p>
        </fieldset>
        <label class="confirmation-check"><input type="checkbox" name="confirmation" value="1" required>{{ __('Подтверждаю сумму, валюту и срок') }}</label>
        <x-button type="submit">{{ __('Отправить запрос денег') }}</x-button>
    </form>
</x-card>
<a class="button button--quiet section-space" href="{{ route('requests.index', $couple) }}">{{ __('Все просьбы') }}</a>
@endsection
@section('navigation')<x-bottom-navigation :couple="$couple" />@endsection
