<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_successfully(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Kamil Testowy',
            'email' => 'kamil@example.com',
            'password' => 'secret12345',
            'role' => 'organizer',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'role'],
                'token',
            ])
            ->assertJsonPath('user.email', 'kamil@example.com')
            ->assertJsonPath('user.name', 'Kamil Testowy');

        $this->assertDatabaseHas('users', [
            'email' => 'kamil@example.com',
            'role' => 'organizer',
        ]);

        $this->assertDatabaseHas('settings', [
            'user_id' => $response->json('user.id'),
        ]);
    }

    public function test_registration_fails_on_duplicate_email(): void
    {
        User::create([
            'name' => 'Existing',
            'email' => 'existing@example.com',
            'password_hash' => Hash::make('password123'),
            'role' => 'organizer',
        ]);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Second',
            'email' => 'existing@example.com',
            'password' => 'secret12345',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_login_with_correct_credentials(): void
    {
        $user = User::create([
            'name' => 'Jan Kowalski',
            'email' => 'jan@example.com',
            'password_hash' => Hash::make('mypassword123'),
            'role' => 'organizer',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'jan@example.com',
            'password' => 'mypassword123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'role'],
                'token',
            ])
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_login_fails_with_invalid_password(): void
    {
        User::create([
            'name' => 'Jan Kowalski',
            'email' => 'jan@example.com',
            'password_hash' => Hash::make('mypassword123'),
            'role' => 'organizer',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'jan@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_authenticated_user_can_get_profile_and_logout(): void
    {
        $user = User::create([
            'name' => 'Jan Kowalski',
            'email' => 'jan@example.com',
            'password_hash' => Hash::make('mypassword123'),
            'role' => 'organizer',
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $meResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me');

        $meResponse->assertStatus(200)
            ->assertJsonPath('email', 'jan@example.com');

        $logoutResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout');

        $logoutResponse->assertStatus(200)
            ->assertJson(['message' => 'Wylogowano pomyślnie.']);
    }
}
