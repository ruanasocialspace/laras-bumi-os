<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\IdentityAccess\Role;
use App\Modules\IdentityAccess\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CustomerContractTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'test-phase-two-password';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2027-01-05T00:30:00+07:00'));
        $this->seed(DatabaseSeeder::class);
        $this->withHeader('Origin', 'http://localhost:8080');
    }

    public function test_customer_directory_contacts_locations_and_lifecycle_are_persisted(): void
    {
        $this->getJson('/api/v1/customers')->assertUnauthorized();
        $this->loginAs('operations_manager');
        $category = $this->postJson('/api/v1/customer-categories', ['code' => 'fasilitas', 'name' => 'Fasilitas'])
            ->assertCreated()->assertJsonPath('data.code', 'FASILITAS')->json('data.id');
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseHas('customer_categories', ['id' => $category, 'code' => 'FASILITAS']);

        $one = $this->customer('C-A', $category);
        $two = $this->customer('C-B');
        $this->getJson('/api/v1/customers?category_id='.$category.'&q=c-a&status=active')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $one);

        $contact = $this->postJson("/api/v1/customers/$one/contacts", [
            'name' => 'Kontak Contoh', 'phone' => '08000000', 'email' => 'fixture@example.test',
        ])->assertCreated()->json('data.id');
        $this->patchJson("/api/v1/customers/$one/contacts/$contact", ['name' => 'Kontak Diperbarui'])
            ->assertOk()->assertJsonPath('data.phone', '08000000');
        $this->patchJson("/api/v1/customers/$two/contacts/$contact", ['name' => 'Di luar pelanggan'])->assertNotFound();
        $this->patchJson("/api/v1/customers/$one/contacts/$contact", ['phone' => null, 'email' => null])->assertUnprocessable();

        $this->postJson("/api/v1/customers/$one/locations", [
            'name' => 'Lokasi Contoh', 'address' => 'Alamat Contoh', 'latitude' => '-8.123456', 'longitude' => '113.123456',
        ])->assertCreated()->assertJsonPath('data.latitude', '-8.123456');
        $this->location($one);
        $this->getJson("/api/v1/customers/$one/locations?per_page=1")->assertOk()->assertJsonPath('meta.total', 2);

        $this->patchJson("/api/v1/customers/$one", ['status' => 'inactive'])->assertOk()->assertJsonPath('data.status', 'inactive');
        $this->patchJson("/api/v1/customers/$one", ['status' => 'archived'])->assertOk();
        $this->patchJson("/api/v1/customers/$one", ['status' => 'active'])->assertConflict();
        $this->assertDatabaseHas('audit_logs', ['action' => 'customer.updated', 'resource_id' => $one]);
    }

    public function test_billing_profiles_and_role_gates_restrict_reads_and_mutations(): void
    {
        $this->loginAs('operations_admin');
        $customer = $this->customer('C-F');
        $path = "/api/v1/customers/$customer/billing-profile";
        $this->getJson($path)->assertForbidden();
        $this->postJson('/api/v1/customer-categories', ['code' => 'FORBIDDEN', 'name' => 'Forbidden'])->assertForbidden();

        $this->loginAs('finance_officer');
        $this->getJson('/api/v1/customers')->assertOk();
        $this->getJson($path)->assertOk()->assertJsonPath('data', null);
        $this->putJson($path, ['billing_name' => 'Tagihan Contoh', 'address' => 'Alamat tagihan', 'payment_term_days' => 14])
            ->assertOk()->assertJsonPath('data.payment_term_days', 14);
        $this->putJson($path, ['billing_name' => 'Tagihan Contoh', 'address' => 'Alamat tagihan', 'payment_term_days' => 21])
            ->assertOk()->assertJsonPath('data.payment_term_days', 21);
        $this->assertDatabaseCount('billing_profiles', 1);
        $this->patchJson("/api/v1/customers/$customer", ['name' => 'Dilarang'])->assertForbidden();

        $this->loginAs('driver');
        $this->getJson('/api/v1/customers')->assertForbidden();
        $this->getJson('/api/v1/contracts')->assertForbidden();
        $this->getJson($path)->assertForbidden();
    }

    public function test_contract_activation_amendment_and_history_respect_jakarta_dates(): void
    {
        $this->loginAs('operations_manager');
        $customer = $this->customer('C-K');
        $other = $this->customer('C-X');
        $location = $this->location($customer);
        $otherLocation = $this->location($other);
        $contract = $this->contract($customer, 'K-1');
        $path = "/api/v1/contracts/$contract";
        $this->postJson("$path/transitions", ['status' => 'active'])->assertUnprocessable()->assertJsonStructure(['error' => ['fields' => ['services']]]);
        $this->postJson("$path/services", ['name' => 'Layanan A', 'location_id' => $otherLocation])->assertNotFound();
        $service = $this->postJson("$path/services", ['name' => 'Layanan A', 'location_id' => $location])->assertCreated()->json('data.id');
        $rates = "$path/services/$service/rates";
        $schedule = ['service_id' => $service, 'weekday' => 2, 'slot' => '08:00', 'effective_from' => '2027-01-05'];
        $rate = ['pricing_model' => 'monthly', 'unit_rate' => '400000.125', 'effective_from' => '2027-01-05'];

        $this->loginAs('operations_admin');
        $this->postJson($rates, $rate)->assertForbidden();
        $this->postJson("$path/transitions", ['status' => 'active'])->assertForbidden();
        $scheduleId = $this->postJson("$path/pickup-schedules", $schedule)->assertCreated()->json('data.id');

        $this->loginAs('operations_manager');
        $this->postJson("$path/transitions", ['status' => 'active'])->assertUnprocessable()->assertJsonStructure(['error' => ['fields' => ['rates']]]);
        $rateId = $this->postJson($rates, $rate)->assertCreated()->assertJsonPath('data.unit_rate', '400000.125000')
            ->assertJsonPath('data.effective_until', '2027-02-01')->json('data.id');
        $this->postJson($rates, array_replace($rate, ['effective_from' => '2027-01-10']))->assertUnprocessable();
        $this->postJson("$path/pickup-schedules", $schedule)->assertUnprocessable();
        $this->getJson("$path/pickup-schedules?service_id=$service")->assertOk()->assertJsonPath('data.0.id', $scheduleId);

        $this->postJson("$path/transitions", ['status' => 'active'])->assertOk()->assertJsonPath('data.status', 'active');
        $this->patchJson("/api/v1/customers/$customer", ['status' => 'archived'])->assertConflict();
        $this->patchJson("$rates/$rateId", $rate)->assertConflict();
        $this->postJson($rates, $rate + ['replace_open' => true])->assertConflict();
        $this->postJson($rates, array_replace($rate, [
            'effective_from' => '2027-01-06', 'unit_rate' => '450000', 'replace_open' => true,
        ]))->assertCreated();
        $this->assertDatabaseHas('service_rates', ['id' => $rateId, 'unit_rate' => '400000.125000', 'effective_until' => '2027-01-06']);

        $this->postJson("$path/transitions", ['status' => 'suspended'])->assertUnprocessable();
        $this->postJson("$path/transitions", ['status' => 'suspended', 'reason' => 'Peninjauan layanan'])->assertOk();
        $history = $this->getJson("$path/history")->assertOk()->assertJsonPath('meta.total', 3);
        $this->assertContains('Peninjauan layanan', array_column($history->json('data'), 'reason'));

        $this->travelTo(CarbonImmutable::parse('2027-02-01T00:01:00+07:00'));
        $this->getJson("$path")->assertOk()->assertJsonPath('data.effective_status', 'expired')->assertJsonPath('data.status', 'suspended');
        $this->getJson('/api/v1/contracts?status=expired')->assertOk()->assertJsonPath('data.0.id', $contract);
        $this->postJson("$path/transitions", ['status' => 'active'])->assertConflict();
        $this->postJson("$path/transitions", ['status' => 'expired'])->assertOk()->assertJsonPath('data.status', 'expired');
        $this->assertDatabaseHas('audit_logs', ['action' => 'contract.status_changed', 'resource_id' => $contract]);
    }

    public function test_database_enforces_customer_scope_even_outside_the_api(): void
    {
        $this->loginAs('operations_manager');
        $customer = $this->customer('C-S');
        $other = $this->customer('C-T');
        $contract = $this->contract($customer, 'K-2');
        $otherLocation = $this->location($other);

        $this->expectException(QueryException::class);
        $this->expectExceptionCode('23503');
        DB::table('contract_services')->insert([
            'id' => Str::uuid()->toString(), 'contract_id' => $contract, 'customer_id' => $customer,
            'location_id' => $otherLocation, 'name' => 'Layanan tanpa akses',
        ]);
    }

    private function customer(string $code, ?string $category = null): string
    {
        return $this->postJson('/api/v1/customers', [
            'code' => $code, 'name' => "Pelanggan $code",
            'category_id' => $category ?? DB::table('customer_categories')->value('id'),
        ])->assertCreated()->json('data.id');
    }

    private function location(string $customer): string
    {
        return $this->postJson("/api/v1/customers/$customer/locations", [
            'name' => 'Gudang Contoh', 'address' => 'Alamat Contoh',
        ])->assertCreated()->json('data.id');
    }

    private function contract(string $customer, string $code): string
    {
        return $this->postJson('/api/v1/contracts', [
            'customer_id' => $customer, 'code' => $code, 'name' => "Kontrak $code",
            'starts_on' => '2027-01-05', 'ends_on' => '2027-01-31',
        ])->assertCreated()->json('data.id');
    }

    private function loginAs(string $code): void
    {
        $email = "$code@example.test";
        $actor = User::query()->firstOrCreate(['email' => $email], [
            'name' => $code, 'password' => self::PASSWORD, 'active' => true,
        ]);
        DB::table('user_roles')->insertOrIgnore([
            'id' => Str::uuid()->toString(), 'role_id' => Role::query()->where('code', $code)->value('id'),
            'user_id' => $actor->id,
        ]);

        Auth::forgetGuards();
        $cookie = $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => self::PASSWORD])
            ->assertOk()->getCookie('laras_session');
        $this->assertNotNull($cookie);
        Auth::forgetGuards();
        $this->withCookie('laras_session', $cookie->getValue());
    }
}
