<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Guest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GuestController extends Controller
{
    public function index()
    {
        return response()->json(['data' => Guest::orderBy('name')->get()]);
    }
    public function show(Guest $guest)
    {
        return response()->json(['data' => $guest]);
    }
    public function store(Request $request)
    {
        return response()->json(['data' => Guest::create($this->validated($request))], 201);
    }
    public function update(Request $request, Guest $guest)
    {
        $guest->update($this->validated($request, $guest));
        return response()->json(['data' => $guest]);
    }
    public function destroy(Guest $guest)
    {
        if ($guest->reservations()->exists()) {
            return response()->json(['message' => 'Guests with reservation history cannot be deleted.'], 409);
        }
        $guest->delete();
        return response()->noContent();
    }
    private function validated(Request $request, ?Guest $guest = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('guests')->ignore($guest)],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);
    }
}
