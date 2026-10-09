<?php

declare(strict_types=1);

namespace App\Enums;

enum AgreementProposalStatus: string
{
    case Proposed = 'proposed';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Superseded = 'superseded';

    public function label(): string
    {
        return match ($this) {
            self::Proposed => 'Ожидает двух согласий',
            self::Accepted => 'Согласовано',
            self::Declined => 'Отклонено',
            self::Superseded => 'Заменено новым вариантом',
        };
    }
}
