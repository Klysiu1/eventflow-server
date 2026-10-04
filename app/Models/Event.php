<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'venue',
        'start_at',
        'end_at',
        'max_capacity',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'max_capacity' => 'integer',
        ];
    }

    public function zones()
    {
        return $this->hasMany(Zone::class)->orderBy('name');
    }

    public function simulations()
    {
        return $this->hasMany(Simulation::class)->orderBy('created_at', 'desc');
    }

    public function alerts()
    {
        return $this->hasManyThrough(Alert::class, Zone::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
