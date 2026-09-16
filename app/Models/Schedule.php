<?php

namespace App\Models;

use App\Booking\Availability\WorkingHours;
use App\Enums\Weekday;
use App\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\ScheduleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One block of a staff member's weekly hours, in the tenant's local clock.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $staff_id
 * @property Weekday $weekday
 * @property string $start_time
 * @property string $end_time
 */
class Schedule extends Model
{
    /** @use HasFactory<ScheduleFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'weekday',
        'start_time',
        'end_time',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weekday' => Weekday::class,
        ];
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function toWorkingHours(): WorkingHours
    {
        return new WorkingHours(
            $this->weekday,
            substr($this->start_time, 0, 5),
            substr($this->end_time, 0, 5),
        );
    }
}
