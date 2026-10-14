<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Un visiteur non connecté est redirigé vers la page de connexion
     * (l'inscription publique est désactivée).
     */
    public function test_la_racine_redirige_vers_la_connexion_pour_un_visiteur(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }
}
