<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_inertia_login_and_register_links_load_the_html_pages(): void
    {
        $manifest = public_path('build/manifest.json');
        $version = file_exists($manifest) ? hash_file('xxh128', $manifest) : '';

        foreach (['login', 'register'] as $route) {
            $this->get(route($route), [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => $version,
            ])->assertStatus(409)->assertHeader('X-Inertia-Location', route($route));

            $this->get(route($route))->assertOk()->assertSee('<form method="POST"', false);
        }
    }

    public function test_users_can_log_in_to_the_quiz_with_name_email_and_password(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasNoErrors()->assertRedirect(route('accueil'));

        $this->assertAuthenticatedAs($user);
        $this->get('/quiz')->assertOk();
    }

    public function test_invalid_credentials_return_an_error_to_the_login_page(): void
    {
        $user = User::factory()->create();

        $this->from(route('login'))->post(route('login.store'), [
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertRedirect(route('login'))->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_users_cannot_log_in_without_a_name(): void
    {
        $user = User::factory()->create();

        $this->from(route('login'))->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('login'))->assertSessionHasErrors('name');

        $this->assertGuest();
    }

    public function test_users_cannot_log_in_with_an_incorrect_name(): void
    {
        $user = User::factory()->create();

        $this->from(route('login'))->post(route('login.store'), [
            'name' => $user->name.' incorrect',
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('login'))->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
