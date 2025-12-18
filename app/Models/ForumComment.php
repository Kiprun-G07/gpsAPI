<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;
use App\Models\ForumPost;

class ForumComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'forum_post_id',
        'parent_comment_id',
        'content',
    ];

    /**
     * Get the user that owns the forum comment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the forum post that this comment belongs to.
     */
    public function forumPost(): BelongsTo
    {
        return $this->belongsTo(ForumPost::class);
    }

    /**
     * Get the parent comment if this is a reply.
     */
    public function parentComment(): BelongsTo
    {
        return $this->belongsTo(ForumComment::class, 'parent_comment_id');
    }
}