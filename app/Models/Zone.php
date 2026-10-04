<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Zone extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'event_id',
        'name',
        'type',
        'capacity',
        'current_count',
        'alert_threshold',
        'coordinates',
        'area',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'current_count' => 'integer',
            'alert_threshold' => 'float',
            'coordinates' => 'array',
            'area' => 'array',
        ];
    }

    protected function alertThreshold(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value === null ? 90 : ($value <= 1.0 ? (int) round($value * 100) : (int) $value),
            set: fn ($value) => $value !== null && $value > 1.0 ? $value / 100.0 : $value
        );
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function logs()
    {
        return $this->hasMany(ZoneLog::class)->orderBy('logged_at', 'desc');
    }

    public function alerts()
    {
        return $this->hasMany(Alert::class)->orderBy('triggered_at', 'desc');
    }
}
