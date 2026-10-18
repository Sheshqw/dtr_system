<?php

use App\Models\Campus;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->campus = Campus::factory()->create(['code' => 'CAN']);
    $this->hr     = User::factory()->hrOfficer()->create(['campus_id' => $this->campus->id]);
});

test('an hr officer can create, list and download a backup of their campus', function () {
    $filename = $this->actingAs($this->hr, 'sanctum')
        ->postJson('/api/backups')
        ->assertOk()
        ->json('filename');

    expect($filename)->toStartWith('backup_can_');
    Storage::disk('local')->assertExists("backups/{$filename}");

    $this->actingAs($this->hr, 'sanctum')
        ->getJson('/api/backups')
        ->assertOk()
        ->assertJsonFragment(['filename' => $filename]);

    $this->actingAs($this->hr, 'sanctum')
        ->get("/api/backups/{$filename}/download")
        ->assertOk();
});

test('an hr officer cannot download another campus\'s backup', function () {
    Storage::disk('local')->put('backups/backup_tan_20261018_080000.sql', '-- other campus');

    $this->actingAs($this->hr, 'sanctum')
        ->get('/api/backups/backup_tan_20261018_080000.sql/download')
        ->assertForbidden();
});

test('restoring and deleting backups stay super admin only', function () {
    Storage::disk('local')->put('backups/backup_can_20261018_080000.sql', '-- backup');

    $this->actingAs($this->hr, 'sanctum')
        ->postJson('/api/backups/backup_can_20261018_080000.sql/restore')
        ->assertForbidden();

    $this->actingAs($this->hr, 'sanctum')
        ->deleteJson('/api/backups/backup_can_20261018_080000.sql')
        ->assertForbidden();

    Storage::disk('local')->assertExists('backups/backup_can_20261018_080000.sql');
});

test('faculty and staff still cannot open backups', function () {
    $employee = User::factory()->create(['campus_id' => $this->campus->id]);

    $this->actingAs($employee, 'sanctum')->getJson('/api/backups')->assertForbidden();
    $this->actingAs($employee, 'sanctum')->postJson('/api/backups')->assertForbidden();
});
