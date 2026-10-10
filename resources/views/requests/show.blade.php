@extends('layouts.app')
@section('title', __('Просьба партнёру'))
@section('content')
<x-card class="start-card form-stack">
<p>{{ __('Цена услуги: :amount сердечек', ['amount'=>$partnerRequest->heart_price]) }}</p>
<x-status>{{ __($partnerRequest->status->label()) }}</x-status>
<h1>{{ __($partnerRequest->type->label()) }}</h1>
<p>{{ $partnerRequest->author->name }} → {{ $partnerRequest->recipient->name }}</p>
@if($partnerRequest->category_name)<p>{{ __('Категория: :name', ['name'=>$partnerRequest->category_name]) }}</p>@endif
@if($partnerRequest->template_name)<p>{{ __('Шаблон: :name', ['name'=>$partnerRequest->template_name]) }}</p>@endif
<p class="request-description">{{ $partnerRequest->description }}</p>
<x-social-invitation :request="$partnerRequest" :timezone="$couple->timezone" />
@if($partnerRequest->dish_snapshot)<x-product-photo :src="!empty($partnerRequest->dish_snapshot['photo_path']) ? route('photos.requests',$partnerRequest) : null" :alt="$partnerRequest->dish_snapshot['title'] ?? ''" symbol="♨" /><p>{{ $partnerRequest->dish_snapshot['title'] }} · {{ $partnerRequest->dish_kind->label() }}</p><p>{{ $partnerRequest->dish_snapshot['ingredients'] }}</p><p>{{ __('Продукты ≈ :amount :currency · порций: :portions',['amount'=>$partnerRequest->dish_snapshot['estimate'],'currency'=>$partnerRequest->dish_snapshot['currency'],'portions'=>$partnerRequest->dish_snapshot['portions']]) }}</p>@endif
@if($partnerRequest->moneyRequest)<p class="price-chip">{{ $partnerRequest->moneyRequest->amount }} {{ $partnerRequest->moneyRequest->currency->value }}</p><p>{{ __('Оплата отмечается плательщиком; поступление отдельно подтверждает получатель. Денежные переводы не дают награду.') }}</p>@if($partnerRequest->status === \App\Enums\RequestStatus::Accepted)<h2>{{ __('Реквизиты получателя') }}</h2><p class="request-description">{{ $partnerRequest->moneyRequest->payment_details_snapshot }}</p>@endif @endif
<p>{{ __('Выполнить до :date', ['date'=>$partnerRequest->due_at->setTimezone($couple->timezone)->format('d.m.Y H:i')]) }}</p>
@if(session('status'))<p role="status">{{ session('status') }}</p>
@endif
@if($errors->any())<div role="alert">
@foreach($errors->all() as $error)<p class="field-error">{{ $error }}</p>
@endforeach</div>
@endif
@if($partnerRequest->decline_reason)<p>{{ __($partnerRequest->decline_reason->label()) }}</p>
@if($partnerRequest->decline_penalty === 0)<p>{{ __('Штраф за отказ: 0 сердечек.') }}</p>
@endif
@endif
@if($partnerRequest->status === \App\Enums\RequestStatus::Accepted)<p>{{ __('Договорились. Выполнение просьбы и условия подтверждается отдельно.') }}</p>
@endif
</x-card>
@if($currentCondition)<x-card class="section-space form-stack">
<h2>{{ __('Встречное условие · вариант :version', ['version'=>$currentCondition->version]) }}</h2>
<p>{{ __($currentCondition->type->label()) }}</p>
<p class="request-description">{{ $currentCondition->description }}</p>
@if($currentCondition->gift_snapshot)<x-product-photo :src="!empty($currentCondition->gift_snapshot['photo_path']) ? route('photos.conditions',$currentCondition) : null" :alt="$currentCondition->gift_snapshot['title'] ?? ''" /><p>{{ $currentCondition->gift_snapshot['title'] }}</p><p class="price-chip">{{ $currentCondition->gift_snapshot['price'] }} {{ $currentCondition->gift_snapshot['currency'] }}</p><p>{{ __('Получатель подтвердит подарок отдельно. После получения желание уйдёт в архив.') }}</p>@endif
@if($currentCondition->amount)<p class="price-chip">{{ $currentCondition->amount }} {{ $currentCondition->currency->value }}</p>@if($currentCondition->accepted_at && $partnerRequest->status === \App\Enums\RequestStatus::Accepted)<h3>{{ __('Реквизиты получателя') }}</h3><p class="request-description">{{ $currentCondition->payment_details_snapshot }}</p>@endif @endif
<p>{{ __('Срок условия: :date', ['date'=>$currentCondition->due_at->setTimezone($couple->timezone)->format('d.m.Y H:i')]) }}</p>
@if($currentCondition->accepted_at)<p>{{ __('Условие принято автором просьбы.') }}</p>
@endif
@if($partnerRequest->status === \App\Enums\RequestStatus::Conditional)
@can('acceptCondition',$partnerRequest)<form method="post" action="{{ route('requests.condition.store',[$couple,$partnerRequest]) }}" class="form-stack" data-submit-lock>
@csrf<input type="hidden" name="expected_version" value="{{ $partnerRequest->version }}">
<label class="confirmation-check">
<input type="checkbox" name="confirmation" value="1" required>{{ __('Подтверждаю решение по этому варианту условия') }}</label>
<div class="confirmation-actions">
<x-button type="submit" name="decision" value="accept">{{ __('Принимаю условие') }}</x-button>
<x-button type="submit" name="decision" value="decline" variant="quiet">{{ __('Отклонить просьбу с условием') }}</x-button>
</div>
</form>
@endcan
@endif</x-card>
@endif
@can('respond',$partnerRequest)
@if($partnerRequest->status === \App\Enums\RequestStatus::Pending)
<x-card class="section-space">
<h2>{{ __('Ваш ответ') }}</h2>
<form method="post" action="{{ route('requests.responses.store',[$couple,$partnerRequest]) }}" class="form-stack" data-submit-lock>
@csrf<input type="hidden" name="expected_version" value="{{ $partnerRequest->version }}">
<div class="field">
<label for="reason">{{ __('Причина при отказе') }}</label>
<select name="reason" id="reason">
<option value="ordinary">{{ __('Не могу согласиться') }}</option>
@if($partnerRequest->type === \App\Enums\RequestType::Food)<option value="no_food_money">{{ __('Нет денег на продукты — без штрафа') }}</option>
@endif</select>
</div>
<label class="confirmation-check">
<input type="checkbox" name="confirmation" value="1" required>{{ __('Подтверждаю выбранный ответ') }}</label>
<div class="confirmation-actions">
<x-button type="submit" name="response" value="agree">{{ __('Согласиться') }}</x-button>
<x-button type="submit" name="response" value="decline" variant="quiet">{{ __('Отказать') }}</x-button>
</div>
</form>
</x-card>
@endif
@if(!$partnerRequest->moneyRequest && in_array($partnerRequest->status,[\App\Enums\RequestStatus::Pending,\App\Enums\RequestStatus::Conditional],true))
<x-card class="section-space">
<h2>{{ __('Предложить встречное условие') }}</h2>
<form method="post" action="{{ route('requests.responses.store',[$couple,$partnerRequest]) }}" class="form-stack" data-submit-lock>
@csrf<input type="hidden" name="expected_version" value="{{ $partnerRequest->version }}">
<input type="hidden" name="response" value="conditional">
<div class="field">
<label for="settlement_method_id">{{ __('Способ из каталога — если нужен') }}</label><select name="settlement_method_id" id="settlement_method_id"><option value="">{{ __('Без способа из каталога') }}</option>@foreach($methods as $method)<option value="{{ $method->id }}" @selected((string)old('settlement_method_id') === (string)$method->id)>{{ $method->name }}</option>@endforeach</select></div>
<div class="field"><label for="sushi_size">{{ __('Размер суши — только для этого способа') }}</label><select name="sushi_size" id="sushi_size"><option value="">{{ __('Не выбрано') }}</option>@foreach(['small'=>__('Маленький'),'medium'=>__('Средний'),'large'=>__('Большой')] as $size=>$label)<option value="{{ $size }}" @selected(old('sushi_size') === $size)>{{ $label }}</option>@endforeach</select></div>
<div class="field"><label for="condition_type">{{ __('Вид условия') }}</label>
<select name="condition_type" id="condition_type">
@foreach($conditionTypes as $type)<option value="{{ $type->value }}" @selected(old('condition_type') === $type->value)>{{ __($type->label()) }}</option>
@endforeach</select>
</div>
<div class="field"><label for="wishlist_choice">{{ __('Для подарка — ваше желание') }}</label><select id="wishlist_choice" name="wishlist_choice"><option value="">{{ __('Не выбрано') }}</option>@foreach($wishes as $wish)<option value="{{ $wish->id }}:{{ $wish->version }}" @selected(old('wishlist_choice') === sprintf('%d:%d',$wish->id,$wish->version))>{{ $wish->title }} · {{ $wish->price }} {{ $wish->currency->value }}</option>@endforeach</select></div>
{{ $wishes->links() }}
<x-field name="amount" :label="__('Для денежного условия — точная сумма')" :value="old('amount','')" inputmode="decimal" /><div class="field"><label for="currency">{{ __('Валюта денежного условия') }}</label><select id="currency" name="currency">@foreach($currencies as $currency)<option value="{{ $currency->value }}" @selected(old('currency',$couple->currency->value) === $currency->value)>{{ $currency->value }}</option>@endforeach</select></div><p>{{ __('Заполняйте сумму только для денег, а желание только для подарка. Для других условий оставьте оба поля пустыми.') }}</p>
<div class="field">
<label for="description">{{ __('Условие') }}</label>
<textarea id="description" name="description" rows="4" maxlength="2000" required>{{ old('description') }}</textarea>
</div>
<x-field name="due_at" :label="__('Выполнить условие до')" type="datetime-local" :value="old('due_at','')" required />
<p>{{ __('Часовой пояс: :zone. Автор просьбы примет этот вариант отдельно.', ['zone'=>$couple->timezone]) }}</p>
<label class="confirmation-check">
<input type="checkbox" name="confirmation" value="1" required>{{ __('Подтверждаю предложение условия') }}</label>
<x-button type="submit">{{ __('Отправить условие') }}</x-button>
</form>
</x-card>
@endif
@endcan
@if($conditions->total() > 1)<x-card class="section-space">
<h2>{{ __('История условий') }}</h2>
@foreach($conditions as $condition)<p>{{ __('Вариант :version', ['version'=>$condition->version]) }}: {{ $condition->description }}</p>
@endforeach</x-card>
@endif
@if($partnerRequest->status === \App\Enums\RequestStatus::Accepted)
<a class="button button--primary section-space" href="{{ route('duties.index',$couple) }}">{{ __('Перейти к выполнению') }}</a>
@endif
{{ $conditions->links() }}<a class="button button--quiet section-space" href="{{ route('requests.index',$couple) }}">{{ __('Все просьбы') }}</a>
@if($couple->status === \App\Enums\CoupleStatus::Active && in_array($partnerRequest->status,[\App\Enums\RequestStatus::Pending,\App\Enums\RequestStatus::Conditional,\App\Enums\RequestStatus::Accepted],true))
@if($partnerRequest->status === \App\Enums\RequestStatus::Accepted || auth()->id() === $partnerRequest->author_id)
<x-card class="section-space"><h2>{{ __('Отмена просьбы') }}</h2><p>{{ __('До согласия автор может отменить просьбу. После принятия нужны оба согласия; завершённое дело не отменяется. Резерв освобождается без возврата уже применённых наград и штрафов.') }}</p>
@if($partnerRequest->cancellation_proposed_by_id)<p>{{ __('Один участник предложил отмену. Второй может подтвердить её; до этого просьба действует.') }}</p>@endif
@if($partnerRequest->cancellation_proposed_by_id !== auth()->id())
<form method="post" action="{{ route('requests.cancel',[$couple,$partnerRequest]) }}" class="form-stack" data-submit-lock>@csrf<input type="hidden" name="expected_version" value="{{ $partnerRequest->version }}"><label class="confirmation-check"><input type="checkbox" name="confirmation" value="1" required>{{ __('Подтверждаю решение об отмене') }}</label><x-button type="submit" variant="quiet">{{ $partnerRequest->status !== \App\Enums\RequestStatus::Accepted ? __('Отменить просьбу') : ($partnerRequest->cancellation_proposed_by_id === null ? __('Предложить отмену') : __('Согласиться с отменой')) }}</x-button></form>
@else<p>{{ __('Ожидаем согласие партнёра на отмену.') }}</p>@endif
</x-card>
@endif
@endif
@endsection
