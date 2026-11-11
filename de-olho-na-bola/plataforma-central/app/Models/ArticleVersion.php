<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleVersion extends Model
{
    protected $fillable = [
        'article_id',
        'version',
        'title',
        'content',
        'created_by',
        'reason',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
