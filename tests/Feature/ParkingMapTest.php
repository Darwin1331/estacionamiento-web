<?php

namespace Tests\Feature;

use App\Models\{Floor, Rate, Role, Sector, Space, Ticket, User, Vehicle, VehicleType};
use Database\Seeders\{PaymentMethodSeeder, RoleSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParkingMapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed([RoleSeeder::class, PaymentMethodSeeder::class]);
    }

    private function user(string $role = 'administrador'): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', $role)->firstOrFail()->id, 'is_active' => true]);
    }

    private function space(array $attributes = []): Space
    {
        $floor = Floor::create(['name' => 'Piso '.fake()->uuid(), 'is_active' => true]);
        $sector = Sector::create(['name' => 'Norte', 'floor_id' => $floor->id, 'is_active' => true]);

        return Space::create(array_replace(['code' => 'A-1', 'floor_id' => $floor->id,
            'sector_id' => $sector->id, 'status' => 'available', 'is_active' => true], $attributes));
    }

    private function ticket(Space $space, User $user): Ticket
    {
        $type = VehicleType::create(['name' => fake()->uuid(), 'is_active' => true]);
        $vehicle = Vehicle::create(['plate' => 'PLATE-'.fake()->unique()->numberBetween(1, 999999), 'vehicle_type_id' => $type->id, 'is_active' => true]);
        $rate = Rate::create(['name' => 'Hora', 'vehicle_type_id' => $type->id, 'unit' => 'hour', 'amount' => '5.00', 'is_active' => true]);

        return Ticket::create(['number' => fake()->uuid(), 'vehicle_id' => $vehicle->id, 'space_id' => $space->id,
            'rate_id' => $rate->id, 'opened_by' => $user->id, 'entered_at' => now(), 'rate_amount' => '5.00', 'total' => '0.00', 'status' => 'open']);
    }

    public function test_both_routes_require_authentication_active_account_and_space_permission(): void
    {
        $this->get('/espacios/mapa')->assertRedirect('/login');
        $this->getJson('/espacios/mapa/datos')->assertUnauthorized();
        $user = $this->user('operador');
        $role = $user->roles->first();
        $role->syncPermissions(['vehiculos.view']);
        $this->actingAs($user->fresh());
        $this->get('/espacios/mapa')->assertForbidden();
        $this->getJson('/espacios/mapa/datos')->assertForbidden();
        $role->givePermissionTo('espacios.view');
        $this->actingAs($user->fresh());
        $this->get('/espacios/mapa')->assertOk()->assertSee('parking-map')->assertSee('nav-gestion');
        $this->getJson('/espacios/mapa/datos')->assertOk();
        $role->revokePermissionTo('espacios.view');
        $this->actingAs($user->fresh())->getJson('/espacios/mapa/datos')->assertForbidden();
        $user->update(['is_active' => false]);
        $this->actingAs($user->fresh())->get('/espacios/mapa')->assertRedirect('/login');
    }

    public function test_empty_map_has_zero_counts_and_never_divides_by_zero(): void
    {
        $this->actingAs($this->user())->getJson('/espacios/mapa/datos')->assertOk()
            ->assertJsonCount(0, 'data')->assertJsonPath('stats.total', 0)
            ->assertJsonPath('stats.occupancy_percent', 0)->assertHeader('Cache-Control', 'no-store, private');
        $this->space(['is_active' => false]);
        $this->getJson('/espacios/mapa/datos')->assertJsonPath('stats.inactive', 1)->assertJsonPath('stats.occupancy_percent', 0);
    }

    public function test_occupancy_uses_open_tickets_and_handles_inactive_locations_and_legacy_inconsistencies(): void
    {
        $user = $this->user();
        $available = $this->space();
        $occupied = $this->space(['status' => 'occupied']);
        $ticket = $this->ticket($occupied, $user);
        $maintenance = $this->space(['status' => 'maintenance']);
        $inactive = $this->space(['is_active' => false]);
        $floorInactive = $this->space();
        $floorInactive->floor->update(['is_active' => false]);
        $sectorInactive = $this->space();
        $sectorInactive->sector->update(['is_active' => false]);
        $wrongFloor = $this->space();
        $wrongFloor->sector->update(['floor_id' => $available->floor_id, 'name' => 'Sector inconsistente']);
        $staleOccupied = $this->space(['status' => 'occupied']);
        $staleAvailable = $this->space();
        $this->ticket($staleAvailable, $user);
        // Closed history does not occupy the available space.
        $this->ticket($available, $user)->update(['status' => 'closed', 'exited_at' => now()]);

        $result = $this->actingAs($user)->getJson('/espacios/mapa/datos')->assertOk()
            ->assertJsonPath('stats.total', 9)->assertJsonPath('stats.available', 1)
            ->assertJsonPath('stats.occupied', 2)->assertJsonPath('stats.inactive', 6)
            ->assertJsonPath('stats.occupancy_percent', 66.7);
        $rows = collect($result->json('data'))->keyBy('id');
        $this->assertSame('available', $rows[$available->id]['status']);
        $this->assertSame([$ticket->vehicle->plate], $rows[$occupied->id]['plates']);
        $this->assertSame($occupied->floor_id, $rows[$occupied->id]['floor']['id']);
        $this->assertSame($occupied->sector_id, $rows[$occupied->id]['sector']['id']);
        $this->assertNotNull($rows[$staleOccupied->id]['note']);
        $this->assertNotNull($rows[$staleAvailable->id]['note']);
        $this->assertSame('available', $staleAvailable->fresh()->status); // Read-only endpoint.
    }

    public function test_duplicate_open_tickets_count_one_space_and_inactive_occupied_spaces_stay_visible(): void
    {
        $user = $this->user();
        $space = $this->space(['is_active' => false]);
        $this->ticket($space, $user);
        $this->ticket($space, $user);
        $this->actingAs($user)->getJson('/espacios/mapa/datos')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonCount(2, 'data.0.plates')
            ->assertJsonPath('stats.occupied', 1)->assertJsonPath('stats.occupancy_percent', 100)
            ->assertJsonPath('data.0.note', 'Revisar: varios tickets abiertos.');
    }

    public function test_plates_are_not_exposed_to_space_only_users_and_revocation_takes_effect(): void
    {
        $space = $this->space(['status' => 'occupied']);
        $ticket = $this->ticket($space, $this->user());
        $user = $this->user('supervisor');
        $role = $user->roles->first();
        $role->syncPermissions(['espacios.view']);
        $this->actingAs($user->fresh())->getJson('/espacios/mapa/datos')->assertOk()
            ->assertJsonPath('data.0.plates', [])->assertDontSee($ticket->vehicle->plate);
        foreach (['vehiculos.view', 'tickets.view'] as $permission) {
            $role->syncPermissions(['espacios.view', $permission]);
            $this->actingAs($user->fresh())->getJson('/espacios/mapa/datos')
                ->assertJsonPath('data.0.plates.0', $ticket->vehicle->plate);
        }
        $role->syncPermissions(['espacios.view']);
        $this->actingAs($user->fresh())->getJson('/espacios/mapa/datos')->assertJsonPath('data.0.plates', []);
    }

    public function test_checkout_is_reflected_without_a_separate_occupancy_record(): void
    {
        $user = $this->user();
        $space = $this->space(['status' => 'occupied']);
        $ticket = $this->ticket($space, $user);
        $this->actingAs($user)->getJson('/espacios/mapa/datos')->assertJsonPath('stats.occupied', 1);
        $this->postJson('/salidas/'.$ticket->id.'/cobrar', ['method' => 'cash', 'expected_total' => '5.00'])->assertOk();
        $this->getJson('/espacios/mapa/datos')->assertJsonPath('stats.occupied', 0)
            ->assertJsonPath('stats.available', 1)->assertJsonPath('data.0.plates', []);
    }
}
