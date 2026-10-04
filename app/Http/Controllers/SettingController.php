<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SettingController extends Controller
{
    protected array $defaults = [
        'profile' => [
            'name' => 'Organizator Główny',
            'email' => 'organizator@eventflow.pl',
            'organization' => 'EventFlow Operations',
        ],
        'notifications' => [
            'critical' => true,
            'warning' => true,
            'sound' => false,
            'browser' => false,
        ],
        'thresholds' => [
            'warning' => 70,
            'critical' => 90,
        ],
    ];

    public function getSettings(Request $request)
    {
        $user = $request->user();
        $record = $user ? $user->settings : Setting::first();

        if (!$record) {
            return response()->json([
                'version' => 1,
                'savedAt' => now()->toISOString(),
                'settings' => $this->defaults,
                'warning' => null,
            ]);
        }

        return response()->json([
            'version' => 1,
            'savedAt' => $record->updated_at?->toISOString() ?? now()->toISOString(),
            'settings' => [
                'profile' => array_merge($this->defaults['profile'], $record->profile ?? []),
                'notifications' => array_merge($this->defaults['notifications'], $record->notifications ?? []),
                'thresholds' => array_merge($this->defaults['thresholds'], $record->thresholds ?? []),
            ],
            'warning' => null,
        ]);
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'profile.name' => 'required|string|max:80',
            'profile.email' => 'required|email|max:254',
            'profile.organization' => 'nullable|string|max:120',
            'notifications.critical' => 'required|boolean',
            'notifications.warning' => 'required|boolean',
            'notifications.sound' => 'required|boolean',
            'notifications.browser' => 'required|boolean',
            'thresholds.warning' => 'required|integer|min:1|max:99',
            'thresholds.critical' => 'required|integer|min:2|max:100',
        ]);

        $warning = (int) $validated['thresholds']['warning'];
        $critical = (int) $validated['thresholds']['critical'];
        if ($warning >= $critical) {
            throw ValidationException::withMessages([
                'thresholds.critical' => ['Próg krytyczny musi być wyższy od progu ostrzegania.'],
            ]);
        }

        $user = $request->user();
        $record = $user ? $user->settings : Setting::first();

        if (!$record) {
            $record = new Setting();
            if ($user) {
                $record->user_id = $user->id;
            }
        }

        $record->profile = [
            'name' => trim($validated['profile']['name']),
            'email' => trim($validated['profile']['email']),
            'organization' => trim($validated['profile']['organization'] ?? ''),
        ];
        $record->notifications = $validated['notifications'];
        $record->thresholds = [
            'warning' => $warning,
            'critical' => $critical,
        ];
        $record->save();

        return response()->json([
            'version' => 1,
            'savedAt' => $record->updated_at->toISOString(),
            'settings' => [
                'profile' => $record->profile,
                'notifications' => $record->notifications,
                'thresholds' => $record->thresholds,
            ],
            'warning' => null,
        ]);
    }
}
