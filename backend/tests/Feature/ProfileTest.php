<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------------------
    // GET /api/v1/profile
    // ---------------------------------------------------------------------------

    public function test_unauthenticated_user_gets_401_on_get_profile(): void
    {
        $response = $this->getJson('/api/v1/profile');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_fetch_their_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+1-555-0100',
            'job_title' => 'CEO',
            'bio' => 'Runs things.',
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/profile');

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $user->id);
        $response->assertJsonPath('data.name', 'Jane Doe');
        $response->assertJsonPath('data.email', 'jane@example.com');
        $response->assertJsonPath('data.phone', '+1-555-0100');
        $response->assertJsonPath('data.job_title', 'CEO');
        $response->assertJsonPath('data.bio', 'Runs things.');
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'email',
                'phone',
                'job_title',
                'bio',
                'birth_date',
                'avatar_url',
                'role',
            ],
        ]);
    }

    // ---------------------------------------------------------------------------
    // PATCH /api/v1/profile
    // ---------------------------------------------------------------------------

    public function test_authenticated_user_can_update_profile_fields(): void
    {
        $user = User::factory()->create();

        $payload = [
            'name' => 'Updated Name',
            'email' => $user->email,
            'phone' => '+1-555-9999',
            'job_title' => 'CTO',
            'bio' => 'Loves code.',
        ];

        $response = $this->actingAs($user)->patchJson('/api/v1/profile', $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'Updated Name');
        $response->assertJsonPath('data.phone', '+1-555-9999');
        $response->assertJsonPath('data.job_title', 'CTO');
        $response->assertJsonPath('data.bio', 'Loves code.');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'phone' => '+1-555-9999',
            'job_title' => 'CTO',
            'bio' => 'Loves code.',
        ]);
    }

    public function test_updating_email_resets_email_verified_at_to_null(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->assertNotNull($user->email_verified_at);

        $payload = [
            'name' => $user->name,
            'email' => 'newemail@example.com',
        ];

        $response = $this->actingAs($user)->patchJson('/api/v1/profile', $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'newemail@example.com',
            'email_verified_at' => null,
        ]);
    }

    public function test_cannot_update_profile_with_invalid_email_format(): void
    {
        $user = User::factory()->create();

        $payload = [
            'name' => $user->name,
            'email' => 'not-a-valid-email',
        ];

        $response = $this->actingAs($user)->patchJson('/api/v1/profile', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_unauthenticated_user_gets_401_on_patch_profile(): void
    {
        $response = $this->patchJson('/api/v1/profile', [
            'name' => 'Hacker',
            'email' => 'hacker@example.com',
        ]);

        $response->assertStatus(401);
    }

    // ---------------------------------------------------------------------------
    // PATCH /api/v1/profile/password
    // ---------------------------------------------------------------------------

    public function test_authenticated_user_can_change_password_with_correct_current_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('OldSecret1!')]);

        $payload = [
            'current_password' => 'OldSecret1!',
            'new_password' => 'NewSecret2!',
            'new_password_confirmation' => 'NewSecret2!',
        ];

        $response = $this->actingAs($user)->patchJson('/api/v1/profile/password', $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'Password updated successfully.');
    }

    public function test_cannot_change_password_with_wrong_current_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('CorrectSecret1!')]);

        $payload = [
            'current_password' => 'WrongSecret99!',
            'new_password' => 'NewSecret2!',
            'new_password_confirmation' => 'NewSecret2!',
        ];

        $response = $this->actingAs($user)->patchJson('/api/v1/profile/password', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['current_password']);
    }

    public function test_cannot_change_password_when_confirmation_does_not_match(): void
    {
        $user = User::factory()->create(['password' => bcrypt('OldSecret1!')]);

        $payload = [
            'current_password' => 'OldSecret1!',
            'new_password' => 'NewSecret2!',
            'new_password_confirmation' => 'DifferentSecret3!',
        ];

        $response = $this->actingAs($user)->patchJson('/api/v1/profile/password', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['new_password']);
    }

    public function test_unauthenticated_user_gets_401_on_patch_password(): void
    {
        $response = $this->patchJson('/api/v1/profile/password', [
            'current_password' => 'whatever',
            'new_password' => 'whatever2',
            'new_password_confirmation' => 'whatever2',
        ]);

        $response->assertStatus(401);
    }

    // ---------------------------------------------------------------------------
    // POST /api/v1/profile/avatar
    // ---------------------------------------------------------------------------

    public function test_avatar_upload_rejects_non_image_files(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->actingAs($user)->postJson('/api/v1/profile/avatar', [
            'avatar' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['avatar']);
    }

    public function test_avatar_upload_rejects_files_over_5mb(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        // 5121 KB exceeds the 5120 KB (5 MB) limit
        $file = UploadedFile::fake()->image('photo.jpg', 100, 100)->size(5121);

        $response = $this->actingAs($user)->postJson('/api/v1/profile/avatar', [
            'avatar' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['avatar']);
    }

    public function test_avatar_upload_rejects_images_over_800x800px(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        // 1024x1024 exceeds the 800x800 max dimensions rule
        $file = UploadedFile::fake()->image('photo.jpg', 1024, 1024);

        $response = $this->actingAs($user)->postJson('/api/v1/profile/avatar', [
            'avatar' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['avatar']);
    }

    public function test_successful_avatar_upload_returns_avatar_url_and_saves_path(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);

        $response = $this->actingAs($user)->postJson('/api/v1/profile/avatar', [
            'avatar' => $file,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'data' => ['avatar_url'],
        ]);
        $response->assertJsonPath('message', 'Avatar uploaded successfully.');

        $user->refresh();
        $this->assertNotNull($user->avatar_path);
        Storage::disk('public')->assertExists($user->avatar_path);
    }

    public function test_unauthenticated_user_gets_401_on_avatar_upload(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);

        $response = $this->postJson('/api/v1/profile/avatar', [
            'avatar' => $file,
        ]);

        $response->assertStatus(401);
    }
}
