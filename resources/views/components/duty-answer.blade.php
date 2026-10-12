@props(['action','duty','isReview'=>false,'positiveLabel','returnRequest'=>false])
<form method="post" action="{{ $action }}" class="form-stack" data-submit-lock data-duty-confirmation data-question-yes="{{ $isReview ? __('Результат проверен и соответствует договорённости?') : __('Всё выполнено согласно договорённости?') }}" data-question-no="{{ $isReview ? __('Оспорить отметку? Исполнитель потеряет :amount сердечек.', ['amount'=>$duty->false_report_penalty]) : __('Сохранить ответ «Пока не сделано»? Это не продлевает срок.') }}">
    @if($returnRequest)<input type="hidden" name="return_request" value="1">@endif
    @csrf<input type="hidden" name="expected_version" value="{{ $duty->version }}">
    <div data-duty-confirmation-content>
        <div class="form-stack">
            <h3 id="duty-confirmation-title">{{ $isReview ? __('Подтвердить результат проверки') : __('Подтвердить ответ о выполнении') }}</h3>
            <p class="request-description">{{ $duty->description }}</p><p data-duty-question>{{ __('Убедитесь, что выбрали нужный ответ.') }}</p>
            <p>{{ $isReview ? __('Это отдельная проверка результата. Подтверждение может завершить дело и начислить награду.') : __('Партнёр проверит результат отдельно. Отметка сама по себе не начисляет сердечки.') }}</p>
            <label class="confirmation-check"><input type="checkbox" name="confirmation" value="1" required>{{ $isReview ? __('Подтверждаю результат проверки') : __('Подтверждаю выбранный ответ о выполнении') }}</label>
            <div class="confirmation-actions" data-duty-dialog-actions hidden><x-button variant="quiet" data-duty-back>{{ __('Назад') }}</x-button><x-button data-duty-send>{{ __('Да, отправить ответ') }}</x-button></div>
        </div>
    </div>
    <div class="confirmation-actions"><x-button type="submit" name="answer" value="1">{{ $positiveLabel }}</x-button><x-button type="submit" name="answer" value="0" variant="quiet">{{ $isReview ? __('Оспорить отметку') : __('Пока не сделано') }}</x-button></div>
</form>
