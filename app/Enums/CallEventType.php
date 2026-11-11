<?php

namespace App\Enums;

enum CallEventType: string
{
    case Incoming = 'incoming';
    case Answerphone = 'answerphone';
    case RingingBrowser = 'ringing_browser';
    case Outbound = 'outbound';
    case Status = 'status';

    /**
     * Short label shown in the phone console.
     */
    public function label(): string
    {
        return match ($this) {
            self::Incoming => 'INCOMING',
            self::Answerphone => 'ANSWERPHONE',
            self::RingingBrowser => 'RINGING',
            self::Outbound => 'OUTBOUND',
            self::Status => 'STATUS',
        };
    }
}
