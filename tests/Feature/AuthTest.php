<?php

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['/'])]
    #[TestWith(['/search'])]
    #[TestWith(['/library'])]
    #[TestWith(['/favorites'])]
    #[TestWith(['/recap'])]
    #[TestWith(['/profile'])]
    #[TestWith(['/playlists/1'])]
    public function test_guests_are_redirected_to_the_login_page(string $path): void
    {
        $this->get($path)->assertRedirect(route('login'));
    }

    public function test_login_and_registration_pages_link_to_each_other(): void
    {
        $this->get('/login')->assertOk()->assertSee(route('register'));
        $this->get('/register')->assertOk()->assertSee(route('login'));
    }

    public function test_registration_creates_the_account_and_signs_in(): void
    {
        Livewire::test(Register::class)
            ->set('name', 'Ada Lovelace')
            ->set('email', 'ada@example.com')
            ->set('password', 'una-clave-larga')
            ->set('password_confirmation', 'una-clave-larga')
            ->call('register')
            ->assertHasNoErrors()
            ->assertRedirect(route('home'));

        $user = User::sole();

        $this->assertSame('ada@example.com', $user->email);
        $this->assertTrue(Hash::check('una-clave-larga', $user->password));
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_reports_invalid_fields(): void
    {
        Livewire::test(Register::class)
            ->call('register')
            ->assertHasErrors(['name', 'email', 'password']);

        Livewire::test(Register::class)
            ->set('name', 'Ada')
            ->set('email', 'ada@example.com')
            ->set('password', 'una-clave-larga')
            ->set('password_confirmation', 'otra-distinta')
            ->call('register')
            ->assertSee('Las contraseñas no coinciden.')
            ->set('password', 'corta')
            ->set('password_confirmation', 'corta')
            ->call('register')
            ->assertSee('La contraseña debe tener al menos 8 caracteres.');

        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    public function test_registration_stays_open_for_more_accounts(): void
    {
        User::factory()->create();

        Livewire::test(Register::class)
            ->set('name', 'Grace Hopper')
            ->set('email', 'Grace@Example.com')
            ->set('password', 'una-clave-larga')
            ->set('password_confirmation', 'una-clave-larga')
            ->call('register')
            ->assertHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertDatabaseCount('users', 2);
        $this->assertAuthenticatedAs(User::where('email', 'grace@example.com')->sole());
    }

    public function test_registration_rejects_an_email_that_already_has_an_account(): void
    {
        User::factory()->create(['email' => 'ada@example.com']);

        Livewire::test(Register::class)
            ->set('name', 'Otra Ada')
            ->set('email', 'ADA@example.com')
            ->set('password', 'una-clave-larga')
            ->set('password_confirmation', 'una-clave-larga')
            ->call('register')
            ->assertSee('Ya hay una cuenta con ese correo.');

        $this->assertDatabaseCount('users', 1);
        $this->assertGuest();
    }

    public function test_registration_is_limited_per_connection(): void
    {
        foreach (range(1, 5) as $number) {
            Livewire::test(Register::class)
                ->set('name', "Cuenta {$number}")
                ->set('email', "cuenta{$number}@example.com")
                ->set('password', 'una-clave-larga')
                ->set('password_confirmation', 'una-clave-larga')
                ->call('register');

            auth()->logout();
        }

        Livewire::test(Register::class)
            ->set('name', 'Una más')
            ->set('email', 'una-mas@example.com')
            ->set('password', 'una-clave-larga')
            ->set('password_confirmation', 'una-clave-larga')
            ->call('register')
            ->assertSee('Se han creado demasiadas cuentas');

        $this->assertDatabaseCount('users', 5);
    }

    public function test_login_signs_in_with_valid_credentials(): void
    {
        $user = User::factory()->create();

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_rejects_wrong_credentials(): void
    {
        $user = User::factory()->create();

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'no-es-la-clave')
            ->call('login')
            ->assertSee('El correo o la contraseña no son correctos.')
            ->assertSet('password', '');

        $this->assertGuest();
    }

    public function test_login_locks_after_too_many_failed_attempts(): void
    {
        $user = User::factory()->create();

        $form = Livewire::test(Login::class)->set('email', $user->email);

        foreach (range(1, 5) as $attempt) {
            $form->set('password', 'no-es-la-clave')->call('login');
        }

        $form->set('password', 'password')
            ->call('login')
            ->assertSee('Demasiados intentos.');

        $this->assertGuest();
    }

    public function test_signed_in_users_skip_the_login_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/login')
            ->assertRedirect(route('home'));
    }

    public function test_logout_ends_the_session(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/logout')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
