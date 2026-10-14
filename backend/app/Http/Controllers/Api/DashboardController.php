<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $today = today()->toDateString();
        return response()->json(['data' => [
            'rooms' => Room::count(),
            'guests' => Guest::count(),
            'active_reservations' => Reservation::whereIn('status', ['confirmed', 'checked_in'])->count(),
            'occupied_rooms' => Reservation::where('status', 'checked_in')->where('check_in', '<=', $today)->where('check_out', '>', $today)->distinct()->count('room_id'),
            'arrivals_today' => Reservation::where('check_in', $today)->whereIn('status', ['confirmed', 'checked_in'])->count(),
        ]]);
    }
}
