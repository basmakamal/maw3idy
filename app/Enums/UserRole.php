<?php

namespace App\Enums;

enum UserRole: string
{
    case Owner = 'owner';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Owner => __('Owner'),
            self::Member => __('Member'),
        };
    }
}
