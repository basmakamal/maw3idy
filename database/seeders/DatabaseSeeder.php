<?php

namespace Database\Seeders;

use App\Enums\Weekday;
use App\Models\Booking;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TimeOff;
use App\Models\User;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Two demo tenants, one per supported locale, each with services, staff,
 * weekly hours, a little time off and a handful of upcoming bookings, so a
 * fresh clone can be explored end to end. Every seeded account uses the
 * password "password".
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $demo = Tenant::factory()->create([
            'name' => 'Demo Salon',
            'slug' => 'demo',
            'timezone' => 'Asia/Riyadh',
            'locale' => 'en',
        ]);

        User::factory()->for($demo)->owner()->create(['name' => 'Demo Owner', 'email' => 'owner@demo.test']);
        User::factory()->for($demo)->create(['name' => 'Demo Staff', 'email' => 'staff@demo.test']);

        app(TenantContext::class)->runAs($demo, function (): void {
            $haircut = Service::factory()->create(['name' => 'Haircut', 'description' => 'Wash, cut and style.', 'duration_minutes' => 30, 'buffer_after_minutes' => 5, 'price' => '80.00']);
            $beard = Service::factory()->create(['name' => 'Beard trim', 'description' => null, 'duration_minutes' => 20, 'buffer_after_minutes' => 0, 'price' => '45.00']);
            $colour = Service::factory()->create(['name' => 'Hair colour', 'description' => 'Full colour, including consultation.', 'duration_minutes' => 90, 'buffer_after_minutes' => 15, 'price' => '250.00']);

            $sara = Staff::factory()->create(['name' => 'Sara', 'email' => 'sara@demo.test']);
            $omar = Staff::factory()->create(['name' => 'Omar', 'email' => 'omar@demo.test']);

            $sara->services()->attach([$haircut->id, $colour->id]);
            $omar->services()->attach([$haircut->id, $beard->id]);

            $this->hours($sara, [Weekday::Sunday, Weekday::Monday, Weekday::Tuesday, Weekday::Wednesday, Weekday::Thursday], '09:00', '18:00');
            $this->hours($omar, [Weekday::Saturday, Weekday::Sunday, Weekday::Monday, Weekday::Tuesday, Weekday::Wednesday], '12:00', '21:00');

            $monday = CarbonImmutable::now('Asia/Riyadh')->addWeek()->next('Monday');

            TimeOff::factory()->for($sara)->between($monday->addDays(2)->setTime(12, 0), $monday->addDays(2)->setTime(14, 0))->create(['reason' => 'Training']);

            $this->booking($haircut, $sara, $monday->setTime(10, 0), 'Layla A.', '+966501112222');
            $this->booking($colour, $sara, $monday->setTime(11, 0), 'Reem S.', '+966503334444');
            $this->booking($haircut, $omar, $monday->setTime(13, 0), 'Khalid M.', '+966505556666');
            $this->booking($beard, $omar, $monday->addDay()->setTime(18, 0), 'Faisal H.', '+966507778888');
        });

        $jamal = Tenant::factory()->arabic()->create([
            'name' => 'صالون الجمال',
            'slug' => 'jamal',
            'timezone' => 'Asia/Riyadh',
        ]);

        User::factory()->for($jamal)->owner()->create(['name' => 'نورة', 'email' => 'owner@jamal.test']);

        app(TenantContext::class)->runAs($jamal, function (): void {
            $cut = Service::factory()->create(['name' => 'قص شعر', 'description' => 'غسيل وقص وتصفيف.', 'duration_minutes' => 30, 'buffer_after_minutes' => 5, 'price' => '80.00']);
            $colour = Service::factory()->create(['name' => 'صبغة', 'description' => 'صبغة كاملة مع استشارة.', 'duration_minutes' => 90, 'buffer_after_minutes' => 15, 'price' => '300.00']);

            $noura = Staff::factory()->create(['name' => 'نورة', 'email' => null]);
            $hind = Staff::factory()->create(['name' => 'هند', 'email' => null]);

            $noura->services()->attach([$cut->id, $colour->id]);
            $hind->services()->attach([$cut->id]);

            $this->hours($noura, [Weekday::Sunday, Weekday::Monday, Weekday::Tuesday, Weekday::Wednesday, Weekday::Thursday], '10:00', '20:00');
            $this->hours($hind, [Weekday::Saturday, Weekday::Sunday, Weekday::Monday, Weekday::Tuesday], '14:00', '22:00');

            $sunday = CarbonImmutable::now('Asia/Riyadh')->addWeek()->next('Sunday');

            $this->booking($cut, $noura, $sunday->setTime(11, 0), 'سارة العلي', '+966509990000');
            $this->booking($colour, $noura, $sunday->setTime(16, 0), 'مها الحربي', '+966508887777');
        });
    }

    /**
     * @param  list<Weekday>  $weekdays
     */
    private function hours(Staff $staff, array $weekdays, string $start, string $end): void
    {
        foreach ($weekdays as $weekday) {
            Schedule::factory()->for($staff)->on($weekday, $start, $end)->create();
        }
    }

    private function booking(Service $service, Staff $staff, CarbonImmutable $start, string $customer, string $phone): void
    {
        Booking::factory()
            ->for($service, 'service')
            ->for($staff, 'staff')
            ->startingAt($start)
            ->create(['customer_name' => $customer, 'customer_phone' => $phone, 'customer_email' => null]);
    }
}
