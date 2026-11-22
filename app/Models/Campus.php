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
     * Where each bundled photo in public/images/campuses came from: NEMSU's
     * own campus photos (several of them drone shots), from each campus's
     * page on nemsu.edu.ph — Tagbina's from the campus's own site. Cropped
     * to remove the seal and place labels stamped on them, since the
     * slideshow captions each campus itself.
     */
    public const BUNDLED_PHOTO_CREDITS = [
        'TAN' => ['Photo: NEMSU', 'https://nemsu.edu.ph/campuses/tandag'],
        'CAN' => ['Photo: NEMSU', 'https://nemsu.edu.ph/campuses/cantilan'],
        'SMC' => ['Photo: NEMSU', 'https://nemsu.edu.ph/campuses/san-miguel'],
        'CAG' => ['Photo: NEMSU', 'https://nemsu.edu.ph/campuses/cagwait'],
        'LIA' => ['Photo: NEMSU', 'https://nemsu.edu.ph/campuses/lianga'],
        'TAG' => ['Photo: NEMSU Tagbina', 'https://nemsu-tagbina.edu.ph/'],
        'BIS' => ['Photo: NEMSU', 'https://nemsu.edu.ph/campuses/bislig'],
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

        // ?v= changes whenever the file does, so a replaced photo shows at
        // once instead of after the browser's week-long cached copy expires.
        $file = public_path($bundled);

        return is_file($file) ? '/' . $bundled . '?v=' . filemtime($file) : null;
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
