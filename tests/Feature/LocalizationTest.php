<?php

use App\Enums\Weekday;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Localization;

beforeEach(function () {
    $this->english = bindTenant(Tenant::factory()->create(['slug' => 'acme', 'name' => 'Acme Salon', 'locale' => 'en']));
    $this->arabic = Tenant::factory()->arabic()->create(['slug' => 'jamal', 'name' => 'صالون الجمال']);
});

it('serves each tenant in its own language and direction', function () {
    $this->get(tenantUrl($this->english, '/login'))
        ->assertOk()
        ->assertSee('<html lang="en" dir="ltr"', false)
        ->assertSee('Sign in');

    $this->get(tenantUrl($this->arabic, '/login'))
        ->assertOk()
        ->assertSee('<html lang="ar" dir="rtl"', false)
        ->assertSee('تسجيل الدخول');
});

it('lets a visitor switch language and remembers the choice', function () {
    $this->post(tenantUrl($this->english, '/locale'), ['locale' => 'ar'])->assertRedirect();

    $this->get(tenantUrl($this->english, '/login'))
        ->assertSee('<html lang="ar" dir="rtl"', false)
        ->assertSee('تسجيل الدخول')
        ->assertDontSee('Remember me');

    $this->post(tenantUrl($this->english, '/locale'), ['locale' => 'en'])->assertRedirect();

    $this->get(tenantUrl($this->english, '/login'))->assertSee('Remember me');
});

it('refuses a language the platform does not have', function () {
    $this->post(tenantUrl($this->english, '/locale'), ['locale' => 'fr'])
        ->assertSessionHasErrors('locale');

    $this->post(tenantUrl($this->english, '/locale'), [])
        ->assertSessionHasErrors('locale');

    expect(session()->has(Localization::SESSION_KEY))->toBeFalse();
});

it('translates the public booking page, including validation messages', function () {
    $service = Service::factory()->lasting(30)->create(['name' => 'Haircut']);
    $staff = Staff::factory()->create();
    $staff->services()->attach($service);

    $this->post(tenantUrl($this->english, '/locale'), ['locale' => 'ar']);

    $this->get(tenantUrl($this->english, '/book'))
        ->assertOk()
        ->assertSee('ماذا تريد أن تحجز؟')
        ->assertSee('خطوات الحجز');
});

it('translates the dashboard for a signed-in user', function () {
    $owner = User::factory()->owner()->create();

    $this->post(tenantUrl($this->english, '/locale'), ['locale' => 'ar']);

    $this->actingAs($owner)
        ->get(tenantUrl($this->english, '/dashboard'))
        ->assertOk()
        ->assertSee('<html lang="ar" dir="rtl"', false)
        ->assertSee('لوحة التحكم')
        ->assertSee('الخدمات')
        ->assertSee('تسجيل الخروج');
});

it('translates the central domain too', function () {
    $this->get(centralUrl('/register'))->assertOk()->assertSee('Create your booking page');

    $this->post(centralUrl('/locale'), ['locale' => 'ar'])->assertRedirect();

    $this->get(centralUrl('/register'))
        ->assertOk()
        ->assertSee('<html lang="ar" dir="rtl"', false)
        ->assertSee('أنشئ صفحة الحجز الخاصة بك');
});

it('knows the direction and name of each locale', function () {
    expect(Localization::direction('ar'))->toBe('rtl')
        ->and(Localization::direction('en'))->toBe('ltr')
        ->and(Localization::direction('ar_SA'))->toBe('rtl')
        ->and(Localization::name('ar'))->toBe('العربية')
        ->and(Localization::name('en'))->toBe('English')
        ->and(Localization::name('zz'))->toBe('zz')
        ->and(Localization::supported())->toBe(['en', 'ar'])
        ->and(Localization::isSupported('ar'))->toBeTrue()
        ->and(Localization::isSupported('fr'))->toBeFalse()
        ->and(Localization::isSupported(null))->toBeFalse();
});

it('translates every weekday name, which the schedule editor renders from the enum', function () {
    app()->setLocale('ar');

    $labels = array_map(fn (Weekday $day) => $day->label(), Weekday::ordered());

    expect($labels)->toBe(['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت']);
});

it('has an Arabic translation for every string the interface uses', function () {
    /** @var array<string, string> $translations */
    $translations = json_decode((string) file_get_contents(lang_path('ar.json')), true, 512, JSON_THROW_ON_ERROR);

    $sources = [];
    foreach (['resources/views', 'app'] as $directory) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($directory), FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            preg_match_all("/__\('((?:[^'\\\\]|\\\\.)+)'/", (string) file_get_contents($file->getPathname()), $matches);

            foreach ($matches[1] as $key) {
                $sources[stripslashes($key)] = true;
            }
        }
    }

    // Keys namespaced with a dot live in lang/ar/*.php instead.
    $missing = array_values(array_filter(
        array_keys($sources),
        fn (string $key) => ! isset($translations[$key]) && ! str_contains($key, '.php') && ! preg_match('/^[a-z_]+\.[a-z_]+$/', $key),
    ));

    expect($missing)->toBe([], 'Untranslated strings: '.implode(' | ', $missing));
});
