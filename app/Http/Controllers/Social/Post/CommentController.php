<?php

namespace App\Http\Controllers\Social\Post;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Http\Resources\ResponseResource;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Exception;

/**
 * @OA\Tag(
 *     name="Comments",
 *     description="API untuk mengelola comments dan replies pada post"
 * )
 */
class CommentController extends Controller
{
    public const ERROR_MESSAGE = "Terjadi kesalahan pada server";

    /**
     * @OA\Get(
     *     path="/api/comments/{postId}",
     *     tags={"Comments"},
     *     summary="List semua comments pada suatu post",
     *     description="Mengambil semua comments beserta replies untuk post tertentu.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="postId",
     *         in="path",
     *         required=true,
     *         description="UUID post",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mengambil daftar comments"
     *     ),
     *     @OA\Response(response=400, description="Bad Request"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Internal Server Error")
     * )
     */
    public function index($postId)
    {
        try {
            $comments = Comment::where('post_id', $postId)
                ->with('replies')
                ->latest()
                ->get();

            return ResponseResource::json(200, 'success', 'List comments', $comments);
        } catch (Exception $errorException) {
            return ResponseResource::json(500, 'error', self::ERROR_MESSAGE, [
                'error' => $errorException->getMessage()
            ]);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/comments/{postId}",
     *     tags={"Comments"},
     *     summary="Membuat comment baru pada post",
     *     description="Menambah comment baru atau reply (jika parent_id diisi) untuk post tertentu.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="postId",
     *         in="path",
     *         required=true,
     *         description="UUID post",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"comment"},
     *             @OA\Property(property="comment", type="string", example="Keren banget!"),
     *             @OA\Property(property="parent_id", type="string", nullable=true, example=null)
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Comment berhasil dibuat"),
     *     @OA\Response(response=400, description="Bad Request"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Internal Server Error")
     * )
     */
    public function store(Request $request, $postId)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            $comment = Comment::create([
                'user_id'   => $user->id,
                'post_id'   => $postId,
                'comment'   => $request->comment,
                'parent_id' => $request->parent_id,
            ]);

            return ResponseResource::json(200, 'success', 'Comment created', $comment);
        } catch (Exception $errorException) {
            return ResponseResource::json(500, 'error', self::ERROR_MESSAGE, [
                'error' => $errorException->getMessage()
            ]);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/comments/{postId}/{commentId}",
     *     tags={"Comments"},
     *     summary="Menghapus comment pada post",
     *     description="Menghapus comment utama atau reply berdasarkan UUID.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="postId",
     *         in="path",
     *         required=true,
     *         description="UUID post",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="commentId",
     *         in="path",
     *         required=true,
     *         description="UUID comment",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *
     *     @OA\Response(response=200, description="Comment berhasil dihapus"),
     *     @OA\Response(response=400, description="Bad Request"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Internal Server Error")
     * )
     */
    public function delete($postId, $commentId)
    {
        try {
            $deleted = Comment::where('id', $commentId)
                ->where('post_id', $postId)
                ->delete();

            return ResponseResource::json(200, 'success', 'Comment deleted', $deleted);
        } catch (Exception $errorException) {
            return ResponseResource::json(500, 'error', self::ERROR_MESSAGE, [
                'error' => $errorException->getMessage()
            ]);
        }
    }

    // TODO update comment
}
