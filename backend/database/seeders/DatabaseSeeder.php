<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Room;
use App\Models\Guest;
use App\Models\Reservation;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }
        User::firstOrCreate(['email' => 'admin@hotel.test'], ['name' => 'Hotel Admin', 'password' => 'password']);
        foreach (['101', '102', '201', '202', '301', '302'] as $index => $number) {
            Room::firstOrCreate(['number' => $number], [
                'type' => ['standard', 'deluxe', 'suite'][intdiv($index, 2)],
                'rate' => [2500, 4000, 6500][intdiv($index, 2)],
                'capacity' => $index < 4 ? 2 : 4,
                'status' => 'available',
            ]);
        }
        $guest = Guest::firstOrCreate(['email' => 'maria@example.test'], ['name' => 'Maria Santos', 'phone' => '0917 555 0101']);
        Reservation::firstOrCreate(['guest_id' => $guest->id, 'room_id' => Room::where('number', '101')->firstOrFail()->id], [
            'check_in' => today()->toDateString(),
            'check_out' => today()->addDays(2)->toDateString(),
            'status' => 'confirmed',
            'total_amount' => 5000,
            'notes' => 'Demo reservation',
        ]);
    }
}
