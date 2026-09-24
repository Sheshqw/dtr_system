<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeaturePermission extends Model
{
    protected $fillable = ['user_id', 'feature_key', 'granted', 'granted_by'];

    protected function casts(): array
    {
        return [
            'granted' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function grantor()
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
