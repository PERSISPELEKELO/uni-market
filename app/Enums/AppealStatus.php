<?php

declare(strict_types=1);

namespace App\Enums;

enum AppealStatus: string
{
    case PENDING = 'PENDING';
    case UNDER_REVIEW = 'UNDER_REVIEW';
    case UPHELD = 'UPHELD';
    case OVERTURNED = 'OVERTURNED';
}
