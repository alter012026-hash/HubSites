<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Article extends Model
{
    protected $fillable = [
        'uuid',
        'organization_id',
        'site_id',
        'created_by',
        'title',
        'content',
        'status',
        'remote_post_id',
        'remote_status',
        'remote_url',
        'last_error',
    ];

    protected static function booted(): void
    {
        static::creating(function (Article $article): void {
            $article->uuid ??= (string) Str::uuid();
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ArticleVersion::class);
    }
}
