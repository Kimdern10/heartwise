<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'John Doe',
            'email' => 'test@example.com',
            'date_of_birth' => now()->subYears(25)->toDateString(),
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => '1',
        ]);

        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_people_under_18_cannot_register(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Too Young',
            'email' => 'young@example.com',
            'date_of_birth' => now()->subYears(18)->addDay()->toDateString(),
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => '1',
        ]);

        $response->assertSessionHasErrors('date_of_birth');
        $this->assertGuest();
    }

    public function test_people_who_turn_18_today_can_register(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Birthday Person',
            'email' => 'birthday@example.com',
            'date_of_birth' => now()->subYears(18)->toDateString(),
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertAuthenticated();
    }

    public function test_terms_must_be_accepted(): void
    {
        $this->post(route('register.store'), [
            'name' => 'No Terms',
            'email' => 'noterms@example.com',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('terms');

        $this->assertGuest();
    }
}
