<?php

namespace App\Models;

use App\Enums\CallEventType;
use Database\Factories\CallEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A line in the phone console, written by the Vonage webhooks.
 *
 * @property int $id
 * @property CallEventType $type
 * @property string|null $call_uuid
 * @property string|null $from
 * @property string|null $to
 * @property string|null $status
 * @property string $message
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['type', 'call_uuid', 'from', 'to', 'status', 'message'])]
class CallEvent extends Model
{
    /** @use HasFactory<CallEventFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CallEventType::class,
        ];
    }
}
