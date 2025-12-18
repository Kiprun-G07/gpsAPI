<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;
use App\Models\ForumPost;

class ForumLike extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'forum_post_id',
    ];

    /**
     * Get the user that owns the forum like.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the forum post that this like belongs to.
     */
    public function forumPost(): BelongsTo
    {
        return $this->belongsTo(ForumPost::class);
    }
}