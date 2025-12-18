<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;
use App\Models\ForumComment;
use App\Models\ForumLike;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class ForumPost extends Model
{
    use HasFactory;

    protected $appends = [
        'like_count',
        'auth_user_like'
    ];

    protected $fillable = [
        'user_id',
        'title',
        'content',
    ];

    /**
     * Get the user that owns the forum post.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the comments for the forum post.
     */
    public function comments()
    {
        return $this->hasMany(ForumComment::class);
    }

    /**
     * Get the likes for the forum post.
     */
    public function likes()
    {
        return $this->hasMany(ForumLike::class);
    }

    /**
     * Accessor for likes count.
     */
    public function getLikeCountAttribute(): int
    {
        return $this->likes()->count();
    }

    /**
     * Accessor to check if the authenticated user has liked the post without using auth() helper.
     */

    public function getAuthUserLikeAttribute(): bool
    {
        $token = request()->bearerToken();
        if (!$token) {
            return false;
        }

        $decoded = JWT::decode($token, new Key(env('JWT_SECRET', 'your-256-bit-secret'), 'HS256'));
        $userId = $decoded->user->id;

        return $this->likes()->where('user_id', $userId)->exists();
    }
}