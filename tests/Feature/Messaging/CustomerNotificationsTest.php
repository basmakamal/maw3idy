<?php

use App\Actions\Booking\CreateBooking;
use App\Data\BookingRequestData;
use App\Enums\Weekday;
use App\Jobs\SendCustomerNotification;
use App\Mail\CustomerNotificationMail;
use App\Messaging\BookingConfirmedNotification;
use App\Messaging\BookingReminderNotification;
use App\Messaging\Contracts\WhatsAppGateway;
use App\Messaging\CustomerNotifier;
use App\Models\Booking;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

// 2026-10-05 is a Monday; tenant in Riyadh.
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-01 09:00', 'UTC'));

    $this->tenant = bindTenant(Tenant::factory()->create(['name' => 'Acme Salon', 'slug' => 'acme', 'timezone' => 'Asia/Riyadh']));
    $this->service = Service::factory()->lasting(60)->create(['name' => 'Haircut']);
    $this->sara = Staff::factory()->create(['name' => 'Sara', 'email' => 'sara@acme.test']);
    $this->sara->services()->attach($this->service);
    Schedule::factory()->for($this->sara)->on(Weekday::Monday, '09:00', '12:00')->create();

    $this->ten = CarbonImmutable::parse('2026-10-05 10:00', 'Asia/Riyadh')->utc();
});

function makeBooking(?string $email = 'customer@example.com', string $phone = '+966501234567'): Booking
{
    return app(CreateBooking::class)->handle(new BookingRequestData(
        serviceId: test()->service->id,
        staffId: test()->sara->id,
        start: test()->ten,
        customerName: 'Basma',
        customerPhone: $phone,
        customerEmail: $email,
    ));
}

it('queues a confirmation for the customer when a booking is created', function () {
    Queue::fake();

    $booking = makeBooking();

    Queue::assertPushed(SendCustomerNotification::class, 1);
    Queue::assertPushed(fn (SendCustomerNotification $job) => in_array('notification:booking.confirmed', $job->tags(), true)
        && in_array('channel:mail', $job->tags(), true)
        && in_array('booking:'.$booking->getKey(), $job->tags(), true));
});

it('sends an email that states the appointment in the tenant timezone and links to the booking', function () {
    Mail::fake();

    $booking = makeBooking();

    Mail::assertSent(CustomerNotificationMail::class, function (CustomerNotificationMail $mail) use ($booking) {
        $rendered = $mail->render();

        return $mail->hasTo('customer@example.com')
            && $mail->hasReplyTo('sara@acme.test')
            && str_contains($mail->envelope()->subject, 'Acme Salon')
            && str_contains($rendered, $booking->reference)
            && str_contains($rendered, 'Monday 5 October 2026')
            && str_contains($rendered, '10:00')
            && str_contains($rendered, 'Asia/Riyadh')
            && str_contains($rendered, 'dir="ltr"');
    });
});

it('writes the email right to left for an Arabic tenant', function () {
    Mail::fake();

    $arabic = bindTenant(Tenant::factory()->arabic()->create(['slug' => 'jamal', 'timezone' => 'Asia/Riyadh']));
    $service = Service::factory()->lasting(60)->create(['name' => 'قص شعر']);
    $staff = Staff::factory()->create(['name' => 'نورة']);
    $staff->services()->attach($service);
    Schedule::factory()->for($staff)->on(Weekday::Monday, '09:00', '12:00')->create();

    app(CreateBooking::class)->handle(new BookingRequestData(
        serviceId: $service->id,
        staffId: $staff->id,
        start: $this->ten,
        customerName: 'سارة',
        customerPhone: '+966501234567',
        customerEmail: 'customer@example.com',
    ));

    Mail::assertSent(CustomerNotificationMail::class, fn (CustomerNotificationMail $mail) => str_contains($mail->render(), 'dir="rtl"')
        && str_contains($mail->render(), 'قص شعر'));

    // The locale is restored so the rest of the request is unaffected.
    expect(app()->getLocale())->toBe('en');
});

it('skips mail when the customer left no email address', function () {
    Queue::fake();

    makeBooking(email: null);

    Queue::assertNothingPushed();
});

it('does not use WhatsApp until it is enabled', function () {
    Queue::fake();

    makeBooking();
    Queue::assertPushed(SendCustomerNotification::class, 1);

    config(['booking.channels.whatsapp.enabled' => true]);

    Booking::query()->delete();
    makeBooking();

    Queue::assertPushed(SendCustomerNotification::class, 3);
    Queue::assertPushed(fn (SendCustomerNotification $job) => in_array('channel:whatsapp', $job->tags(), true));
});

it('hands an international number to the WhatsApp gateway when enabled', function () {
    config(['booking.channels.whatsapp.enabled' => true]);

    $gateway = $this->mock(WhatsAppGateway::class);
    $gateway->shouldReceive('send')
        ->once()
        ->withArgs(fn (string $to, string $text) => $to === '+966501234567'
            && str_contains($text, 'Acme Salon')
            && str_contains($text, 'Haircut'));

    makeBooking(email: null, phone: '+966 50 123 4567');
});

it('cannot reach a national number over WhatsApp', function () {
    config(['booking.channels.whatsapp.enabled' => true]);
    Queue::fake();

    makeBooking(email: null, phone: '0501234567');

    Queue::assertNothingPushed();
});

it('does not send a reminder for a booking that was cancelled after it was queued', function () {
    $booking = makeBooking();
    $notification = new BookingReminderNotification($booking);
    $job = new SendCustomerNotification($notification, 'mail');

    $booking->cancel('Changed my mind');

    // Faked only now, so the confirmation and cancellation mails are out of scope.
    Mail::fake();

    app()->call([$job, 'handle']);

    Mail::assertNothingSent();
});

it('reports which channels took the message', function () {
    Queue::fake();

    $booking = makeBooking();

    expect(app(CustomerNotifier::class)->send(new BookingConfirmedNotification($booking)))->toBe(['mail']);
});
