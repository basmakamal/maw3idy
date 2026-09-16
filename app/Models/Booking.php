<?php

namespace App\Models;

use App\Booking\Availability\Period;
use App\Casts\UtcDateTime;
use App\Enums\BookingStatus;
use App\Tenancy\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A confirmed (or cancelled) appointment. Times are UTC instants; the service's
 * duration, buffer and price are snapshotted so later edits never rewrite history.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $service_id
 * @property int $staff_id
 * @property string $reference
 * @property string $customer_name
 * @property string $customer_phone
 * @property string|null $customer_email
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property int $duration_minutes
 * @property int $buffer_after_minutes
 * @property string $price
 * @property BookingStatus $status
 * @property bool|null $slot_lock
 * @property string $cancel_token
 * @property CarbonImmutable|null $cancelled_at
 * @property string|null $cancellation_reason
 */
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * Customer-supplied fields only. Everything that decides *when* and *who*
     * is set by the CreateBooking action, never from request input.
     *
     * @var list<string>
     */
    protected $fillable = [
        'customer_name',
        'customer_phone',
        'customer_email',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => UtcDateTime::class,
            'ends_at' => UtcDateTime::class,
            'duration_minutes' => 'integer',
            'buffer_after_minutes' => 'integer',
            'price' => 'decimal:2',
            'status' => BookingStatus::class,
            'slot_lock' => 'boolean',
            'cancelled_at' => UtcDateTime::class,
        ];
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * @param  Builder<Booking>  $query
     * @return Builder<Booking>
     */
    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', BookingStatus::Confirmed);
    }

    /**
     * @param  Builder<Booking>  $query
     * @return Builder<Booking>
     */
    public function scopeOverlapping(Builder $query, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return $query->where('starts_at', '<', $to)->where('ends_at', '>', $from);
    }

    public function isConfirmed(): bool
    {
        return $this->status === BookingStatus::Confirmed;
    }

    /**
     * The appointment itself, without buffer.
     */
    public function period(): Period
    {
        return new Period($this->starts_at, $this->ends_at);
    }

    /**
     * The time this booking removes from the staff member's day: the appointment plus its buffer.
     */
    public function blockedPeriod(): Period
    {
        return $this->period()->extendedBy($this->buffer_after_minutes);
    }

    /**
     * Release the slot. Clearing slot_lock lets the unique index accept a new
     * confirmed booking at the same start.
     */
    public function cancel(?string $reason = null): void
    {
        $this->status = BookingStatus::Cancelled;
        $this->slot_lock = null;
        $this->cancelled_at = CarbonImmutable::now();
        $this->cancellation_reason = $reason;
        $this->save();
    }
}
