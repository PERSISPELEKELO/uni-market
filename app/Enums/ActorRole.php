<?php

declare(strict_types=1);

namespace App\Enums;

enum ActorRole: string
{
    case STUDENT = 'student';
    case GOVERNANCE_COMMITTEE = 'governance_committee';
    case ADMIN = 'admin';
    case SYSTEM = 'system';
}
