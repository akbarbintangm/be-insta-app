<?php

namespace App\Http\Controllers\Social\Post;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostMedia;
use App\Http\Resources\ResponseResource;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

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
            $response = null;
            $user = JWTAuth::parseToken()->authenticate();
            $post = Post::find($postId);
            if (!$post) {
                Log::warning("Post not found: {$postId}");
                $response = ResponseResource::json(404, 'error', 'Post tidak ditemukan');
            }
            if ($post->user_id !== $user->id) {
                Log::warning("User {$user->id} mencoba upload ke post {$postId} milik user lain");
                $response = ResponseResource::json(403, 'error', 'Tidak boleh upload ke post orang lain');
            }
            if (!$request->hasFile('file')) {
                Log::warning("File tidak ditemukan di request oleh user {$user->id}");
                $response = ResponseResource::json(400, 'error', 'File tidak ditemukan');
            }
            $file = $request->file('file');
            $request->validate([
                'file' => 'required|file|mimes:jpg,jpeg,png,mp4,mov,avi'
            ]);

            Storage::disk('public')->makeDirectory('post-media');
            Storage::disk('public')->makeDirectory('post-media/' . $user->id);
            $filename = $postId . $file->getClientOriginalName();
            $path = Storage::disk('public')->putFileAs('post-media/' . $user->id, $file, $filename);

            if (empty($path)) {
                Log::error("Path kosong setelah store() untuk file: {$file->getClientOriginalName()}");
                $response = ResponseResource::json(500, 'error', 'Gagal menyimpan file, path kosong');
            } else {
                $media = PostMedia::create([
                    'post_id' => $postId,
                    'media_url' => $path,
                    'type' => $request->type ?? 'image',
                ]);
                $response = ResponseResource::json(200, 'success', 'Media uploaded', $media);
            }
            return $response;
        } catch (Exception $e) {
            Log::error("Upload failed for postId {$postId}: " . $e->getMessage(), [
                'stack' => $e->getTraceAsString()
            ]);
            return ResponseResource::json(500, 'error', self::ERROR_MESSAGE, $e->getMessage());
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/post-media/{postId}",
     *     tags={"Post Media"},
     *     summary="Hapus post media",
     *     description="Hanya pemilik post media yang boleh menghapus post media.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="UUID post media",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *
     *     @OA\Response(response=200, description="Post media berhasil dihapus"),
     *     @OA\Response(response=403, description="Tidak boleh hapus post media orang lain"),
     *     @OA\Response(response=404, description="Post media tidak ditemukan"),
     *     @OA\Response(response=500, description="Internal Server Error")
     * )
     */
    public function destroy($id)
    {
        try {
            $response = null;
            $user = JWTAuth::parseToken()->authenticate();
            $post = PostMedia::where('post_id', $id);

            if (!$post) {
                $response = ResponseResource::json(404, 'error', 'Post Media tidak ditemukan');
            }

            if ($post->user_id !== $user->id) {
                $response = ResponseResource::json(403, 'error', 'Tidak boleh hapus post media orang lain');
            } else {
                Storage::disk('public')->delete($post->media_url);
                $post->delete();
                $response = ResponseResource::json(200, 'success', 'Post Media deleted');
            }
            return $response;
        } catch (Exception $errorException) {
            return ResponseResource::json(500, 'error', self::ERROR_MESSAGE, [
                'error' => $errorException->getMessage()
            ]);
        }
    }
}
