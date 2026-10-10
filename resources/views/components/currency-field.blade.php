@props(['currencies', 'value', 'error' => null, 'label' => __('Валюта')])
<div class="field">
    <label for="currency">{{ $label }}</label>
    <select id="currency" name="currency" @if($error) aria-invalid="true" aria-describedby="currency-error" @endif>
        @foreach($currencies as $currency)<option value="{{ $currency->value }}" @selected($value === $currency->value)>{{ $currency->value }}</option>@endforeach
    </select>
    @if($error)<p class="field-error" id="currency-error">{{ $error }}</p>@endif
</div>
