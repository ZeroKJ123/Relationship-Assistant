<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Enums\Gender;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/** Собирает текст уведомления в разметке Telegram HTML; весь пользовательский ввод экранируется. */
final class MessageText
{
    /** Лимит Telegram — 4096 символов; запас оставлен на служебные данные. */
    private const MESSAGE_LENGTH = 3900;

    /** При переполнении текст сокращается так, чтобы экранирование не превысило лимит. */
    private const FALLBACK_LENGTH = 900;

    public function title(string $emoji, string $text): string
    {
        return sprintf('%s <b>%s</b>', $emoji, e($text));
    }

    public function text(string $value, int $limit): string
    {
        return e(Str::limit($value, $limit));
    }

    public function labeled(string $label, string $value, int $limit): string
    {
        return sprintf('<i>%s:</i> %s', e($label), $this->text($value, $limit));
    }

    public function byGender(User $user, string $male, string $female, string $neutral): string
    {
        return match ($user->gender) {
            Gender::Male => $male,
            Gender::Female => $female,
            default => $neutral,
        };
    }

    /** Дата словами относительно сегодняшнего дня в указанном часовом поясе. */
    public function moment(CarbonImmutable $at, string $timezone): string
    {
        $local = $at->setTimezone($timezone);
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $time = $local->format('H:i');

        return match ($local->startOfDay()->toDateString()) {
            $today->toDateString() => __('сегодня, :time', ['time' => $time]),
            $today->addDay()->toDateString() => __('завтра, :time', ['time' => $time]),
            $today->subDay()->toDateString() => __('вчера, :time', ['time' => $time]),
            default => $local->isoFormat($local->year === $today->year ? 'dd, DD.MM, HH:mm' : 'dd, DD.MM.YYYY, HH:mm'),
        };
    }

    /** @param list<string> $lines */
    public function compose(array $lines): string
    {
        $message = implode("\n", $lines);
        if (mb_strlen($message) <= self::MESSAGE_LENGTH) {
            return $message;
        }

        return e(Str::limit(html_entity_decode(strip_tags($message), ENT_QUOTES | ENT_HTML5), self::FALLBACK_LENGTH));
    }
}
