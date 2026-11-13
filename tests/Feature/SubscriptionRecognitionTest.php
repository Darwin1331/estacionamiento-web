<?php

namespace Tests\Feature;

use App\Models\{Client, Floor, Payment, Rate, Role, Sector, Space, Subscription, Ticket, User, Vehicle, VehicleType};
use App\Services\SubscriptionRecognition;
use Database\Seeders\{PaymentMethodSeeder, RoleSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionRecognitionTest extends TestCase
{
    use RefreshDatabase;

    private array $scenario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed([RoleSeeder::class, PaymentMethodSeeder::class]);
        $this->travelTo(now()->setDate(2026, 11, 13)->setTime(10, 0));
        $user = User::factory()->create(['role_id' => Role::where('slug', 'administrador')->firstOrFail()->id, 'is_active' => true]);
        $this->actingAs($user);
        $client = Client::create(['name' => 'Abonado', 'document' => 'SUB-1', 'is_active' => true]);
        $type = VehicleType::create(['name' => 'Auto', 'is_active' => true]);
        $vehicle = Vehicle::create(['plate' => 'SUB-123', 'client_id' => $client->id, 'vehicle_type_id' => $type->id, 'is_active' => true]);
        $rate = Rate::create(['name' => 'Mensual', 'vehicle_type_id' => $type->id, 'unit' => 'month', 'amount' => '100.00', 'is_active' => true]);
        $subscription = Subscription::create(['client_id' => $client->id, 'vehicle_id' => $vehicle->id, 'rate_id' => $rate->id,
            'starts_on' => '2026-11-01', 'ends_on' => '2026-11-30', 'amount' => '100.00', 'is_active' => true]);
        $this->scenario = compact('user', 'client', 'type', 'vehicle', 'rate', 'subscription');
    }

    private function recognition(): array
    {
        return app(SubscriptionRecognition::class)->forVehicle($this->scenario['vehicle']->id, now());
    }

    public function test_date_boundaries_are_inclusive_but_future_expired_and_inactive_subscriptions_are_not_recognized(): void
    {
        $subscription = $this->scenario['subscription'];
        foreach (['2026-11-01 00:00:00' => 'active', '2026-11-30 23:59:59' => 'active',
            '2026-10-31 23:59:59' => 'none', '2026-12-01 00:00:00' => 'none'] as $date => $expected) {
            $this->travelTo(\Carbon\Carbon::parse($date, config('app.timezone')));
            $this->assertSame($expected, $this->recognition()['status']);
            $this->assertFalse($this->recognition()['coverage_applied']);
        }
        $this->travelTo(\Carbon\Carbon::parse('2026-11-13 10:00:00', config('app.timezone')));
        $subscription->update(['is_active' => false]);
        $this->assertSame('none', $this->recognition()['status']);
    }

    public function test_exact_vehicle_ownership_active_relations_and_monthly_rate_are_required(): void
    {
        $b = $this->scenario;
        $other = Vehicle::create(['plate' => 'SUB-OTHER', 'client_id' => $b['client']->id, 'vehicle_type_id' => $b['type']->id, 'is_active' => true]);
        $this->assertSame('none', app(SubscriptionRecognition::class)->forVehicle($other->id, now())['status']);
        foreach (['client', 'vehicle', 'type', 'rate'] as $key) {
            $b[$key]->update(['is_active' => false]);
            $this->assertSame('none', $this->recognition()['status'], $key);
            $b[$key]->update(['is_active' => true]);
        }
        $b['vehicle']->update(['client_id' => null]);
        $this->assertSame('none', $this->recognition()['status']);
        $b['vehicle']->update(['client_id' => $b['client']->id]);
        $b['rate']->update(['unit' => 'hour']);
        $this->assertSame('none', $this->recognition()['status']);
        $otherType = VehicleType::create(['name' => 'Moto', 'is_active' => true]);
        $b['rate']->update(['unit' => 'month', 'vehicle_type_id' => $otherType->id]);
        $this->assertSame('none', $this->recognition()['status']);
    }

    public function test_legacy_overlaps_are_reported_as_ambiguous_without_selecting_a_benefit(): void
    {
        $this->scenario['subscription']->replicate()->save();
        $this->assertSame(['status' => 'ambiguous', 'coverage_applied' => false], $this->recognition());
    }

    public function test_only_authorized_users_receive_subscription_information(): void
    {
        $url = '/entradas/opciones?plate='.$this->scenario['vehicle']->plate;
        $this->getJson($url)->assertOk()->assertJsonPath('subscription.status', 'active')
            ->assertJsonPath('subscription.id', $this->scenario['subscription']->id);
        $operator = User::factory()->create(['role_id' => Role::where('slug', 'operador')->firstOrFail()->id, 'is_active' => true]);
        $role = $operator->roles->first();
        $role->syncPermissions(['entradas.create']);
        $this->actingAs($operator->fresh())->getJson($url)->assertOk()->assertJsonPath('subscription', null);
        $role->givePermissionTo('abonados.view');
        $this->actingAs($operator->fresh())->getJson($url)->assertOk()->assertJsonPath('subscription.status', 'active');
        $role->revokePermissionTo('entradas.create');
        $this->actingAs($operator->fresh())->getJson($url)->assertForbidden();
    }

    public function test_recognition_does_not_change_charges_duplicate_protection_or_history(): void
    {
        $b = $this->scenario;
        $floor = Floor::create(['name' => 'Planta baja', 'is_active' => true]);
        $sector = Sector::create(['floor_id' => $floor->id, 'name' => 'Norte', 'is_active' => true]);
        $space = Space::create(['floor_id' => $floor->id, 'sector_id' => $sector->id, 'code' => 'A1', 'is_active' => true, 'status' => 'available']);
        $rate = Rate::create(['name' => 'Hora', 'vehicle_type_id' => $b['type']->id, 'unit' => 'hour', 'amount' => '5.25', 'is_active' => true]);
        $id = $this->postJson('/entradas', ['vehicle_id' => $b['vehicle']->id, 'space_id' => $space->id, 'rate_id' => $rate->id])->assertCreated()->json('data.id');
        $this->getJson('/salidas/'.$id.'/cotizacion')->assertOk()->assertJsonPath('subscription.status', 'active')
            ->assertJsonPath('subscription.coverage_applied', false)->assertJsonPath('quote.total', '5.25');
        $payload = ['method' => 'cash', 'expected_total' => '5.25'];
        $this->postJson('/salidas/'.$id.'/cobrar', $payload)->assertOk()->assertJsonPath('total', '5.25');
        $this->postJson('/salidas/'.$id.'/cobrar', $payload)->assertUnprocessable();
        $ticketBefore = Ticket::findOrFail($id)->getAttributes();
        $paymentBefore = Payment::sole()->getAttributes();
        $b['subscription']->update(['is_active' => false]);
        $this->assertSame('none', $this->recognition()['status']);
        $this->assertSame($ticketBefore, Ticket::findOrFail($id)->getAttributes());
        $this->assertSame($paymentBefore, Payment::sole()->getAttributes());
        $this->assertDatabaseHas('tickets', ['id' => $id, 'opened_by' => $b['user']->id, 'closed_by' => $b['user']->id, 'subscription_id' => null]);
        $this->assertDatabaseHas('payments', ['ticket_id' => $id, 'user_id' => $b['user']->id, 'amount' => '5.25']);
    }
}
