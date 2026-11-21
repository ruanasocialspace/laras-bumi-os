<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\IdentityAccess\Role;
use App\Modules\IdentityAccess\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CollectionTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'phase-three-test-password';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2027-01-05T00:30:00+07:00'));
        $this->seed(DatabaseSeeder::class);
        $this->withHeader('Origin', 'http://localhost:8080');
    }

    public function test_jakarta_scheduler_generates_only_eligible_recurring_tasks_once(): void
    {
        $this->getJson('/api/v1/pickup-tasks')->assertUnauthorized();
        $operator = $this->actor('operations_manager');
        $this->login($operator);
        $active = $this->scheduledContract('ACT', 'active');
        $this->scheduledContract('SUS', 'suspended');
        $this->scheduledContract('EXP', 'active', '2027-01-04');
        $this->scheduledContract('FUT', 'active', null, '2027-01-12');
        $this->scheduledContract('NOR', 'active', withRate: false);

        $this->artisan('collections:generate')->assertExitCode(0);
        $this->postJson('/api/v1/pickup-tasks/generate', ['service_date' => '2027-01-05'])
            ->assertOk()->assertJsonPath('data.created', 0);
        $this->getJson('/api/v1/pickup-tasks')->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.schedule_id', $active)
            ->assertJsonPath('data.0.status', 'scheduled');
        $this->getJson('/api/v1/pickup-tasks?assigned=1')->assertForbidden();
        $this->assertDatabaseHas('pickup_tasks', ['schedule_id' => $active, 'service_date' => '2027-01-05']);
        DB::table('contracts')->where('code', 'K-ACT')->update(['status' => 'suspended']);
        $this->getJson('/api/v1/pickup-tasks')->assertOk()->assertJsonPath('data.0.contract_status', 'suspended');
        $this->postJson('/api/v1/pickup-tasks/generate', ['service_date' => '2027-01-04'])->assertUnprocessable();
        $this->assertDatabaseCount('pickup_tasks', 1);
        $this->assertDatabaseCount('pickup_events', 1);
    }

    public function test_driver_completes_with_private_evidence_and_idempotent_replay(): void
    {
        Storage::fake('local');
        $office = $this->actor('operations_manager');
        $driver = $this->actor('driver');
        $other = $this->actor('driver');
        [$customer, $location] = $this->location('PHOTO');
        $this->login($office);
        $task = $this->manual($customer, $location);
        $vehicle = $this->vehicle();
        $path = "/api/v1/pickup-tasks/$task";

        $this->assign($task, $driver, $vehicle, 0);
        $this->patchJson("/api/v1/vehicles/$vehicle", ['active' => false])->assertConflict();
        $this->patchJson("/api/v1/customers/$customer", ['status' => 'archived'])->assertConflict();

        $this->login($other);
        $this->getJson('/api/v1/pickup-tasks')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson($path)->assertNotFound();
        $this->postJson("$path/events", $this->event('en_route', 1))->assertNotFound();

        $this->login($driver);
        $this->getJson('/api/v1/pickup-tasks?assigned=1')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/pickup-tasks?assigned=1', ['X-Driver-Actor' => $other->id])->assertForbidden();
        $depart = $this->event('en_route', 1);
        $this->postJson("$path/events", $depart, ['X-Driver-Actor' => $other->id])->assertForbidden();
        $this->postJson("$path/events", $depart)->assertOk()->assertJsonPath('data.revision', 2);
        $this->postJson("$path/events", $depart)->assertOk()->assertJsonPath('data.event_id', $depart['client_event_id']);
        $this->postJson("$path/events", array_replace($depart, ['expected_revision' => 2]))->assertConflict();
        $this->postJson("$path/events", $this->event('arrived', 2))->assertOk()->assertJsonPath('data.revision', 3);
        $this->postJson("$path/events", $this->event('completed', 3))->assertUnprocessable();

        $fileId = Str::uuid()->toString();
        $photo = UploadedFile::fake()->image('evidence.png', 48, 32);
        $this->post("$path/evidence", ['client_file_id' => $fileId, 'photo' => $photo])
            ->assertCreated()->assertJsonPath('data.id', $fileId);
        $this->post("$path/evidence", ['client_file_id' => $fileId, 'photo' => $photo])->assertCreated();

        $completion = $this->event('completed', 3) + [
            'latitude' => -8.214, 'longitude' => 113.643, 'accuracy_m' => 9.2,
            'weight_kg' => '12.500', 'weight_basis' => 'estimated', 'evidence_ids' => [$fileId],
        ];
        $this->postJson("$path/events", array_replace($completion, ['evidence_ids' => [Str::uuid()->toString()]]))->assertUnprocessable();
        $this->assertDatabaseMissing('pickup_events', ['id' => $completion['client_event_id']]);
        $this->postJson("$path/events", $completion)->assertOk()->assertJsonPath('data.status', 'completed');
        $this->postJson("$path/events", $completion)->assertOk()->assertJsonPath('data.revision', 4);
        $this->post("$path/evidence", ['client_file_id' => $fileId, 'photo' => $photo])->assertCreated();
        $this->getJson('/api/v1/pickup-tasks?history=1')->assertOk()->assertJsonPath('data.0.status', 'completed');

        $this->assertDatabaseCount('pickup_events', 5);
        $this->assertDatabaseCount('pickup_evidence', 1);
        $this->assertDatabaseHas('pickup_evidence', ['id' => $fileId, 'event_id' => $completion['client_event_id']]);
        $this->assertDatabaseHas('pickup_waste_entries', ['weight_kg' => '12.500', 'weight_basis' => 'estimated']);
        Storage::disk('local')->assertExists("pickup/$task/$fileId.jpg");

        $this->login($other);
        $this->get("$path/evidence/$fileId/download")->assertNotFound();
        $this->login($office);
        $this->get("$path/evidence/$fileId/download")->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->getJson("/api/v1/customers/$customer/pickup-history")->assertOk()->assertJsonPath('data.0.id', $task);
        $this->assertDatabaseHas('audit_logs', ['action' => 'collection.driver_event', 'resource_id' => $task]);
    }

    public function test_reassignment_rejects_stale_events_and_failure_requires_a_reason(): void
    {
        $office = $this->actor('operations_admin');
        $first = $this->actor('driver');
        $second = $this->actor('driver');
        [$customer, $location] = $this->location('REASSIGN');
        $this->login($office);
        $task = $this->manual($customer, $location);
        $vehicle = $this->vehicle();
        $path = "/api/v1/pickup-tasks/$task";
        $this->assign($task, $first, $vehicle, 0);
        $this->assign($task, $second, $vehicle, 1);

        $this->login($first);
        $this->postJson("$path/events", $this->event('en_route', 1))->assertNotFound();
        $this->login($second);
        $this->postJson("$path/events", $this->event('en_route', 1))
            ->assertConflict()->assertJsonPath('error.current_revision', 2);
        $this->postJson("$path/events", $this->event('failed', 2))->assertUnprocessable();
        $this->postJson("$path/events", $this->event('failed', 2) + ['reason' => 'Lokasi tertutup'])
            ->assertOk()->assertJsonPath('data.status', 'failed');
        $this->assertDatabaseHas('pickup_events', ['task_id' => $task, 'reason' => 'Lokasi tertutup']);
    }

    private function scheduledContract(
        string $code,
        string $status,
        ?string $end = null,
        string $start = '2027-01-01',
        bool $withRate = true,
    ): string {
        [$customer, $location] = $this->location($code);
        $contract = Str::uuid()->toString();
        $service = Str::uuid()->toString();
        $schedule = Str::uuid()->toString();
        DB::table('contracts')->insert([
            'id' => $contract, 'customer_id' => $customer, 'code' => 'K-'.$code, 'name' => 'Kontrak '.$code,
            'starts_on' => $start, 'ends_on' => $end, 'status' => $status,
        ]);
        DB::table('contract_services')->insert([
            'id' => $service, 'contract_id' => $contract, 'customer_id' => $customer, 'location_id' => $location, 'name' => 'Penjemputan',
        ]);
        DB::table('pickup_schedules')->insert([
            'id' => $schedule, 'series_id' => Str::uuid()->toString(), 'service_id' => $service,
            'weekday' => 2, 'slot' => '08:00', 'effective_from' => $start,
        ]);
        if ($withRate) {
            DB::table('service_rates')->insert([
                'id' => Str::uuid()->toString(), 'service_id' => $service,
                'pricing_model' => 'monthly', 'unit_rate' => 100, 'effective_from' => $start,
            ]);
        }

        return $schedule;
    }

    private function location(string $code): array
    {
        $customer = Str::uuid()->toString();
        $location = Str::uuid()->toString();
        DB::table('customers')->insert([
            'id' => $customer, 'code' => 'C-'.$code, 'name' => 'Pelanggan '.$code,
            'category_id' => DB::table('customer_categories')->value('id'),
        ]);
        DB::table('pickup_locations')->insert([
            'id' => $location, 'customer_id' => $customer, 'name' => 'Gudang '.$code, 'address' => 'Alamat Uji',
        ]);

        return [$customer, $location];
    }

    private function manual(string $customer, string $location): string
    {
        $id = Str::uuid()->toString();
        $input = [
            'client_task_id' => $id, 'customer_id' => $customer, 'location_id' => $location,
            'service_date' => '2027-01-05', 'slot' => '08:00',
        ];
        $this->postJson('/api/v1/pickup-tasks', $input)->assertCreated()->assertJsonPath('data.id', $id);
        $this->postJson('/api/v1/pickup-tasks', $input)->assertCreated()->assertJsonPath('data.id', $id);

        return $id;
    }

    private function vehicle(): string
    {
        return $this->postJson('/api/v1/vehicles', ['registration' => 'p 1000 xx', 'name' => 'Kendaraan uji'])
            ->assertCreated()->assertJsonPath('data.registration', 'P 1000 XX')->json('data.id');
    }

    private function assign(string $task, User $driver, string $vehicle, int $revision): void
    {
        $this->postJson("/api/v1/pickup-tasks/$task/assignments", [
            'driver_id' => $driver->id, 'vehicle_id' => $vehicle, 'expected_revision' => $revision,
        ])->assertOk()->assertJsonPath('data.revision', $revision + 1);
    }

    private function event(string $type, int $revision): array
    {
        return [
            'client_event_id' => Str::uuid()->toString(), 'type' => $type, 'expected_revision' => $revision,
            'captured_at' => now()->utc()->format('Y-m-d\TH:i:s.v\Z'),
        ];
    }

    private function actor(string $role): User
    {
        $actor = User::query()->create([
            'email' => Str::uuid()->toString().'@example.test', 'name' => $role,
            'password' => self::PASSWORD, 'active' => true,
        ]);
        DB::table('user_roles')->insert([
            'id' => Str::uuid()->toString(), 'user_id' => $actor->id,
            'role_id' => Role::query()->where('code', $role)->value('id'),
        ]);

        return $actor;
    }

    private function login(User $actor): void
    {
        Auth::forgetGuards();
        $cookie = $this->postJson('/api/v1/auth/login', ['email' => $actor->email, 'password' => self::PASSWORD])
            ->assertOk()->getCookie('laras_session');
        $this->assertNotNull($cookie);
        Auth::forgetGuards();
        $this->withCookie('laras_session', $cookie->getValue());
        $this->withHeader('X-Driver-Actor', $actor->id);
    }
}
