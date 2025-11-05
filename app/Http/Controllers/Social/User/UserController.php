<?php

namespace App\Http\Controllers\Social\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Http\Resources\ResponseResource;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Exception;

/**
 * @OA\Tag(
 *     name="Users",
 *     description="User list, detail, and authenticated user"
 * )
 */
class UserController extends Controller
{
    public const ERROR_MESSAGE = "Terjadi kesalahan pada server";

    /**
     * @OA\Get(
     *     path="/api/users",
     *     tags={"Users"},
     *     summary="Get list of users",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="List of users"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function index()
    {
        try {
            $users = User::all();
            return ResponseResource::json(200, 'success', 'List user', $users);
        } catch (Exception $e) {
            return ResponseResource::json(500, 'error', self::ERROR_MESSAGE, $e->getMessage());
        }
    }

    /**
     * @OA\Get(
     *     path="/api/users/me",
     *     tags={"Users"},
     *     summary="Get authenticated user info",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Authenticated user data"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function me()
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            return ResponseResource::json(200, 'success', 'User authenticated', $user);
        } catch (Exception $e) {
            return ResponseResource::json(500, 'error', self::ERROR_MESSAGE, $e->getMessage());
        }
    }

    /**
     * @OA\Get(
     *     path="/api/users/{id}",
     *     tags={"Users"},
     *     summary="Get detail of a user",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="UUID of the user",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *
     *     @OA\Response(response=200, description="Detail user"),
     *     @OA\Response(response=404, description="User not found"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function show($id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return ResponseResource::json(404, 'error', 'User tidak ditemukan');
            }

            return ResponseResource::json(200, 'success', 'Detail user', $user);

        } catch (Exception $e) {
            return ResponseResource::json(500, 'error', self::ERROR_MESSAGE, $e->getMessage());
        }
    }

    /**
     * @OA\Put(
     *     path="/api/users/username/change",
     *     tags={"Users"},
     *     summary="Menganti username pengguna yang terautentikasi",
     *     description="Endpoint ini memungkinkan pengguna yang terautentikasi untuk mengubah username mereka. Pastikan username baru belum digunakan oleh pengguna lain.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"username"},
     *             @OA\Property(property="username", type="string", example="username_new", description="Username baru yang diinginkan"),
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Berhasil mengubah username"),
     *     @OA\Response(response=400, description="Bad Request"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Internal Server Error")
     * )
     */
    public function updateUsername(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            $username = User::where('id', $user->id)->update([
                'username' => $request->input('username')
            ]);

            return ResponseResource::json(200, 'success', 'Username updated', $username);
        } catch (Exception $errorException) {
            return ResponseResource::json(500, 'error', self::ERROR_MESSAGE, [
                'error' => $errorException->getMessage()
            ]);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/users/username/check",
     *     tags={"Users"},
     *     summary="Check username pengguna tersedia atau tidak",
     *     description="Endpoint ini memungkinkan untuk memeriksa apakah username yang diinginkan sudah digunakan oleh pengguna lain atau belum.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"username"},
     *             @OA\Property(property="username", type="string", example="username_new", description="Username yang ingin diperiksa"),
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Berhasil mengubah username"),
     *     @OA\Response(response=400, description="Bad Request"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Internal Server Error")
     * )
     */
    public function checkUsername(Request $request)
    {
        $response = null;
        try {
            $username = $request->input('username');
            $isUsernameExist = User::where('username', $username)->exists();
            if ($isUsernameExist) {
                $response = ResponseResource::json(400, 'success', 'Username sudah digunakan', $isUsernameExist);
            } else {
                $response = ResponseResource::json(200, 'success', 'Username tersedia', $isUsernameExist);
            }
            return $response;
        } catch (Exception $errorException) {
            return ResponseResource::json(500, 'error', self::ERROR_MESSAGE, [
                'error' => $errorException->getMessage()
            ]);
        }
    }
}
