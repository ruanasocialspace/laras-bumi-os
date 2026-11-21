<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\IdentityAccess\Accounts;
use App\Modules\IdentityAccess\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class IdentityAccessTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'testing-password-74';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->withHeader('Origin', 'http://localhost:8080');
    }

    public function test_role_seed_can_run_twice_without_overwriting_user_assignments(): void
    {
        $admin = $this->bootstrapAdmin();
        $grant = DB::table('user_roles')->where('user_id', $admin->id)->first();
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('roles', 8);
        $this->assertDatabaseCount('reference_values', 3);
        $this->assertDatabaseHas('user_roles', ['id' => $grant->id]);
        $this->assertTrue($admin->fresh()->hasPermission('identity.manage'));
    }

    public function test_login_rotates_session_logout_clears_it_and_records_audit(): void
    {
        $admin = $this->bootstrapAdmin();
        $cookie = $this->get('/sanctum/csrf-cookie')->assertNoContent()->getCookie('laras_session');
        $this->assertNotNull($cookie);

        $login = $this->withCookie('laras_session', $cookie->getValue())->postJson('/api/v1/auth/login', [
            'email' => strtoupper($admin->email),
            'password' => self::PASSWORD,
        ])->assertOk()->assertJsonPath('data.id', $admin->id)->assertJsonMissingPath('data.password');
        $signedIn = $login->getCookie('laras_session');
        $this->assertNotNull($signedIn);
        $this->assertNotSame($cookie->getValue(), $signedIn->getValue());
        Auth::forgetGuards();
        $this->withCookie('laras_session', $signedIn->getValue())->getJson('/api/v1/auth/me')
            ->assertOk()->assertJsonPath('data.roles.0.code', 'super_admin');
        $this->postJson('/api/v1/auth/logout')->assertNoContent();
        Auth::forgetGuards();
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->assertDatabaseHas('audit_logs', ['action' => 'identity.login', 'actor_id' => $admin->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'identity.logout', 'actor_id' => $admin->id]);
    }

    public function test_disabled_and_unknown_accounts_receive_the_same_login_error(): void
    {
        $this->bootstrapAdmin();
        $disabledEmail = 'disabled@example.test';
        User::create(['name' => 'Disabled', 'email' => $disabledEmail, 'password' => self::PASSWORD, 'active' => false]);

        foreach ([$disabledEmail, 'unknown@example.test'] as $email) {
            $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => self::PASSWORD])
                ->assertUnprocessable()->assertJsonPath('error.fields.email.0', 'Email atau kata sandi tidak sesuai.');
        }
    }

    public function test_admin_manages_accounts_and_driver_cannot_access_protected_resources(): void
    {
        $admin = $this->bootstrapAdmin();
        $this->withinSession($admin);
        $id = $this->postJson('/api/v1/users', [
            'name' => 'Driver',
            'email' => 'DRIVER@example.test',
            'password' => self::PASSWORD,
            'role_codes' => ['driver'],
            'session_version' => 999,
        ])->assertCreated()->assertJsonPath('data.email', 'driver@example.test')
            ->assertJsonPath('data.permissions.0', 'collections.drive')->json('data.id');

        $this->getJson('/api/v1/users?q=Driver')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/roles')->assertOk()->assertJsonCount(8, 'data');
        $this->assertDatabaseHas('audit_logs', ['action' => 'identity.user_created', 'resource_id' => $id]);
        $this->assertDatabaseHas('users', ['id' => $id, 'session_version' => 1]);

        Auth::forgetGuards();
        $driverSession = $this->postJson('/api/v1/auth/login', [
            'email' => 'driver@example.test',
            'password' => self::PASSWORD,
        ])->assertOk()->getCookie('laras_session');
        $this->assertNotNull($driverSession);
        Auth::forgetGuards();
        $this->withCookie('laras_session', $driverSession->getValue());
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.id', $id);
        $this->getJson('/api/v1/users')->assertForbidden()->assertJsonPath('error.code', 'forbidden');
        $this->getJson('/api/v1/users/'.$admin->id)->assertForbidden();
        $this->getJson('/api/v1/users/'.Str::uuid())->assertForbidden();
        $this->getJson('/api/v1/roles')->assertForbidden();
        $this->getJson('/api/v1/audit-logs')->assertForbidden();
        $this->patchJson('/api/v1/users/'.$admin->id, ['role_codes' => ['driver']])->assertForbidden();
        $this->getJson('/api/v1/reference')->assertOk()->assertJsonPath('data.timezone', 'Asia/Jakarta');
    }

    public function test_admin_protection_and_session_revocation_are_persisted(): void
    {
        $admin = $this->bootstrapAdmin();
        $this->withinSession($admin);
        $this->patchJson('/api/v1/users/'.$admin->id, ['role_codes' => ['driver']])->assertConflict();
        $this->patchJson('/api/v1/users/'.$admin->id, ['active' => false])->assertConflict();
        $this->patchJson('/api/v1/users/'.$admin->id, ['role_codes' => ['not-found']])->assertUnprocessable();
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'active' => true, 'session_version' => 1]);

        $this->patchJson('/api/v1/users/'.$admin->id, ['password' => 'new-testing-password'])->assertOk();
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'session_version' => 2]);
        $this->withinSession($admin->fresh(), 1);
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_audit_is_authorized_and_append_only_at_the_database(): void
    {
        $this->assertSame('UTC', DB::selectOne("select current_setting('TimeZone') as zone")->zone);
        $this->withinSession($this->bootstrapAdmin());
        $this->getJson('/api/v1/audit-logs')->assertOk()->assertJsonPath('data.0.action', 'identity.admin_bootstrapped');
        $this->assertDatabaseHas('audit_logs', ['action' => 'audit.viewed']);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Audit logs are append-only');
        DB::table('audit_logs')->update(['action' => 'overwritten']);
    }

    public function test_demo_account_command_is_disabled_in_test_environment(): void
    {
        config(['laras.demo_accounts' => true, 'laras.demo_password' => self::PASSWORD]);
        $this->artisan('identity:seed-demo')->assertExitCode(1);
        $this->assertDatabaseCount('users', 0);
    }

    private function bootstrapAdmin(): User
    {
        return app(Accounts::class)->bootstrap('Test Administrator', 'admin@example.test', self::PASSWORD)->refresh();
    }

    private function withinSession(User $user, ?int $version = null): void
    {
        Auth::forgetGuards();
        $this->actingAs($user, 'web')->withSession(['identity_version' => $version ?? $user->session_version]);
    }
}
