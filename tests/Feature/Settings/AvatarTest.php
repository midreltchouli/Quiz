<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AvatarTest extends TestCase
{
    use RefreshDatabase;

    private function photo(): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');

        return UploadedFile::fake()->createWithContent('avatar.png', $png);
    }

    public function test_photo_upload_is_displayed_and_replacing_it_removes_the_previous_file(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $this->actingAs($user);
        $fields = ['name' => $user->name, 'email' => $user->email];
        $this->patch(route('profile.update'), [...$fields, 'avatar' => $this->photo()])
            ->assertSessionHasNoErrors();
        $first = $user->fresh()->avatar_path;
        $this->assertNotNull($first);
        Storage::disk('local')->assertExists($first);
        $this->get(route('account.avatar'))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get(route('accueil'))->assertSee(route('account.avatar'), false);
        $this->get(route('profile.edit'))->assertSee('class="profile-photo"', false);

        $this->patch(route('profile.update'), [...$fields, 'avatar' => $this->photo()])
            ->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($user->fresh()->avatar_path);
    }

    public function test_removing_a_photo_deletes_the_file(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('avatars/old.png', 'old');
        $user = User::factory()->create();
        $user->forceFill(['avatar_path' => 'avatars/old.png'])->save();
        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name, 'email' => $user->email, 'remove_avatar' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertNull($user->fresh()->avatar_path);
        Storage::disk('local')->assertMissing('avatars/old.png');
        $this->get(route('account.avatar'))->assertNotFound();
    }

    public function test_non_images_and_oversized_files_are_rejected(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $this->actingAs($user);
        foreach ([UploadedFile::fake()->createWithContent('avatar.svg', '<svg></svg>'), UploadedFile::fake()->create('large.png', 2049, 'image/png')] as $file) {
            $this->patch(route('profile.update'), [
                'name' => $user->name, 'email' => $user->email, 'avatar' => $file,
            ])->assertSessionHasErrors('avatar');
        }
        $this->assertNull($user->fresh()->avatar_path);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_a_user_cannot_read_another_users_photo(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('avatars/other.png', 'other');
        $owner = User::factory()->create();
        $owner->forceFill(['avatar_path' => 'avatars/other.png'])->save();
        $this->get(route('account.avatar'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())
            ->get(route('account.avatar', ['user' => $owner->id]))->assertNotFound();
    }

    public function test_deleting_a_user_also_deletes_their_photo(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('avatars/old.png', 'old');
        $user = User::factory()->create();
        $user->forceFill(['avatar_path' => 'avatars/old.png'])->save();
        $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing('avatars/old.png');
    }
}
