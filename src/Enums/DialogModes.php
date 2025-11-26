<?php

namespace Devhammed\Photoshop\Enums;

use Devhammed\Photoshop\Contracts\Rawable;

/**
 * Controls whether Photoshop displays dialogs during scripts.
 */
enum DialogModes implements Rawable
{
    /** Show all dialogs. */
    case ALL;

    /** Show only dialogs related to errors. */
    case ERROR;

    /** Show no dialogs. */
    case NO;

    public function toRaw(): string
    {
        return match ($this) {
            self::ALL => 'DialogModes.ALL',
            self::ERROR => 'DialogModes.ERROR',
            self::NO => 'DialogModes.NO',
        };
    }
}
