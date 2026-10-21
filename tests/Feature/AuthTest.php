<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    // ---------- register ----------

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token'])
            ->assertJsonMissingPath('user.password');

        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }

    public function test_register_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'test@example.com']);

        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_register_fails_with_short_password(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'short',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    public function test_register_ignores_role_in_request(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'role' => 'admin',
        ])->assertCreated();

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'role' => 'member',
        ]);
    }

    // ---------- login ----------

    public function test_user_can_login_with_correct_credentials(): void
    {
        $user = User::factory()->create(['email' => 'user@example.com']);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk()->assertJsonStructure(['user', 'token']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertUnauthorized()->assertJson(['message' => 'Invalid credentials.']);
    }

    public function test_login_fails_with_unknown_email(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'nobody@example.com',
            'password' => 'password',
        ]);

        $response->assertUnauthorized()->assertJson(['message' => 'Invalid credentials.']);
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->postJson('/api/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    // ---------- user ----------

    public function test_user_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_authenticated_user_can_get_own_profile(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonMissingPath('user.password');
    }

    // ---------- logout ----------

    public function test_user_can_logout_and_token_is_deleted(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/logout')
            ->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
