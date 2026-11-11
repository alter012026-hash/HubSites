<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Site extends Model
{
    protected $fillable = [
        'uuid',
        'organization_id',
        'created_by',
        'name',
        'base_url',
        'wp_username',
        'application_password',
        'connection_status',
        'connection_message',
        'wp_version',
        'capabilities',
        'last_checked_at',
    ];

    protected $hidden = ['application_password', 'wp_username'];

    protected function casts(): array
    {
        return [
            'application_password' => 'encrypted',
            'capabilities' => 'array',
            'last_checked_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }
}
