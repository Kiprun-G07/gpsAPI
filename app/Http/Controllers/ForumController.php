<?php

namespace App\Http\Controllers;

use App\Models\ForumPost;
use App\Models\ForumComment;
use App\Models\ForumLike;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\Response;
use Exception;
use Firebase\JWT\JWT;

class ForumController extends Controller
{
    private $key;

    public function __construct()
    {
        $this->key = env('JWT_SECRET', 'your-256-bit-secret');
    }

    public function createPost(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['error' => 'Token not provided'], 401);
        }   

        $decoded = JWT::decode($token, new \Firebase\JWT\Key($this->key, 'HS256'));
        $userId = $decoded->user->id;

        $forumPost = ForumPost::create([
            'user_id' => $userId,
            'title' => $request->input('title'),
            'content' => $request->input('content'),
        ]);

        return response()->json([
            'message' => 'Forum post created successfully',
            'post' => $forumPost->with('user', 'comments', 'likes')->find($forumPost->id),
        ], Response::HTTP_CREATED);
    }

    public function likePost(Request $request, $postId)
    {
        try {
            $forumPost = ForumPost::findOrFail($postId);

            $token = $request->bearerToken();
            if (!$token) {
                return response()->json(['error' => 'Token not provided'], 401);
            }   

            $decoded = JWT::decode($token, new \Firebase\JWT\Key($this->key, 'HS256'));
            $userId = $decoded->user->id;

            // Check if the user has already liked the post
            $existingLike = ForumLike::where('user_id', $userId)
                ->where('forum_post_id', $postId)
                ->first();

            if ($existingLike) {
                return response()->json([
                    'message' => 'You have already liked this post',
                ], Response::HTTP_CONFLICT);
            }

            $forumLike = ForumLike::create([
                'user_id' => $userId,
                'forum_post_id' => $postId,
            ]);

            return response()->json([
                'message' => 'Post liked successfully',
                'like' => $forumLike,
            ], Response::HTTP_CREATED);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Forum post not found',
            ], Response::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'An error occurred while liking the post',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function unlikePost(Request $request, $postId)
    {
        try {
            $forumPost = ForumPost::findOrFail($postId);

            $token = $request->bearerToken();
            if (!$token) {
                return response()->json(['error' => 'Token not provided'], 401);
            }

            $decoded = JWT::decode($token, new \Firebase\JWT\Key($this->key, 'HS256'));
            $userId = $decoded->user->id;

            $existingLike = ForumLike::where('user_id', $userId)
                ->where('forum_post_id', $postId)
                ->first();

            if (!$existingLike) {
                return response()->json([
                    'message' => 'You have not liked this post',
                ], Response::HTTP_NOT_FOUND);
            }

            $existingLike->delete();

            return response()->json([
                'message' => 'Post unliked successfully',
            ], Response::HTTP_OK);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Forum post not found',
            ], Response::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'An error occurred while unliking the post',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getPostLikes($postId)
    {
        try {
            $forumPost = ForumPost::findOrFail($postId);

            $likesCount = $forumPost->likes()->count();

            return response()->json([
                'post_id' => $postId,
                'likes_count' => $likesCount,
            ], Response::HTTP_OK);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Forum post not found',
            ], Response::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'An error occurred while retrieving likes count',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getAllPosts()
    {
        // sort by created_at descending
        $posts = ForumPost::with('user', 'comments', 'likes')->orderBy('created_at', 'desc')->get();

        return response()->json([
            'posts' => $posts,
        ], Response::HTTP_OK);
    }

    public function getPost($postId)
    {
        try {
            $forumPost = ForumPost::with('user', 'comments', 'likes', 'comments.user')->findOrFail($postId);
            
            return response()->json([
                'post' => $forumPost,
            ], Response::HTTP_OK);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Forum post not found',
            ], Response::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'An error occurred while retrieving the post',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function deletePost(Request $request, $postId)
    {
        // Only admins can delete posts
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['error' => 'Token not provided'], 401);
        }
        $decoded = JWT::decode($token, new \Firebase\JWT\Key($this->key, 'HS256'));
        if ($decoded->user->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $forumPost = ForumPost::findOrFail($postId);
            $forumPost->delete();

            return response()->json([
                'message' => 'Forum post deleted successfully',
            ], Response::HTTP_OK);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Forum post not found',
            ], Response::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'An error occurred while deleting the post',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function createComment(Request $request, $postId)
    {
        $request->validate([
            'content' => 'required|string',
        ]);

        try {
            $forumPost = ForumPost::findOrFail($postId);

            $token = $request->bearerToken();
            if (!$token) {
                return response()->json(['error' => 'Token not provided'], 401);
            }   

            $decoded = JWT::decode($token, new \Firebase\JWT\Key($this->key, 'HS256'));
            $userId = $decoded->user->id;

            $forumComment = ForumComment::create([
                'user_id' => $userId,
                'forum_post_id' => $postId,
                'content' => $request->input('content'),
            ]);

            return response()->json([
                'message' => 'Comment added successfully',
                'comment' => $forumComment->with('user')->find($forumComment->id),
            ], Response::HTTP_CREATED);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Forum post not found',
            ], Response::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'An error occurred while adding the comment',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getPostComments($postId)
    {
        try {
            $forumPost = ForumPost::findOrFail($postId);

            $comments = $forumPost->comments()->with('user')->get();

            return response()->json([
                'post_id' => $postId,
                'comments' => $comments,
            ], Response::HTTP_OK);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Forum post not found',
            ], Response::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'An error occurred while retrieving comments',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}