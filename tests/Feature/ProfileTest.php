<?php

namespace Tests\Feature;

use App\Livewire\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploading_a_photo_stores_it_and_makes_it_the_avatar(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['avatar' => 'headphones']);

        Livewire::actingAs($user)
            ->test(Profile::class)
            ->set('photo', UploadedFile::fake()->image('yo.jpg', 512, 512))
            ->assertHasNoErrors()
            ->assertSet('photo', null)
            ->assertDispatched('avatar-changed');

        $avatar = $user->refresh()->avatar;

        $this->assertStringStartsWith("avatars/{$user->id}-", $avatar);
        Storage::disk('public')->assertExists($avatar);
        $this->assertSame(asset('storage/'.$avatar), $user->avatarUrl());
    }

    public function test_a_new_photo_replaces_the_previous_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $profile = Livewire::actingAs($user)->test(Profile::class);

        $profile->set('photo', UploadedFile::fake()->image('primera.png', 300, 300));
        $first = $user->refresh()->avatar;

        $profile->set('photo', UploadedFile::fake()->image('segunda.png', 300, 300));
        $second = $user->refresh()->avatar;

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_files_that_are_not_photos_are_rejected(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['avatar' => 'vinyl']);

        Livewire::actingAs($user)
            ->test(Profile::class)
            ->set('photo', UploadedFile::fake()->create('cancion.pdf', 120, 'application/pdf'))
            ->assertSee('El archivo tiene que ser una imagen.')
            ->assertSet('photo', null);

        $this->assertSame('vinyl', $user->refresh()->avatar);
        $this->assertSame([], Storage::disk('public')->allFiles('avatars'));
    }

    public function test_photos_larger_than_the_limit_are_rejected(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Profile::class)
            ->set('photo', UploadedFile::fake()->image('enorme.jpg', 2100, 100))
            ->assertSee('La foto es demasiado grande.');

        $this->assertSame('', (string) $user->refresh()->avatar);
    }

    public function test_removing_the_photo_deletes_the_file_and_falls_back_to_the_initial(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['name' => 'Ada Lovelace']);
        $profile = Livewire::actingAs($user)->test(Profile::class);

        $profile->set('photo', UploadedFile::fake()->image('yo.jpg', 300, 300));
        $photo = $user->refresh()->avatar;

        $profile->call('removePhoto')->assertDispatched('avatar-changed', url: null, symbol: 'A');

        Storage::disk('public')->assertMissing($photo);
        $this->assertNull($user->refresh()->avatarUrl());
    }

    public function test_choosing_an_icon_replaces_the_uploaded_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $profile = Livewire::actingAs($user)->test(Profile::class);

        $profile->set('photo', UploadedFile::fake()->image('yo.jpg', 300, 300));
        $photo = $user->refresh()->avatar;

        $profile->call('selectAvatar', 'guitar')->call('selectAvatar', 'no-existe');

        $this->assertSame('guitar', $user->refresh()->avatar);
        Storage::disk('public')->assertMissing($photo);
    }

    public function test_new_accounts_start_with_the_dark_theme(): void
    {
        $user = User::factory()->create()->refresh();

        $this->assertSame('grafito', $user->theme);
        $this->assertSame('dark', $user->themeConfig()['scheme']);
        $this->actingAs($user)->get('/')->assertSee('data-scheme="dark"', false);
    }

    public function test_an_unknown_saved_theme_falls_back_to_the_default_one(): void
    {
        $user = User::factory()->create(['theme' => 'ya-no-existe']);

        $this->assertSame('grafito', $user->themeKey());
    }

    public function test_new_accounts_start_with_the_default_transparency(): void
    {
        $user = User::factory()->create()->refresh();

        $this->assertSame(30, $user->transparency);
        $this->actingAs($user)->get('/')->assertSee('--translucency: 0.30', false);
    }

    public function test_choosing_a_transparency_level_saves_it_and_applies_it_to_the_pages(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Profile::class)
            ->call('saveTransparency', 72)
            ->assertSet('transparency', 72);

        $this->assertSame(72, $user->refresh()->transparency);
        $this->actingAs($user)->get('/')->assertSee('--translucency: 0.72', false);
    }

    #[TestWith([150, 100])]
    #[TestWith([-20, 0])]
    public function test_a_transparency_level_outside_the_range_is_limited_to_it(int $requested, int $saved): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Profile::class)
            ->call('saveTransparency', $requested);

        $this->assertSame($saved, $user->refresh()->transparency);
    }

    public function test_changing_the_transparency_does_not_touch_other_accounts(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Profile::class)
            ->call('saveTransparency', 90);

        $this->assertSame(30, $other->refresh()->transparency);
    }
}
