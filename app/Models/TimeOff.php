<?php

namespace App\Models;

use App\Booking\Availability\Period;
use App\Casts\UtcDateTime;
use App\Tenancy\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\TimeOffFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An absence that overrides the weekly schedule.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $staff_id
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property string|null $reason
 */
class TimeOff extends Model
{
    /** @use HasFactory<TimeOffFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'time_off';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'starts_at',
        'ends_at',
        'reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => UtcDateTime::class,
            'ends_at' => UtcDateTime::class,
        ];
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * @param  Builder<TimeOff>  $query
     * @return Builder<TimeOff>
     */
    public function scopeOverlapping(Builder $query, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return $query->where('starts_at', '<', $to)->where('ends_at', '>', $from);
    }

    public function period(): Period
    {
        return new Period($this->starts_at, $this->ends_at);
    }
}
