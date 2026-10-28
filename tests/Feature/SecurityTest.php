<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Farm;
use App\Models\User;
use App\Services\ImageSanitizer;
use App\Services\SubscriptionService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(PlanSeeder::class);
    }

    private function farmWith(): array
    {
        $owner = User::factory()->create();
        $farm = Farm::factory()->create(['owner_user_id' => $owner->id]);
        $farm->users()->attach($owner->id, ['role' => 'owner']);
        app(SubscriptionService::class)->startTrial($farm);

        return [$owner, $farm->fresh()];
    }

    public function test_security_headers_are_sent(): void
    {
        $response = $this->get('/login')->assertOk();

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $csp = $response->headers->get('Content-Security-Policy-Report-Only');
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);
        $this->assertNull($response->headers->get('Content-Security-Policy'));
    }

    public function test_csp_mode_switches_between_enforce_and_off(): void
    {
        config(['security.csp_mode' => 'enforce']);
        $enforced = $this->get('/login');
        $this->assertNotNull($enforced->headers->get('Content-Security-Policy'));
        $this->assertNull($enforced->headers->get('Content-Security-Policy-Report-Only'));

        config(['security.csp_mode' => 'off']);
        $off = $this->get('/login');
        $this->assertNull($off->headers->get('Content-Security-Policy'));
        $this->assertNull($off->headers->get('Content-Security-Policy-Report-Only'));

        config(['security.headers' => false]);
        $this->assertNull($this->get('/login')->headers->get('X-Content-Type-Options'));
    }

    public function test_hsts_is_only_sent_over_https(): void
    {
        config(['security.hsts' => true]);

        $this->get('http://localhost/login')->assertHeaderMissing('Strict-Transport-Security');

        $this->withServerVariables(['HTTPS' => 'on'])
            ->get('https://localhost/login')
            ->assertHeader('Strict-Transport-Security');
    }

    public function test_signed_in_pages_are_not_cached_by_the_browser(): void
    {
        [$owner, $farm] = $this->farmWith();

        $guest = $this->get('/login');
        $this->assertStringNotContainsString('no-store', (string) $guest->headers->get('Cache-Control'));

        $signedIn = $this->actingAs($owner)->get(route('farms.reports.index', $farm))->assertOk();
        $this->assertStringContainsString('no-store', (string) $signedIn->headers->get('Cache-Control'));
    }

    public function test_weak_passwords_are_rejected_at_registration(): void
    {
        $this->post('/register', [
            'name' => 'রহিম',
            'email' => 'rahim@example.com',
            'password' => 'abcdefgh',
            'password_confirmation' => 'abcdefgh',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'rahim@example.com']);
    }

    public function test_password_reset_enforces_the_same_rules(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);
        $base = ['token' => $token, 'email' => $user->email];

        $this->post('/password/reset', $base + ['password' => 'abcdefgh', 'password_confirmation' => 'abcdefgh'])
            ->assertSessionHasErrors('password');

        $this->post('/password/reset', $base + ['password' => 'Abcdef123', 'password_confirmation' => 'Abcdef123'])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('Abcdef123', $user->fresh()->password));
        $this->assertTrue(AuditLog::where('event', 'password_reset')->exists());
    }

    public function test_the_reset_email_form_is_rate_limited_per_ip(): void
    {
        Notification::fake();
        config(['security.auth_throttle.password/email' => [2, 10]]);

        $this->post('/password/email', ['email' => 'a@example.com'])->assertStatus(302);
        $this->post('/password/email', ['email' => 'a@example.com'])->assertStatus(302);
        $this->post('/password/email', ['email' => 'a@example.com'])->assertStatus(429);
    }

    public function test_too_many_wrong_passwords_lock_the_login_and_are_logged(): void
    {
        [$owner, $farm] = $this->farmWith();

        foreach (range(1, 6) as $_) {
            $this->post('/login', ['email' => $owner->email, 'password' => 'wrong-password']);
        }

        $this->assertTrue(AuditLog::where('event', 'lockout')->where('farm_id', $farm->id)->exists());

        // লক থাকা অবস্থায় সঠিক পাসওয়ার্ডেও ঢোকা যায় না
        $this->post('/login', ['email' => $owner->email, 'password' => 'password']);
        $this->assertGuest();
    }

    public function test_a_successful_login_is_logged_for_the_users_farm(): void
    {
        [$owner, $farm] = $this->farmWith();

        $this->post('/login', ['email' => $owner->email, 'password' => 'password']);

        $log = AuditLog::where('event', 'login')->where('farm_id', $farm->id)->firstOrFail();

        $this->assertSame($owner->name, $log->actor_name);
        $this->assertSame('web', $log->source);
    }

    public function test_failed_logins_are_logged_once_and_unknown_emails_not_at_all(): void
    {
        [$owner, $farm] = $this->farmWith();

        $this->post('/login', ['email' => $owner->email, 'password' => 'wrong-1']);
        $this->post('/login', ['email' => $owner->email, 'password' => 'wrong-2']);

        $this->assertSame(1, AuditLog::where('event', 'login_failed')->where('farm_id', $farm->id)->count());

        $before = AuditLog::where('event', 'login_failed')->count();
        $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong-3']);

        $this->assertSame($before, AuditLog::where('event', 'login_failed')->count());
    }

    public function test_image_sanitizer_strips_metadata_resizes_and_renames(): void
    {
        Storage::fake('public');

        $canvas = imagecreatetruecolor(2400, 1200);
        ob_start();
        imagejpeg($canvas, null, 90);
        $jpeg = (string) ob_get_clean();

        $secret = 'SECRET-GPS-LOCATION';
        $dirty = substr($jpeg, 0, 2) . "\xFF\xFE" . pack('n', strlen($secret) + 2) . $secret . substr($jpeg, 2);

        $path = app(ImageSanitizer::class)->store(
            UploadedFile::fake()->createWithContent('cow.jpg', $dirty),
            'cows/1'
        );

        $this->assertStringStartsWith('cows/1/', $path);
        $this->assertStringEndsWith('.jpg', $path);
        $this->assertStringNotContainsString('cow', basename($path));

        $stored = Storage::disk('public')->get($path);
        $this->assertStringNotContainsString($secret, $stored);

        [$width, $height] = getimagesizefromstring($stored);
        $this->assertSame(1600, $width);
        $this->assertSame(800, $height);
    }

    public function test_image_sanitizer_rejects_files_that_are_not_images(): void
    {
        Storage::fake('public');

        $this->expectException(ValidationException::class);

        app(ImageSanitizer::class)->store(
            UploadedFile::fake()->createWithContent('cow.jpg', '<?php echo "not an image";'),
            'cows/1'
        );
    }

    public function test_super_admin_sees_every_farms_audit_log_and_others_do_not(): void
    {
        [$ownerA, $farmA] = $this->farmWith();
        [$ownerB, $farmB] = $this->farmWith();

        foreach ([[$ownerA, $farmA], [$ownerB, $farmB]] as [$owner, $farm]) {
            $this->actingAs($owner)->post(route('farms.expenses.store', $farm), [
                'category' => 'feed',
                'expense_date' => now()->toDateString(),
                'total_amount' => 500,
                'pay_mode' => 'unpaid',
                'pay_method' => 'cash',
            ])->assertSessionHasNoErrors();
        }

        $admin = User::factory()->create();
        $admin->forceFill(['is_super_admin' => true])->save();

        $this->actingAs($admin)->get(route('admin.audit.index'))
            ->assertOk()
            ->assertViewHas('logs', fn($logs) => collect($logs->items())->pluck('farm_id')->filter()->unique()->count() >= 2);

        $status = $this->actingAs($ownerA)->get(route('admin.audit.index'))->status();
        $this->assertContains($status, [403, 404]);
    }

    public function test_security_check_flags_an_unsafe_setup(): void
    {
        config(['app.debug' => true, 'app.env' => 'local']);

        $code = Artisan::call('security:check');

        $this->assertSame(1, $code);
        $this->assertStringContainsString('APP_DEBUG চালু আছে', Artisan::output());
    }

    public function test_security_check_recognises_a_good_setting(): void
    {
        config(['app.debug' => false, 'app.env' => 'production', 'session.secure' => true]);

        Artisan::call('security:check');

        $output = Artisan::output();

        $this->assertStringContainsString('APP_DEBUG বন্ধ আছে', $output);
        $this->assertStringContainsString('APP_ENV=production', $output);
        $this->assertStringContainsString('কুকি শুধু HTTPS-এ যায়', $output);
    }
}
