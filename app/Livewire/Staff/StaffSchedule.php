<?php

namespace App\Livewire\Staff;

use App\Booking\Availability\WorkingHours;
use App\Enums\Weekday;
use App\Models\Schedule;
use App\Models\Staff;
use App\Models\TimeOff;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * A staff member's weekly hours (one block per weekday in this UI; the engine
 * supports more) and their upcoming time off. Times are entered in the
 * tenant's timezone and stored as UTC.
 */
class StaffSchedule extends Component
{
    public Staff $staff;

    /**
     * Hydrated from the browser on every request, so keys may be missing or mistyped.
     *
     * @var array<int, array{working?: bool, start?: string, end?: string}>
     */
    public array $days = [];

    public string $offStart = '';

    public string $offEnd = '';

    public string $offReason = '';

    public function mount(Staff $staff): void
    {
        $this->staff = $staff->loadMissing('schedules');

        $byDay = $this->staff->schedules->keyBy(fn (Schedule $schedule) => $schedule->weekday->value);

        foreach (Weekday::ordered() as $weekday) {
            /** @var Schedule|null $schedule */
            $schedule = $byDay->get($weekday->value);

            $this->days[$weekday->value] = [
                'working' => $schedule !== null,
                'start' => $schedule !== null ? substr($schedule->start_time, 0, 5) : '09:00',
                'end' => $schedule !== null ? substr($schedule->end_time, 0, 5) : '17:00',
            ];
        }
    }

    public function saveHours(): void
    {
        $this->authorize('update', $this->staff);
        $this->resetErrorBag();

        $hours = [];

        foreach ($this->days as $value => $day) {
            if (! (bool) ($day['working'] ?? false)) {
                continue;
            }

            try {
                $hours[] = new WorkingHours(Weekday::from((int) $value), (string) ($day['start'] ?? ''), (string) ($day['end'] ?? ''));
            } catch (InvalidArgumentException) {
                $this->addError("days.{$value}.end", __('Enter a start and an end time, with the end after the start.'));
            }
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        DB::transaction(function () use ($hours): void {
            $this->staff->schedules()->delete();

            foreach ($hours as $block) {
                $this->staff->schedules()->create([
                    'weekday' => $block->weekday,
                    'start_time' => $block->start,
                    'end_time' => $block->end,
                ]);
            }
        });

        $this->staff->load('schedules');

        session()->flash('status', __('Working hours saved.'));
    }

    public function addTimeOff(): void
    {
        $this->authorize('update', $this->staff);

        $this->validate([
            'offStart' => ['required', 'date_format:Y-m-d\TH:i'],
            'offEnd' => ['required', 'date_format:Y-m-d\TH:i'],
            'offReason' => ['nullable', 'string', 'max:200'],
        ]);

        $timezone = tenant()->timezone;
        $start = CarbonImmutable::parse($this->offStart, $timezone);
        $end = CarbonImmutable::parse($this->offEnd, $timezone);

        if (! $end->greaterThan($start)) {
            $this->addError('offEnd', __('Time off must end after it starts.'));

            return;
        }

        $this->staff->timeOff()->create([
            'starts_at' => $start,
            'ends_at' => $end,
            'reason' => $this->offReason !== '' ? $this->offReason : null,
        ]);

        $this->reset('offStart', 'offEnd', 'offReason');
        unset($this->upcomingTimeOff);

        session()->flash('status', __('Time off added.'));
    }

    public function removeTimeOff(int $timeOffId): void
    {
        $this->authorize('update', $this->staff);

        $this->staff->timeOff()->whereKey($timeOffId)->delete();
        unset($this->upcomingTimeOff);
    }

    /**
     * @return EloquentCollection<int, TimeOff>
     */
    #[Computed]
    public function upcomingTimeOff(): EloquentCollection
    {
        return $this->staff->timeOff()
            ->where('ends_at', '>', CarbonImmutable::now())
            ->orderBy('starts_at')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.staff.staff-schedule', [
            'weekdays' => Weekday::ordered(),
            'timezone' => tenant()->timezone,
            'canManage' => auth()->user()?->can('update', $this->staff) ?? false,
        ]);
    }
}
