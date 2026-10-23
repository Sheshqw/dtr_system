<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Campus extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code', 'address', 'director_name', 'photo_path', 'is_active'];

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
}
