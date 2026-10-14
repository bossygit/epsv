<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Authentification de la V1 :
 *  - inscription publique, vérification d'e-mail et réinitialisation par
 *    e-mail volontairement désactivées ;
 *  - connexion, déconnexion et changement de mot de passe fonctionnels ;
 *  - un compte désactivé est immédiatement rejeté.
 */
class AuthScaffoldingTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_routes_retirees_sont_indisponibles(): void
    {
        foreach (['/register', '/forgot-password', '/verify-email', '/confirm-password'] as $url) {
            $this->get($url)->assertNotFound();
        }
    }

    public function test_connexion_et_deconnexion(): void
    {
        $user = User::factory()->create(['email' => 'test.connexion@epsv.cg']);

        $this->post('/login', [
            'email' => 'test.connexion@epsv.cg',
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_mauvais_mot_de_passe_refuse(): void
    {
        User::factory()->create(['email' => 'test.refus@epsv.cg']);

        $this->post('/login', [
            'email' => 'test.refus@epsv.cg',
            'password' => 'mauvais-mot-de-passe',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_compte_desactive_ne_peut_pas_se_connecter_ni_rester_connecte(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create(['email' => 'desactive@epsv.cg', 'is_active' => false]);

        // La connexion est refusée.
        $this->post('/login', [
            'email' => 'desactive@epsv.cg',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();

        // S'il était connecté, il est déconnecté à la requête suivante.
        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_changement_de_mot_de_passe(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/password', [
            'current_password' => 'password',
            'password' => 'nouveau-mot-de-passe',
            'password_confirmation' => 'nouveau-mot-de-passe',
        ])->assertSessionHasNoErrors();
    }
}
