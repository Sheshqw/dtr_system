<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Campus extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code', 'address', 'director_name', 'photo_path', 'is_active'];

    /**
     * Where each bundled photo in public/images/campuses came from. The two
     * Wikimedia Commons photos are CC BY-SA, which requires crediting the
     * photographer and licence wherever they're shown; the rest are NEMSU's
     * own, from the Campuses section of nemsu.edu.ph.
     */
    public const BUNDLED_PHOTO_CREDITS = [
        'TAN' => ['Photo: Rektz, CC BY-SA 4.0', 'https://commons.wikimedia.org/wiki/File:NEMSU_Field.jpg'],
        'CAN' => ['Photo: Surigaotourist, CC BY-SA 3.0', 'https://commons.wikimedia.org/wiki/File:Cantilan_Uni_3.JPG'],
        'SMC' => ['Photo: NEMSU', null],
        'CAG' => ['Photo: NEMSU', null],
        'LIA' => ['Photo: NEMSU', null],
        'TAG' => ['Photo: NEMSU', null],
        'BIS' => ['Photo: NEMSU', null],
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function departments()
    {
        return $this->hasMany(Department::class);
    }

    public function employees()
    {
        return $this->hasMany(User::class);
    }

    /**
     * The campus's photo for the login page slideshow: an uploaded one, else
     * the official photo bundled for its code (public/images/campuses — from
     * the Campuses section of nemsu.edu.ph), else none.
     */
    public function getPhotoUrlAttribute(): ?string
    {
        if ($this->photo_path) {
            return Storage::disk('public')->url($this->photo_path);
        }

        $code = strtoupper((string) $this->code);
        if (! preg_match('/^[A-Z0-9-]+$/', $code)) {
            return null;
        }

        $bundled = "images/campuses/{$code}.jpg";

        return is_file(public_path($bundled)) ? '/' . $bundled : null;
    }

    /**
     * [text, link] crediting a bundled photo; null for an uploaded one (the
     * Super Admin's own) or none at all.
     */
    public function photoCredit(): ?array
    {
        if ($this->photo_path || ! $this->photo_url) {
            return null;
        }

        return self::BUNDLED_PHOTO_CREDITS[strtoupper((string) $this->code)] ?? null;
    }
}
