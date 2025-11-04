<?php

namespace App\Http\Controllers\Social\Post;

use App\Http\Controllers\Controller;
use App\Models\Like;
use App\Http\Resources\ResponseResource;
use Tymon\JWTAuth\Facades\JWTAuth;
use Exception;

/**
 * @OA\Tag(
 *     name="Likes",
 *     description="API untuk menyukai dan batal menyukai post"
 * )
 */
class LikeController extends Controller
{
    public const ERROR_MESSAGE = "Terjadi kesalahan pada server";

    /**
     * @OA\Post(
     *     path="/api/likes/{postId}",
     *     tags={"Likes"},
     *     summary="Like post",
     *     description="Memberikan like pada sebuah post. Jika sudah pernah like, tidak membuat duplikasi.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="postId",
     *         in="path",
     *         required=true,
     *         description="UUID dari post yang ingin di-like",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil melakukan like"
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Internal Server Error")
     * )
     */
    public function like($postId)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            Like::firstOrCreate([
                'user_id' => $user->id,
                'post_id' => $postId
            ]);

            return ResponseResource::json(200, 'success', 'Liked');
        } catch (Exception $errorException) {
            return ResponseResource::json(500, 'error', self::ERROR_MESSAGE, [
                'error' => $errorException->getMessage()
            ]);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/likes/{postId}",
     *     tags={"Likes"},
     *     summary="Unlike post",
     *     description="Menghapus like dari sebuah post jika sebelumnya sudah like.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="postId",
     *         in="path",
     *         required=true,
     *         description="UUID dari post yang ingin di-unlike",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil melakukan unlike"
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Internal Server Error")
     * )
     */
    public function unlike($postId)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            Like::where('user_id', $user->id)
                ->where('post_id', $postId)
                ->delete();

            return ResponseResource::json(200, 'success', 'Unliked');
        } catch (Exception $errorException) {
            return ResponseResource::json(500, 'error', self::ERROR_MESSAGE, [
                'error' => $errorException->getMessage()
            ]);
        }
    }
}
