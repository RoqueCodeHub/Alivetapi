<?php

namespace Tests\Feature\Api;

use App\Console\Kernel;
use App\Models\AuditLog;
use App\Models\BackupProbe;
use App\Models\User;
use App\Services\BackupService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Tests\TestCase;

class AuditV1Test extends TestCase
{
    private string $token;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sanctum.stateful' => []]);
        $this->seed(PermissionSeeder::class);
        $this->admin = User::factory()->create([
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->admin->syncRoles(['Administrador']);
        $this->flushHeaders();
        $this->token = $this->admin->createToken('api')->plainTextToken;
    }

    public function test_login_is_logged_and_password_is_hidden_in_audit(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => $this->admin->email,
            'password' => 'wrong-pass',
        ])->assertUnauthorized();

        $this->assertDatabaseHas('login_logs', [
            'email' => strtolower($this->admin->email),
            'success' => 0,
        ]);

        $this->withToken($this->token)->putJson('/api/v1/auth/password', [
            'current_password' => 'password',
            'password' => 'password2',
            'password_confirmation' => 'password2',
        ])->assertOk();

        $log = AuditLog::query()->where('model', 'User')->where('action', 'updated')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame('[hidden]', $log->after['password'] ?? null);

        $this->postJson('/api/v1/auth/login', [
            'email' => $this->admin->email,
            'password' => 'password2',
        ])->assertOk();

        $this->assertDatabaseHas('login_logs', [
            'email' => strtolower($this->admin->email),
            'success' => 1,
        ]);
    }

    public function test_operator_cannot_view_audit_and_admin_can(): void
    {
        $operator = User::factory()->create([
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $operator->syncRoles(['Operador de tienda']);
        $opToken = $operator->createToken('api')->plainTextToken;

        $this->flushHeaders();
        $this->withToken($opToken)->getJson('/api/v1/audit-logs')->assertForbidden();
        $this->flushHeaders();
        $this->actingAs($this->admin, 'sanctum');
        $this->getJson('/api/v1/audit-logs')->assertOk();
        $this->getJson('/api/v1/login-logs')->assertOk();
        $this->getJson('/api/v1/backups')->assertOk();
        $this->getJson('/api/v1/security-status')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['notes', 'force_https', 'app_debug']]);
    }

    public function test_backup_roundtrip_on_probe_table(): void
    {
        $token = 'probe-'.uniqid();
        BackupProbe::query()->create(['token' => $token]);
        $backups = app(BackupService::class);
        $dump = $backups->create();
        $this->assertTrue(File::exists($dump));
        $this->assertStringContainsString($token, File::get($dump));

        BackupProbe::query()->where('token', $token)->delete();
        $this->assertDatabaseMissing('backup_probes', ['token' => $token]);

        $quoted = DB::getPdo()->quote($token);
        $sqlPath = $backups->directory().DIRECTORY_SEPARATOR.'restore-probe-'.uniqid().'.sql';
        File::put($sqlPath, "INSERT INTO `backup_probes` (`token`) VALUES ({$quoted});\n");
        $backups->restore(basename($sqlPath));
        $this->assertDatabaseHas('backup_probes', ['token' => $token]);

        $this->expectException(InvalidArgumentException::class);
        $backups->restore('no-existe.sql');
    }

    public function test_backup_command_and_schedule_and_security_headers(): void
    {
        $this->artisan('backup:database')->assertSuccessful();
        $this->artisan('backup:restore')->assertFailed();

        $schedule = app(Schedule::class);
        $kernel = app(Kernel::class);
        $method = new \ReflectionMethod($kernel, 'schedule');
        $method->invoke($kernel, $schedule);
        $listed = collect($schedule->events())->map(fn ($event) => $event->command ?? '')->implode(' | ');
        $this->assertStringContainsString('backup:database', $listed);

        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY');
    }
}
