<?php

use App\Models\Campus;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->campus = Campus::factory()->create(['code' => 'CAN']);
});

test('a regular employee cannot manage a signature', function () {
    $employee = User::factory()->create(['campus_id' => $this->campus->id]);

    $this->actingAs($employee, 'sanctum')->getJson('/api/my-signature')->assertStatus(403);
    $this->actingAs($employee, 'sanctum')->postJson('/api/my-signature', [
        'signature' => UploadedFile::fake()->image('sig.png', 200, 80),
    ])->assertStatus(403);
});

test('an hr officer can upload a signature and it is enabled by default', function () {
    $hr = User::factory()->hrOfficer()->create(['campus_id' => $this->campus->id]);

    $this->actingAs($hr, 'sanctum')
        ->postJson('/api/my-signature', ['signature' => UploadedFile::fake()->image('sig.png', 200, 80)])
        ->assertStatus(200)
        ->assertJson(['signature_enabled' => true]);

    $hr->refresh();
    expect($hr->signature_path)->not->toBeNull();
    expect($hr->signature_enabled)->toBeTrue();
    Storage::disk('public')->assertExists($hr->signature_path);
});

test('toggling a signature on without one uploaded fails', function () {
    $hr = User::factory()->hrOfficer()->create(['campus_id' => $this->campus->id]);

    $this->actingAs($hr, 'sanctum')
        ->patchJson('/api/my-signature', ['enabled' => true])
        ->assertStatus(422);
});

test('an hr officer can toggle their uploaded signature off and back on', function () {
    $admin = User::factory()->hrOfficer()->create(['campus_id' => $this->campus->id]);

    $this->actingAs($admin, 'sanctum')->postJson('/api/my-signature', [
        'signature' => UploadedFile::fake()->image('sig.png', 200, 80),
    ])->assertStatus(200);

    $this->actingAs($admin, 'sanctum')
        ->patchJson('/api/my-signature', ['enabled' => false])
        ->assertStatus(200)
        ->assertJson(['signature_enabled' => false]);

    expect($admin->fresh()->signature_enabled)->toBeFalse();

    $this->actingAs($admin, 'sanctum')
        ->patchJson('/api/my-signature', ['enabled' => true])
        ->assertStatus(200)
        ->assertJson(['signature_enabled' => true]);

    expect($admin->fresh()->signature_enabled)->toBeTrue();
});

test('removing a signature clears the file and disables it', function () {
    $hr = User::factory()->hrOfficer()->create(['campus_id' => $this->campus->id]);

    $this->actingAs($hr, 'sanctum')->postJson('/api/my-signature', [
        'signature' => UploadedFile::fake()->image('sig.png', 200, 80),
    ])->assertStatus(200);

    $path = $hr->fresh()->signature_path;

    $this->actingAs($hr, 'sanctum')->deleteJson('/api/my-signature')->assertStatus(200);

    $hr->refresh();
    expect($hr->signature_path)->toBeNull();
    expect($hr->signature_enabled)->toBeFalse();
    Storage::disk('public')->assertMissing($path);
});
