<?php

namespace App\Http\Controllers\Social\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserProfile;
use App\Http\Resources\ResponseResource;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Exception;

/**
 * @OA\Tag(
 *     name="User Profile",
 *     description="User profile information and updates"
 * )
 */
class UserProfileController extends Controller
{
    public const ERROR_MESSAGE = "Terjadi kesalahan pada server";

    /**
     * @OA\Put(
     *     path="/api/profile",
     *     tags={"User Profile"},
     *     summary="Update user profile",
     *     security={{"bearerAuth": {}}},
     * 
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="bio", type="string", example="This is my bio."),
     *             @OA\Property(property="website", type="string", example="https://example.com"),
     *             @OA\Property(property="avatar", type="string", example="/uploads/avatar.jpg")
     *         )
     *     ),
     * 
     *     @OA\Response(response=200, description="Profile updated"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function update(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            $profile = UserProfile::firstOrCreate(['user_id' => $user->id]);

            $profile->update($request->only(['bio', 'website', 'avatar']));

            return ResponseResource::json(200, 'success', 'Profile updated', $profile);

        } catch (Exception $e) {
            return ResponseResource::json(500, 'error', self::ERROR_MESSAGE, $e->getMessage());
        }
    }

    /**
     * @OA\Get(
     *     path="/api/profile/{id}",
     *     tags={"User Profile"},
     *     summary="Get user profile detail",
     *     security={{"bearerAuth": {}}},
     * 
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="UUID of user",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     * 
     *     @OA\Response(response=200, description="User profile fetched"),
     *     @OA\Response(response=404, description="User not found"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function show($id)
    {
        try {
            $user = User::with('profile')->find($id);

            if (!$user) {
                return ResponseResource::json(404, 'error', 'User tidak ditemukan');
            }

            return ResponseResource::json(200, 'success', 'Detail user', $user);

        } catch (Exception $e) {
            return ResponseResource::json(500, 'error', self::ERROR_MESSAGE, $e->getMessage());
        }
    }
}
