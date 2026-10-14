<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    protected $fillable = ['number', 'type', 'rate', 'capacity', 'status'];
    protected function casts(): array
    {
        return ['rate' => 'decimal:2', 'capacity' => 'integer'];
    }
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }
}
