<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Done = 'done';

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::Todo => [self::InProgress],
            self::InProgress => [self::Todo, self::Done],
            self::Done => [self::InProgress],
        }, true);
    }
}
