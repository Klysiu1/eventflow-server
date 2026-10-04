<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'profile',
        'notifications',
        'thresholds',
    ];

    protected function casts(): array
    {
        return [
            'profile' => 'array',
            'notifications' => 'array',
            'thresholds' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
