<?php

namespace App\Http\Controllers\Social\User;

use App\Http\Controllers\Controller;
use App\Models\Follow;
use App\Models\User;
use App\Http\Resources\ResponseResource;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Exception;

/**
 * @OA\Tag(
 *     name="Follow",
 *     description="Follow and Unfollow user"
 * )
 */
class FollowController extends Controller
{
    public const ERROR_MESSAGE = "Terjadi kesalahan pada server";

    /**
     * @OA\Post(
     *     path="/api/follow/{id}",
     *     tags={"Follow"},
     *     summary="Follow a user",
     *     description="Follow User.",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="UUID of user to follow",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *
     *     @OA\Response(response=200, description="Followed"),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="User not found"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function follow($id)
    {
        try {
            $response = null;
            $me = JWTAuth::parseToken()->authenticate();

            if ($me->id == $id) {
                $response = ResponseResource::json(400, 'error', 'Tidak bisa follow diri sendiri');
            }

            $target = User::find($id);
            if (!$target) {
                $response = ResponseResource::json(404, 'error', 'User tidak ditemukan');
            } else {
                Follow::firstOrCreate([
                    'user_id' => $id,
                    'follower_id' => $me->id,
                ]);

                $response = ResponseResource::json(200, 'success', 'Followed');
            }

            return $response;

        } catch (Exception $e) {
            return ResponseResource::json(500, 'error', self::ERROR_MESSAGE, $e->getMessage());
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/follow/{id}",
     *     tags={"Follow"},
     *     summary="Unfollow a user",
     *     description="Unfollow User.",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="UUID of user to unfollow",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *
     *     @OA\Response(response=200, description="Unfollowed"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function unfollow($id)
    {
        try {
            $me = JWTAuth::parseToken()->authenticate();

            Follow::where('user_id', $id)
                ->where('follower_id', $me->id)
                ->delete();

            return ResponseResource::json(200, 'success', 'Unfollowed');

        } catch (Exception $e) {
            return ResponseResource::json(500, 'error', self::ERROR_MESSAGE, $e->getMessage());
        }
    }
}
