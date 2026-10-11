@props(['couple','partnerRequest','methods','wishes','currencies','conditionTypes'])
<form method="post" action="{{ route('requests.responses.store',[$couple,$partnerRequest]) }}" class="form-stack" data-submit-lock data-condition-form>
@csrf<input type="hidden" name="expected_version" value="{{ $partnerRequest->version }}">
<input type="hidden" name="response" value="conditional">
@if($errors->any() && old('response') === 'conditional')<x-form-feedback />@endif
<div class="condition-choices" hidden data-condition-choices role="group" aria-label="{{ __('Вид условия') }}">
@foreach($conditionTypes as $type)<button class="choice-chip" type="button" data-condition-choice="{{ $type->value }}" aria-pressed="false">{{ __($type->label()) }}</button>@endforeach
@if($methods->contains('kind','sushi'))<button class="choice-chip" type="button" data-condition-choice="sushi" aria-pressed="false">{{ __('Суши') }}</button>@endif
</div>
<div class="field" data-condition-fields="sushi"><label for="sushi_size">{{ __('Размер суши — только для этого способа') }}</label><select name="sushi_size" id="sushi_size"><option value="">{{ __('Не выбрано') }}</option>@foreach(['small'=>__('Маленький'),'medium'=>__('Средний'),'large'=>__('Большой')] as $size=>$label)<option value="{{ $size }}" @selected(old('sushi_size') === $size)>{{ $label }}</option>@endforeach</select></div>
<div class="field" data-native-condition-type><label for="condition_type">{{ __('Вид условия') }}</label>
<select name="condition_type" id="condition_type">
@foreach($conditionTypes as $type)<option value="{{ $type->value }}" @selected(old('condition_type','custom') === $type->value)>{{ __($type->label()) }}</option>
@endforeach</select>
</div>
<details @if(old('settlement_method_id') || $errors->hasAny(['settlement_method_id','sushi_size'])) open @endif><summary>{{ __('Способ из каталога — по желанию') }}</summary><div class="section-space"><div class="field">
<label for="settlement_method_id">{{ __('Способ из каталога — если нужен') }}</label><select name="settlement_method_id" id="settlement_method_id"><option value="">{{ __('Без способа из каталога') }}</option>@foreach($methods as $method)<option value="{{ $method->id }}" data-kind="{{ $method->kind }}" @selected((string)old('settlement_method_id') === (string)$method->id)>{{ $method->name }}</option>@endforeach</select></div>
</div></details>

<div class="field" data-condition-fields="gift"><label for="wishlist_choice">{{ __('Для подарка — ваше желание') }}</label><select id="wishlist_choice" name="wishlist_choice"><option value="">{{ __('Не выбрано') }}</option>@foreach($wishes as $wish)<option value="{{ $wish->id }}:{{ $wish->version }}" @selected(old('wishlist_choice') === sprintf('%d:%d',$wish->id,$wish->version))>{{ $wish->title }} · {{ $wish->price }} {{ $wish->currency->value }}</option>@endforeach</select></div>
<div data-condition-fields="gift">{{ $wishes->links() }}</div>
<div class="form-stack" data-condition-fields="money"><x-field name="amount" :label="__('Для денежного условия — точная сумма')" :value="old('amount','')" inputmode="decimal" /><div class="field"><label for="currency">{{ __('Валюта денежного условия') }}</label><select id="currency" name="currency">@foreach($currencies as $currency)<option value="{{ $currency->value }}" @selected(old('currency',$couple->currency->value) === $currency->value)>{{ $currency->value }}</option>@endforeach</select></div></div><p data-condition-fallback>{{ __('Заполняйте сумму только для денег, а желание только для подарка. Для других условий оставьте оба поля пустыми.') }}</p>
<div class="field">
<label for="description">{{ __('Условие') }}</label>
<textarea id="description" name="description" rows="3" maxlength="2000" required>{{ old('description') }}</textarea>
</div>
<x-field name="due_at" :label="__('Выполнить условие до')" type="datetime-local" :value="old('due_at',$partnerRequest->due_at->setTimezone($couple->timezone)->format('Y-m-d\TH:i'))" required />
<p>{{ __('Автор просьбы примет условие отдельно.') }}</p>
<label class="confirmation-check">
<input type="checkbox" name="confirmation" value="1" required>{{ __('Подтверждаю предложение условия') }}</label>
<x-button type="submit">{{ __('Отправить условие') }}</x-button>
</form>