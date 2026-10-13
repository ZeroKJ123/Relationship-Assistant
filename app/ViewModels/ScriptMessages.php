<?php

declare(strict_types=1);

namespace App\ViewModels;

/** Texts that JavaScript shows on its own; the layout passes them in the reader's language. */
final class ScriptMessages
{
    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'request_failed' => __('Не удалось отправить запрос. Попробуйте ещё раз.'),
            'request_offline' => __('Не удалось отправить запрос. Проверьте соединение.'),
            'sticker_unavailable' => __('Стикер недоступен'),
            'preview_updated' => __('Предпросмотр обновлён. Сохраните изменения, чтобы применить их на сайте.'),
            'block_limit' => __('Можно добавить не более 20 блоков.'),
            'presence_failed' => __('Не удалось проверить присутствие. Проверьте соединение.'),
            'presence_waiting' => __('Ожидание партнёра для прокрутки рулетки. Оба должны согласиться и открыть эту страницу.'),
            'presence_interrupted' => __('Соединение прервалось. Ожидаем восстановления.'),
        ];
    }
}
