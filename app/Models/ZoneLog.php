<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZoneLog extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'zone_id',
        'count',
        'logged_at',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'logged_at' => 'datetime',
            'count' => 'integer',
        ];
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }
}
