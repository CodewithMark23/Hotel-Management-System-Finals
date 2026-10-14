<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoomController extends Controller
{
    public function index()
    {
        return response()->json(['data' => Room::orderBy('number')->get()]);
    }
    public function show(Room $room)
    {
        return response()->json(['data' => $room->load('reservations.guest')]);
    }
    public function store(Request $request)
    {
        return response()->json(['data' => Room::create($this->validated($request))], 201);
    }
    public function update(Request $request, Room $room)
    {
        $room->update($this->validated($request, $room));
        return response()->json(['data' => $room]);
    }
    public function destroy(Room $room)
    {
        if ($room->reservations()->exists()) {
            return response()->json(['message' => 'Rooms with reservation history cannot be deleted.'], 409);
        }
        $room->delete();
        return response()->noContent();
    }
    private function validated(Request $request, ?Room $room = null): array
    {
        return $request->validate([
            'number' => ['required', 'string', 'max:20', Rule::unique('rooms')->ignore($room)],
            'type' => ['required', Rule::in(['standard', 'deluxe', 'suite'])],
            'rate' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'capacity' => ['required', 'integer', 'min:1', 'max:20'],
            'status' => ['required', Rule::in(['available', 'maintenance'])],
        ]);
    }
}
