<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_fetch_default_settings(): void
    {
        $response = $this->getJson('/api/v1/settings');

        $response->assertStatus(200)
            ->assertJsonPath('version', 1)
            ->assertJsonStructure([
                'version',
                'savedAt',
                'settings' => [
                    'profile' => ['name', 'email', 'organization'],
                    'notifications' => ['critical', 'warning', 'sound', 'browser'],
                    'thresholds' => ['warning', 'critical'],
                ],
            ]);
    }

    public function test_can_update_settings(): void
    {
        $response = $this->putJson('/api/v1/settings', [
            'profile' => [
                'name' => 'Jan Kowalski',
                'email' => 'jan@kowalski.pl',
                'organization' => 'Event Agency Sp. z o.o.',
            ],
            'notifications' => [
                'critical' => true,
                'warning' => false,
                'sound' => true,
                'browser' => true,
            ],
            'thresholds' => [
                'warning' => 65,
                'critical' => 85,
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('settings.profile.name', 'Jan Kowalski')
            ->assertJsonPath('settings.thresholds.warning', 65)
            ->assertJsonPath('settings.thresholds.critical', 85);

        $this->assertDatabaseHas('settings', [
            'id' => Setting::first()->id,
        ]);
    }

    public function test_settings_validation_rejects_invalid_thresholds(): void
    {
        $response = $this->putJson('/api/v1/settings', [
            'profile' => [
                'name' => 'Jan Kowalski',
                'email' => 'jan@kowalski.pl',
                'organization' => 'Event Agency',
            ],
            'notifications' => [
                'critical' => true,
                'warning' => true,
                'sound' => false,
                'browser' => false,
            ],
            'thresholds' => [
                'warning' => 90,
                'critical' => 70, // warning >= critical is invalid!
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['thresholds.critical']);
    }
}
