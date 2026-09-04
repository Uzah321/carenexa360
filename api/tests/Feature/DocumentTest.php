<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Documents\Models\Document;
use App\Modules\Organization\Models\Tenant;
use App\Modules\ServiceUsers\Models\ServiceUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_user_can_upload_and_download_a_document(): void
    {
        Storage::fake('local');

        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $serviceUser = ServiceUser::create([
            'tenant_id' => $tenant->id,
            'first_name' => 'John',
            'last_name' => 'Smith',
        ]);

        $file = UploadedFile::fake()->create('care-plan.pdf', 100, 'application/pdf');

        $upload = $this->actingAs($user)->postJson("/api/v1/service-users/{$serviceUser->id}/documents", [
            'file' => $file,
            'category' => 'Care Plan',
        ]);

        $upload->assertCreated()->assertJsonPath('data.original_filename', 'care-plan.pdf');

        $document = Document::first();
        Storage::disk('local')->assertExists($document->path);

        $download = $this->actingAs($user)->get("/api/v1/documents/{$document->id}/download");
        $download->assertOk();
    }

    public function test_tenant_user_cannot_download_another_tenants_document(): void
    {
        Storage::fake('local');

        $tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'country' => 'UK']);

        $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $serviceUserB = ServiceUser::create([
            'tenant_id' => $tenantB->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
        ]);

        $userB = User::factory()->create(['tenant_id' => $tenantB->id]);
        $file = UploadedFile::fake()->create('secret.pdf', 50, 'application/pdf');

        $upload = $this->actingAs($userB)->postJson("/api/v1/service-users/{$serviceUserB->id}/documents", [
            'file' => $file,
        ]);
        $documentId = $upload->json('data.id');

        // Implicit route-model-binding runs before our 'tenant' middleware sets
        // the tenant context, so it's the controller's explicit authorization
        // check — not scope-filtered binding — that rejects this (403).
        $this->actingAs($userA)
            ->get("/api/v1/documents/{$documentId}/download")
            ->assertForbidden();
    }
}
