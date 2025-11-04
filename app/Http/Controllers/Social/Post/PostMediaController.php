<?php

namespace App\Http\Controllers\Social\Post;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostMedia;
use App\Http\Resources\ResponseResource;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Exception;

/**
 * @OA\Tag(
 *     name="Post Media",
 *     description="Upload media for posts"
 * )
 */
class PostMediaController extends Controller
{
    public const ERROR_MESSAGE = "Terjadi kesalahan pada server";

    /**
     * @OA\Post(
     *     path="/api/post-media/{postId}",
     *     tags={"Post Media"},
     *     summary="Upload media to a post",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="postId",
     *         in="path",
     *         required=true,
     *         description="UUID of the post",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"file"},
     *                 @OA\Property(
     *                     property="file",
     *                     type="string",
     *                     format="binary",
     *                     description="Media file (image/video)"
     *                 ),
     *                 @OA\Property(
     *                     property="type",
     *                     type="string",
     *                     example="image",
     *                     description="Media type (image/video)"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Media uploaded"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error"
     *     )
     * )
     */
    public function upload(Request $request, $postId)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            $post = Post::find($postId);
            if (!$post || $post->user_id !== $user->id) {
                return ResponseResource::json(403, 'error', 'Tidak boleh upload ke post orang lain');
            }

            // Validasi file
            $request->validate([
                'file' => 'required|file|mimes:jpg,jpeg,png,mp4,mov,avi|max:20048'
            ]);

            // Upload file
            $path = $request->file('file')->store('post-media', 'public');

            // Simpan ke DB
            $media = PostMedia::create([
                'post_id' => $postId,
                'media_url' => $path,
                'type' => $request->type ?? 'image',
            ]);

            return ResponseResource::json(200, 'success', 'Media uploaded', $media);

        } catch (Exception $e) {
            return ResponseResource::json(500, 'error', self::ERROR_MESSAGE, $e->getMessage());
        }
    }
}
