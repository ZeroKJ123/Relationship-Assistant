@props(['duty', 'couple', 'partnerRequest'])
<section class="form-stack">
@if($duty->kind !== \App\Enums\DutyKind::Service)<h2>{{ __($duty->kind->label()) }}</h2><p class="request-description">{{ $duty->description }}</p><x-status>{{ __($duty->status->label()) }}</x-status>@endif
@can('report', $duty)
@if(in_array($duty->status,[\App\Enums\DutyStatus::Pending,\App\Enums\DutyStatus::Overdue],true))
<x-duty-answer :action="route('duties.report',[$couple,$duty])" :duty="$duty" :positive-label="__('Выполнено')" :return-request="true" />
@endif
@endcan
@can('verify', $duty)
@if($duty->status === \App\Enums\DutyStatus::Reported)
<x-duty-answer :action="route('duties.verify',[$couple,$duty])" :duty="$duty" :is-review="true" :positive-label="__('Подтвердить выполнение')" :return-request="true" />
@endif
@endcan
</section>
