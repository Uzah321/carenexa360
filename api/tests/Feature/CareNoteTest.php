<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\CareNotes\Models\CareNote;
use App\Modules\Organization\Models\Tenant;
use App\Modules\ServiceUsers\Models\ServiceUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CareNoteTest extends TestCase
{
    use RefreshDatabase;

    protected function makeTenantWithServiceUser(): array
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $carer = User::factory()->create(['tenant_id' => $tenant->id]);
        $serviceUser = ServiceUser::create([
            'tenant_id' => $tenant->id,
            'first_name' => 'John',
            'last_name' => 'Smith',
        ]);

        return compact('tenant', 'carer', 'serviceUser');
    }

    protected function assignRole(User $user, Tenant $tenant, string $roleName): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $user->assignRole(Role::where(['name' => $roleName, 'tenant_id' => $tenant->id])->firstOrFail());
    }

    public function test_a_carer_can_record_and_play_back_a_care_note(): void
    {
        Storage::fake('local');

        ['carer' => $carer, 'serviceUser' => $serviceUser] = $this->makeTenantWithServiceUser();
        $audio = UploadedFile::fake()->create('note.webm', 200, 'audio/webm');

        $upload = $this->actingAs($carer)->postJson("/api/v1/service-users/{$serviceUser->id}/care-notes", [
            'audio' => $audio,
            'caption' => 'Client was in good spirits today',
            'duration_seconds' => 14,
        ]);

        $upload->assertCreated()
            ->assertJsonPath('data.caption', 'Client was in good spirits today')
            ->assertJsonPath('data.duration_seconds', 14)
            ->assertJsonPath('data.author_name', $carer->name);

        $careNote = CareNote::first();
        Storage::disk('local')->assertExists($careNote->audio_path);

        $this->actingAs($carer)
            ->get("/api/v1/care-notes/{$careNote->id}/audio")
            ->assertOk();
    }

    public function test_a_non_audio_file_is_rejected(): void
    {
        Storage::fake('local');

        ['carer' => $carer, 'serviceUser' => $serviceUser] = $this->makeTenantWithServiceUser();
        $file = UploadedFile::fake()->create('note.pdf', 100, 'application/pdf');

        $this->actingAs($carer)
            ->postJson("/api/v1/service-users/{$serviceUser->id}/care-notes", ['audio' => $file])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('audio');
    }

    public function test_the_author_can_delete_their_own_note(): void
    {
        Storage::fake('local');

        ['carer' => $carer, 'serviceUser' => $serviceUser] = $this->makeTenantWithServiceUser();
        $audio = UploadedFile::fake()->create('note.webm', 50, 'audio/webm');
        $noteId = $this->actingAs($carer)
            ->postJson("/api/v1/service-users/{$serviceUser->id}/care-notes", ['audio' => $audio])
            ->json('data.id');

        $this->actingAs($carer)->deleteJson("/api/v1/care-notes/{$noteId}")->assertNoContent();
        $this->assertDatabaseCount('care_notes', 0);
    }

    public function test_a_different_carer_cannot_delete_someone_elses_note(): void
    {
        Storage::fake('local');

        ['tenant' => $tenant, 'carer' => $carer, 'serviceUser' => $serviceUser] = $this->makeTenantWithServiceUser();
        $otherCarer = User::factory()->create(['tenant_id' => $tenant->id]);
        $audio = UploadedFile::fake()->create('note.webm', 50, 'audio/webm');
        $noteId = $this->actingAs($carer)
            ->postJson("/api/v1/service-users/{$serviceUser->id}/care-notes", ['audio' => $audio])
            ->json('data.id');

        $this->actingAs($otherCarer)->deleteJson("/api/v1/care-notes/{$noteId}")->assertForbidden();
        $this->assertDatabaseCount('care_notes', 1);
    }

    public function test_a_care_manager_can_delete_another_carers_note(): void
    {
        Storage::fake('local');

        ['tenant' => $tenant, 'carer' => $carer, 'serviceUser' => $serviceUser] = $this->makeTenantWithServiceUser();
        $manager = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->assignRole($manager, $tenant, 'Care Manager');
        $audio = UploadedFile::fake()->create('note.webm', 50, 'audio/webm');
        $noteId = $this->actingAs($carer)
            ->postJson("/api/v1/service-users/{$serviceUser->id}/care-notes", ['audio' => $audio])
            ->json('data.id');

        $this->actingAs($manager)->deleteJson("/api/v1/care-notes/{$noteId}")->assertNoContent();
    }

    public function test_a_tenant_user_cannot_access_another_tenants_care_note_audio(): void
    {
        Storage::fake('local');

        ['carer' => $carerA, 'serviceUser' => $serviceUserA] = $this->makeTenantWithServiceUser();
        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'country' => 'UK']);
        $userB = User::factory()->create(['tenant_id' => $tenantB->id]);

        $audio = UploadedFile::fake()->create('note.webm', 50, 'audio/webm');
        $noteId = $this->actingAs($carerA)
            ->postJson("/api/v1/service-users/{$serviceUserA->id}/care-notes", ['audio' => $audio])
            ->json('data.id');

        $this->actingAs($userB)->get("/api/v1/care-notes/{$noteId}/audio")->assertForbidden();
    }
}
