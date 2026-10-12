@props(['couple', 'partnerRequest', 'returnDuty' => null])
@if(!$partnerRequest->duties->contains('status', \App\Enums\DutyStatus::Confirmed) && $partnerRequest->rating === null && $couple->status === \App\Enums\CoupleStatus::Active && in_array($partnerRequest->status,[\App\Enums\RequestStatus::Pending,\App\Enums\RequestStatus::Conditional,\App\Enums\RequestStatus::Accepted],true))
@if($partnerRequest->status === \App\Enums\RequestStatus::Accepted || auth()->id() === $partnerRequest->author_id)
<details class="settings-section section-space" @if($partnerRequest->cancellation_proposed_by_id) open @endif><summary><span class="row__body"><span class="row__title">{{ __('Отменить просьбу') }}</span><span class="row__meta">{{ $partnerRequest->cancellation_proposed_by_id ? __('Отмена предложена и ждёт второго согласия') : __('После принятия нужны оба согласия') }}</span></span></summary><div class="form-stack"><p>{{ __('До согласия автор может отменить просьбу. После принятия нужны оба согласия; завершённое дело не отменяется. Резерв освобождается без возврата уже применённых наград и штрафов.') }}</p>
@if($partnerRequest->cancellation_proposed_by_id)<p>{{ __('Один участник предложил отмену. Второй может подтвердить её; до этого просьба действует.') }}</p>@endif
@if($partnerRequest->cancellation_proposed_by_id !== auth()->id())
<form method="post" action="{{ route('requests.cancel',[$couple,$partnerRequest]) }}" class="form-stack" data-submit-lock>@csrf<input type="hidden" name="expected_version" value="{{ $partnerRequest->version }}">@if($returnDuty)<input type="hidden" name="return_duty" value="{{ $returnDuty->id }}">@endif<label class="confirmation-check"><input type="checkbox" name="confirmation" value="1" required>{{ __('Подтверждаю решение об отмене') }}</label><x-button type="submit" variant="quiet">{{ $partnerRequest->status !== \App\Enums\RequestStatus::Accepted ? __('Отменить просьбу') : ($partnerRequest->cancellation_proposed_by_id === null ? __('Предложить отмену') : __('Согласиться с отменой')) }}</x-button></form>
@else<p>{{ __('Ожидаем согласие партнёра на отмену.') }}</p>@endif
</div></details>
@endif
@endif
