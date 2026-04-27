<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Tests\TestCase;

/**
 * Verifies that public self-registration is disabled (Wave 8 / Fix C1).
 *
 * In a multi-tenant SaaS, user accounts must always belong to a school and
 * have a role assigned. Allowing anyone on the internet to POST /register
 * created users in a permissions limbo (no role, no school_id).
 *
 * Wave 9 additionally turned User into a MustVerifyEmail implementor, so
 * any account created out-of-band would also be unable to satisfy the
 * `verified` middleware until it goes through the invite flow.
 *
 * New users are created exclusively via:
 *   - school_admin → POST /admin/users (InviteUserAction)
 *   - super_admin  → ProvisionSchoolAction (creates School + first school_admin)
 */
class RegistrationDisabledTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_get_route_returns_404(): void
    {
        $response = $this->get('/register');

        $response->assertNotFound();
    }

    public function test_register_post_route_returns_404(): void
    {
        $response = $this->post('/register', [
            'name' => 'Anyone',
            'email' => 'anyone@example.com',
            'password' => 'StrongPass!2026',
            'password_confirmation' => 'StrongPass!2026',
        ]);

        $response->assertNotFound();
    }

    public function test_login_page_does_not_show_register_link(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        // Login.jsx is rendered via Inertia. Check the serialized payload does
        // not advertise the register endpoint or any "create account" CTA.
        $body = $response->getContent();

        $this->assertStringNotContainsString('Cadastre-se', $body);
        $this->assertStringNotContainsString('Sign up', $body);
        $this->assertStringNotContainsString('Criar conta', $body);
        // Inertia link prop would carry "/register" in the JSON payload.
        $this->assertStringNotContainsString('"/register"', $body);
    }

    public function test_register_route_name_is_no_longer_registered(): void
    {
        // route('register') should throw a RouteNotFoundException because the
        // named route was removed entirely. Catching it here documents the
        // contract: any code/test that still references this route name will
        // fail loudly rather than silently render a working endpoint.
        $this->expectException(RouteNotFoundException::class);

        route('register');
    }
}
