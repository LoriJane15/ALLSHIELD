<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JapicStage2AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_inactive_and_every_non_japic_role_are_denied_all_japic_entry_routes(): void
    {
        $routes = [route('japic.dashboard'), route('japic.certifications.index')];
        foreach ($routes as $route) {
            $this->get($route)->assertRedirect(route('login'));
        }
        $inactive = User::factory()->role('japic')->create(['is_active' => false]);
        foreach ($routes as $route) {
            $this->actingAs($inactive)->get($route)->assertRedirect(route('login'));
        }
        foreach (['39th_ib', 'admin', 'super_admin', 'lgu', 'afp', 'mblrc', 'gov_agency'] as $role) {
            $user = User::factory()->role($role)->create();
            foreach ($routes as $route) {
                $this->actingAs($user)->get($route)->assertForbidden();
            }
        }
    }

    public function test_active_japic_has_only_the_explicit_stage_three_mutation_routes_and_correct_home(): void
    {
        $japic = User::factory()->role('japic')->create();
        $this->assertSame('japic.dashboard', $japic->homeRoute());
        $this->actingAs($japic)->get(route('japic.dashboard'))->assertOk();
        $this->actingAs($japic)->get(route('japic.certifications.index'))->assertOk();
        foreach ([route('ib39.fr-profiles.create'), route('ib39.fea.index'), route('admin.rcsp.index'), route('super_admin.users.index')] as $route) {
            $this->actingAs($japic)->get($route)->assertForbidden();
        }
        $mutations = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->getName() ?? '', 'japic.') && ! in_array('GET', $route->methods(), true))
            ->mapWithKeys(fn ($route) => [$route->getName() => $route->methods()[0]])->all();
        $this->assertSame([
            'japic.certifications.draft.update' => 'PUT',
            'japic.certifications.photos.store' => 'POST',
            'japic.certifications.final-document.upload' => 'POST',
            'japic.certifications.submit-for-signing' => 'POST',
            'japic.certifications.signing-complete' => 'POST',
        ], $mutations);
    }
}
