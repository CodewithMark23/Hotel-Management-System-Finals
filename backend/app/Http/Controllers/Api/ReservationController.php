<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReservationController extends Controller
{
    public function index()
    {
        return response()->json(['data' => Reservation::with(['guest', 'room'])->orderByDesc('check_in')->get()]);
    }
    public function show(Reservation $reservation)
    {
        return response()->json(['data' => $reservation->load(['guest', 'room'])]);
    }
    public function store(Request $request)
    {
        return response()->json(['data' => $this->save($request)], 201);
    }
    public function update(Request $request, Reservation $reservation)
    {
        return response()->json(['data' => $this->save($request, $reservation)]);
    }
    public function destroy(Reservation $reservation)
    {
        $reservation->delete();
        return response()->noContent();
    }
    private function save(Request $request, ?Reservation $reservation = null): Reservation
    {
        $data = $request->validate([
            'guest_id' => ['required', 'integer', 'exists:guests,id'],
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'check_in' => ['required', 'date_format:Y-m-d'],
            'check_out' => ['required', 'date_format:Y-m-d', 'after:check_in'],
            'status' => ['required', Rule::in(['confirmed', 'checked_in', 'checked_out', 'cancelled'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        return DB::transaction(function () use ($data, $reservation) {
            $room = Room::lockForUpdate()->findOrFail($data['room_id']);
            if (in_array($data['status'], ['confirmed', 'checked_in'])) {
                if ($room->status === 'maintenance') {
                    throw ValidationException::withMessages(['room_id' => ['This room is under maintenance.']]);
                }
                $overlap = Reservation::where('room_id', $room->id)
                    ->whereIn('status', ['confirmed', 'checked_in'])
                    ->when($reservation, fn ($query) => $query->where('id', '!=', $reservation->id))
                    ->where('check_in', '<', $data['check_out'])
                    ->where('check_out', '>', $data['check_in'])->exists();
                if ($overlap) {
                    throw ValidationException::withMessages(['room_id' => ['This room is already booked for these dates.']]);
                }
            }
            $nights = (int) Carbon::parse($data['check_in'])->diffInDays(Carbon::parse($data['check_out']));
            // Retain the booked nightly rate when editing the same room.
            $rate = $reservation && $reservation->room_id === $room->id
                ? (float) $reservation->total_amount / max(1, (int) $reservation->check_in->diffInDays($reservation->check_out))
                : (float) $room->rate;
            $data['total_amount'] = round($nights * $rate, 2);
            $reservation ??= new Reservation;
            $reservation->fill($data)->save();
            return $reservation->load(['guest', 'room']);
        });
    }
}
