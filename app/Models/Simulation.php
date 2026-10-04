<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Simulation extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'event_id',
        'scenario',
        'results',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'scenario' => 'array',
            'results' => 'array',
        ];
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
