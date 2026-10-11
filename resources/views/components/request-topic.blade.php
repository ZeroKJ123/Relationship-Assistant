@props(['couple', 'selected' => 'service'])
<div class="field"><label for="request-topic">{{ __('О чём просьба') }}</label>
<select id="request-topic" name="{{ in_array($selected, ['money','guests','event'], true) ? 'request_topic' : 'type' }}" data-request-topic>
@foreach(\App\Enums\RequestType::cases() as $type)
<option value="{{ $type->value }}" data-url="{{ match($type) { \App\Enums\RequestType::Money => route('money.create',$couple), \App\Enums\RequestType::Guests => route('guests.create',$couple), \App\Enums\RequestType::Event => route('dates.create',$couple), default => route('requests.create',[$couple,'type'=>$type->value]) } }}" @selected(old('type',$selected) === $type->value)>{{ __($type->label()) }}</option>
@endforeach
</select></div>
<noscript><p>{{ __('Виды просьб') }}:</p><div class="confirmation-actions">@foreach(['money.create'=>__('Деньги'),'guests.create'=>__('Гости'),'dates.create'=>__('Свидание'),'requests.create'=>__('Другие виды')] as $route=>$label)<a href="{{ route($route,$couple) }}">{{ $label }}</a>@endforeach</div></noscript>
