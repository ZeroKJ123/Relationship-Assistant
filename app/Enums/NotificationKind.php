<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationKind: string
{
    case RequestEvent = 'request_event';
    case DutyEvent = 'duty_event';
    case MoodPrompt = 'mood_prompt';
    case MoodEntry = 'mood_entry';
    case MoodEmoji = 'mood_emoji';
    case Reminder = 'reminder';
    case DutyReminder = 'duty_reminder';
    case MovieChoice = 'movie_choice';
    case ChoreDraw = 'chore_draw';
}
