<?php

namespace App\Http\Controllers\Social\Post;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Http\Resources\ResponseResource;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Exception;

/**
 * @OA\Tag(
 *     name="Posts",
 *     description="API untuk mengelola postingan (CRUD)"
 * )
 */
class PostController extends Controller
{
    public const ERROR_MESSAGE = "Terjadi kesalahan pada server";

    /**
     * @OA\Get(
     *     path="/api/posts",
     *     tags={"Posts"},
     *     summary="List semua post",
     *     description="Mengambil semua postingan beserta media.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(response=200, description="Berhasil mengambil list post"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Internal Server Error")
     * )
     */
    public function index()
    {
        try {
            $posts = Post::with([
                'media',
                'likes',
                'user',
                'comments' => function ($q) { $q->with('user')->orderBy('created_at', 'desc'); }
            ])->latest()->get();
            return ResponseResource::json(200, 'success', 'List post', $posts);
        } catch (Exception $errorException) {
            return ResponseResource::json(500, 'error', self::ERROR_MESSAGE, [
                'error' => $errorException->getMessage()
            ]);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/posts",
     *     tags={"Posts"},
     *     summary="Buat post baru",
     *     description="Membuat postingan baru oleh user yang login.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="caption", type="string", example="Liburan hari ini!")
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Berhasil membuat post"),
     *     @OA\Response(response=400, description="Bad Request"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Internal Server Error")
     * )
     */
    public function store(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            $post = Post::create([
                'user_id' => $user->id,
                'caption' => $request->caption,
            ]);

            return ResponseResource::json(200, 'success', 'Post created', $post);
        } catch (Exception $errorException) {
            return ResponseResource::json(500, 'error', self::ERROR_MESSAGE, [
                'error' => $errorException->getMessage()
            ]);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/posts/{id}",
     *     tags={"Posts"},
     *     summary="Detail post",
     *     description="Mengambil detail post beserta media.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="UUID post",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *
     *     @OA\Response(response=200, description="Berhasil ambil detail post"),
     *     @OA\Response(response=404, description="Post tidak ditemukan"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Internal Server Error")
     * )
     */
    public function show($id)
    {
        try {
            $posts = Post::with([
                'media',
                'likes',
                'user',
                'comments' => function ($q) { $q->with('user')->orderBy('created_at', 'desc'); }
            ])->find($id);
            if (!$posts) {
                return ResponseResource::json(404, 'error', 'Post not found', null);
            }
            return ResponseResource::json(200, 'success', 'Post by ID', $posts);
        } catch (Exception $errorException) {
            return ResponseResource::json(500, 'error', self::ERROR_MESSAGE, [
                'error' => $errorException->getMessage()
            ]);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/posts/{id}",
     *     tags={"Posts"},
     *     summary="Update post",
     *     description="Hanya pemilik post yang dapat mengupdate postingannya.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="UUID post",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="caption", type="string", example="Update caption baru")
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Berhasil update post"),
     *     @OA\Response(response=403, description="Tidak boleh update post orang lain"),
     *     @OA\Response(response=404, description="Post tidak ditemukan"),
     *     @OA\Response(response=500, description="Internal Server Error")
     * )
     */
    public function update(Request $request, $id)
    {
        try {
            $response = null;
            $user = JWTAuth::parseToken()->authenticate();
            $post = Post::find($id);

            if (!$post) {
                $response = ResponseResource::json(404, 'error', 'Post tidak ditemukan');
            }

            if ($post->user_id !== $user->id) {
                $response = ResponseResource::json(403, 'error', 'Tidak boleh update post orang lain');
            } else {
                $post->update($request->only('caption'));
                $response = ResponseResource::json(200, 'success', 'Post updated', $post);
            }
            return $response;
        } catch (Exception $errorException) {
            return ResponseResource::json(500, 'error', self::ERROR_MESSAGE, [
                'error' => $errorException->getMessage()
            ]);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/posts/{id}",
     *     tags={"Posts"},
     *     summary="Hapus post",
     *     description="Hanya pemilik post yang boleh menghapus post.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="UUID post",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *
     *     @OA\Response(response=200, description="Post berhasil dihapus"),
     *     @OA\Response(response=403, description="Tidak boleh hapus post orang lain"),
     *     @OA\Response(response=404, description="Post tidak ditemukan"),
     *     @OA\Response(response=500, description="Internal Server Error")
     * )
     */
    public function destroy($id)
    {
        try {
            $response = null;
            $user = JWTAuth::parseToken()->authenticate();
            $post = Post::find($id);

            if (!$post) {
                $response = ResponseResource::json(404, 'error', 'Post tidak ditemukan');
            }

            if ($post->user_id !== $user->id) {
                $response = ResponseResource::json(403, 'error', 'Tidak boleh hapus post orang lain');
            } else {
                $post->delete();
                $response = ResponseResource::json(200, 'success', 'Post deleted');
            }
            return $response;
        } catch (Exception $errorException) {
            return ResponseResource::json(500, 'error', self::ERROR_MESSAGE, [
                'error' => $errorException->getMessage()
            ]);
        }
    }
}
