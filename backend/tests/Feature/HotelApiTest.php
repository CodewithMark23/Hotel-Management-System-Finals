<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HotelApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_requires_authentication(): void
    {
        $this->getJson('/api/health')->assertOk();
        $this->getJson('/api/rooms')->assertUnauthorized();
    }

    public function test_login_and_logout_revoke_token(): void
    {
        User::factory()->create(['email' => 'admin@hotel.test', 'password' => 'password']);
        $this->postJson('/api/login', ['email' => 'admin@hotel.test', 'password' => 'wrong'])->assertUnprocessable();
        $token = $this->postJson('/api/login', ['email' => 'admin@hotel.test', 'password' => 'password'])
            ->assertOk()->json('token');
        $this->withToken($token)->getJson('/api/me')->assertOk()->assertJsonPath('data.email', 'admin@hotel.test');
        $this->withToken($token)->postJson('/api/logout')->assertNoContent();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_reservations_calculate_totals_and_reject_overlapping_dates(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $room = Room::create(['number' => '101', 'type' => 'standard', 'rate' => 2500, 'capacity' => 2, 'status' => 'available']);
        $guest = Guest::create(['name' => 'Maria', 'email' => 'maria@example.test']);
        $data = ['room_id' => $room->id, 'guest_id' => $guest->id, 'check_in' => '2026-11-01', 'check_out' => '2026-11-03', 'status' => 'confirmed'];
        $id = $this->postJson('/api/reservations', $data)->assertCreated()->assertJsonPath('data.total_amount', '5000.00')->json('data.id');
        $this->postJson('/api/reservations', $data)->assertUnprocessable()->assertJsonValidationErrors('room_id');
        $this->postJson('/api/reservations', [...$data, 'check_in' => '2026-11-03', 'check_out' => '2026-11-04'])->assertCreated();
        $this->postJson('/api/reservations', [...$data, 'check_out' => '2026-11-01'])->assertUnprocessable()->assertJsonValidationErrors('check_out');
        $room->update(['rate' => 3000]);
        $this->putJson('/api/reservations/'.$id, $data)->assertOk()->assertJsonPath('data.total_amount', '5000.00');
        $this->deleteJson('/api/rooms/'.$room->id)->assertConflict();
        $this->deleteJson('/api/guests/'.$guest->id)->assertConflict();
    }

    public function test_cancelled_booking_releases_dates_and_maintenance_blocks_booking(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $room = Room::create(['number' => '101', 'type' => 'suite', 'rate' => 5000, 'capacity' => 4, 'status' => 'available']);
        $guest = Guest::create(['name' => 'Maria', 'email' => 'maria@example.test']);
        $data = ['room_id' => $room->id, 'guest_id' => $guest->id, 'check_in' => '2026-11-01', 'check_out' => '2026-11-02', 'status' => 'cancelled'];
        $this->postJson('/api/reservations', $data)->assertCreated();
        $this->postJson('/api/reservations', [...$data, 'status' => 'confirmed'])->assertCreated();
        $room->update(['status' => 'maintenance']);
        $this->postJson('/api/reservations', [...$data, 'check_in' => '2026-11-05', 'check_out' => '2026-11-06', 'status' => 'confirmed'])->assertUnprocessable()->assertJsonValidationErrors('room_id');
    }

    public function test_rooms_and_guests_can_be_managed(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $room = ['number' => '401', 'type' => 'deluxe', 'rate' => 4000, 'capacity' => 2, 'status' => 'available'];
        $id = $this->postJson('/api/rooms', $room)->assertCreated()->json('data.id');
        $this->postJson('/api/rooms', $room)->assertUnprocessable();
        $this->putJson('/api/rooms/'.$id, [...$room, 'status' => 'maintenance'])->assertOk();
        $this->getJson('/api/rooms/'.$id)->assertOk();
        $this->deleteJson('/api/rooms/'.$id)->assertNoContent();
        $id = $this->postJson('/api/guests', ['name' => 'Alex', 'email' => 'alex@example.test'])->assertCreated()->json('data.id');
        $this->putJson('/api/guests/'.$id, ['name' => 'Alex Santos', 'email' => 'alex@example.test'])->assertOk();
        $this->getJson('/api/guests/'.$id)->assertOk();
        $this->deleteJson('/api/guests/'.$id)->assertNoContent();
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('data.rooms', 0);
    }
}
