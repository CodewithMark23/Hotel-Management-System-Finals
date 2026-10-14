<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    protected $fillable = ['guest_id', 'room_id', 'check_in', 'check_out', 'status', 'total_amount', 'notes'];
    protected function casts(): array
    {
        return ['check_in' => 'date:Y-m-d', 'check_out' => 'date:Y-m-d', 'total_amount' => 'decimal:2'];
    }
    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
