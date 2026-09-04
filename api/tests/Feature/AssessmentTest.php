<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Organization\Models\Tenant;
use App\Modules\ServiceUsers\Models\ServiceUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_user_can_create_a_template_and_submit_a_response(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $serviceUser = ServiceUser::create([
            'tenant_id' => $tenant->id,
            'first_name' => 'John',
            'last_name' => 'Smith',
        ]);

        $template = $this->actingAs($user)->postJson('/api/v1/assessment-templates', [
            'name' => 'Falls Risk Assessment',
            'category' => 'Falls Risk',
            'fields' => [
                ['key' => 'history_of_falls', 'label' => 'History of falls?', 'type' => 'checkbox'],
                ['key' => 'mobility_score', 'label' => 'Mobility score (0-10)', 'type' => 'score'],
            ],
        ]);
        $template->assertCreated();
        $templateId = $template->json('data.id');

        $response = $this->actingAs($user)->postJson("/api/v1/service-users/{$serviceUser->id}/assessment-responses", [
            'assessment_template_id' => $templateId,
            'answers' => ['history_of_falls' => true, 'mobility_score' => 6],
            'status' => 'completed',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.template_name', 'Falls Risk Assessment')
            ->assertJsonPath('data.answers.mobility_score', 6)
            ->assertJsonPath('data.status', 'completed');

        $this->actingAs($user)
            ->getJson("/api/v1/service-users/{$serviceUser->id}/assessment-responses")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_template_field_type_must_be_a_supported_type(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($user)->postJson('/api/v1/assessment-templates', [
            'name' => 'Bad Template',
            'fields' => [
                ['key' => 'signature', 'label' => 'Sign here', 'type' => 'signature'],
            ],
        ]);

        $response->assertUnprocessable();
    }
}
