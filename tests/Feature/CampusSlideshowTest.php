<?php

use App\Models\Campus;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

test('a campus uses its bundled official photo until one is uploaded', function () {
    // public/images/campuses/CAN.jpg ships with the app; there is none for TAN.
    expect(Campus::factory()->create(['code' => 'CAN'])->photo_url)->toBe('/images/campuses/CAN.jpg')
        ->and(Campus::factory()->create(['code' => 'TAN'])->photo_url)->toBeNull();
});

test('the login page gets every active campus with a photo, and nothing else', function () {
    Campus::factory()->create(['code' => 'CAN', 'name' => 'Cantilan Campus', 'address' => 'Cantilan, Surigao del Sur']);
    Campus::factory()->create(['code' => 'TAN', 'name' => 'Tandag Campus']);                      // no photo
    Campus::factory()->create(['code' => 'BIS', 'name' => 'Bislig Campus', 'is_active' => false]); // inactive

    $this->getJson('/api/system-settings')
        ->assertOk()
        ->assertJsonPath('campus_slides', [[
            'name'      => 'Cantilan Campus',
            'address'   => 'Cantilan, Surigao del Sur',
            'photo_url' => '/images/campuses/CAN.jpg',
        ]]);
});

test('the super admin can upload a campus photo and remove it again', function () {
    $super  = User::factory()->superAdmin()->create();
    $campus = Campus::factory()->create(['code' => 'TAN', 'name' => 'Tandag Campus']);

    $res = $this->actingAs($super, 'sanctum')
        ->post("/api/campuses/{$campus->id}/photo", ['photo' => UploadedFile::fake()->image('tandag.png', 1600, 900)], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('has_custom_photo', true);

    $path = $campus->fresh()->photo_path;
    Storage::disk('public')->assertExists($path);
    expect($res->json('photo_url'))->toBe(Storage::disk('public')->url($path));

    // Now in the slideshow.
    auth()->forgetGuards();
    $this->getJson('/api/system-settings')->assertJsonPath('campus_slides.0.name', 'Tandag Campus');

    $this->actingAs($super, 'sanctum')
        ->deleteJson("/api/campuses/{$campus->id}/photo")
        ->assertOk()
        ->assertJsonPath('has_custom_photo', false)
        ->assertJsonPath('photo_url', null);

    Storage::disk('public')->assertMissing($path);
});

test('an hr officer cannot change campus photos', function () {
    $campus = Campus::factory()->create(['code' => 'CAN']);
    $hr     = User::factory()->hrOfficer()->create(['campus_id' => $campus->id]);

    $this->actingAs($hr, 'sanctum')
        ->post("/api/campuses/{$campus->id}/photo", ['photo' => UploadedFile::fake()->image('x.png')], ['Accept' => 'application/json'])
        ->assertForbidden();

    $this->actingAs($hr, 'sanctum')
        ->deleteJson("/api/campuses/{$campus->id}/photo")
        ->assertForbidden();
});

test('a campus code can never point the bundled-photo lookup outside its folder', function () {
    expect(Campus::factory()->make(['code' => '../../CAN'])->photo_url)->toBeNull();
});
