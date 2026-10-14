<?php

namespace Tests\Feature;

use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Vérifications de contenu sur les pages de structure : au-delà du simple
 * code HTTP, on s'assure que les listes déroulantes sont réellement
 * alimentées (un sélecteur vide est un bug fonctionnel invisible en test
 * de rendu).
 */
class StructurePagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_la_page_classes_propose_niveaux_et_enseignants(): void
    {
        $direction = User::where('email', 'direction@epsv.cg')->firstOrFail();

        $response = $this->actingAs($direction)->get(route('classes.index'));
        $response->assertOk();

        // Enseignants titulaires possibles
        $response->assertSee('Jean Mabiala', false);
        $response->assertSee('Clarisse Kimbembe', false);

        // Niveaux de la structure
        $response->assertSee('Sixième', false);
        $response->assertSee('Cours Moyen 2', false);

        // Années scolaires
        $response->assertSee('2026-2027', false);

        // La modale de création est bien présente
        $response->assertSee('open-modal', false);
    }

    public function test_la_fiche_classe_affiche_ses_sections(): void
    {
        $direction = User::where('email', 'direction@epsv.cg')->firstOrFail();
        $class = SchoolClass::where('name', '6ème A')->firstOrFail();

        $response = $this->actingAs($direction)->get(route('classes.show', $class));
        $response->assertOk();

        foreach (['Élèves inscrits', 'Matières', 'Affectations', 'Enseignants'] as $section) {
            $response->assertSee($section, false);
        }

        // Les élèves de la classe sont listés avec leur matricule
        $matricule = $class->activeEnrollments()->first()->student->matricule;
        $response->assertSee($matricule, false);
    }

    public function test_la_page_matieres_liste_les_matieres_avec_coefficients(): void
    {
        $direction = User::where('email', 'direction@epsv.cg')->firstOrFail();

        $response = $this->actingAs($direction)->get(route('subjects.index'));
        $response->assertOk();

        $response->assertSee('Mathématiques', false);
        $response->assertSee('MATH', false);
        $response->assertSee('Français', false);
    }
}
