<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Http\Resources\ResponseResource;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Cookie;
use Exception;

class LoginController extends Controller
{
    public const ERROR_MESSAGE = "Terjadi kesalahan pada server";

    /**
     * @OA\Post(
     *     path="/api/auth/register",
     *     tags={"Auth"},
     *     summary="Registrasi user baru",
     *     description="Membuat akun baru dan langsung mengembalikan data user tanpa JWT",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "email", "password"},
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", example="user@mail.com"),
     *             @OA\Property(property="password", type="string", example="password123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Berhasil registrasi user baru",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", type="object")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Validasi gagal")
     * )
     */
    public function register(Request $request)
    {
        $response = null;
        try {
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255',
                'password' => 'required|string|min:6',
            ]);

            $checkUser = User::where('email', $validatedData['email'])->first();
            if ($checkUser) {
                $response = ResponseResource::json(400, 'error', 'Email sudah terdaftar', ['email' => $validatedData['email']]);
            } else {
                $user = User::create([
                    'name' => $validatedData['name'],
                    'email' => $validatedData['email'],
                    'password' => Hash::make($validatedData['password']),
                ]);
                $response = ResponseResource::json(200, 'success', 'Register berhasil', $user);
            }
            return $response;
        } catch (Exception $errorException) {
            return ResponseResource::json(500, 'error', self::ERROR_MESSAGE, ['error' => $errorException->getMessage()]);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/auth/login",
     *     tags={"Auth"},
     *     summary="Login dan mendapatkan JWT token",
     *     description="Endpoint untuk login user dan mendapatkan token JWT, disimpan dalam cookie http-only",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", example="user@mail.com"),
     *             @OA\Property(property="password", type="string", example="password123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil login",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", type="object"),
     *                 @OA\Property(property="token", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Bad Request"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Internal Server Error")
     * )
     */
    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|string|email',
                'password' => 'required|string',
            ]);

            $user = User::where('email', $request->email)->first();
            if (!$user) {
                return ResponseResource::json(404, 'error', 'Email tidak ditemukan', ['email' => $request->email]);
            }

            if (!Hash::check($request->password, $user->password)) {
                return ResponseResource::json(401, 'error', 'Password salah', ['email' => $request->email]);
            }

            $token = bin2hex(random_bytes(32));
            $refreshToken = bin2hex(random_bytes(32));
            $user->remember_token = $refreshToken;
            $user->save();

            Cookie::queue(cookie('token', $token, 60 * 24, null, null, true, true, false, 'Strict'));
            Cookie::queue(cookie('refresh_token', $refreshToken, 60 * 24 * 7, null, null, true, true, false, 'Strict'));

            $data = [
                'user' => $user,
                'token' => $token,
            ];

            return ResponseResource::json(200, 'success', 'Login berhasil', $data);
        } catch (ValidationException $errorValidation) {
            return ResponseResource::json(500, 'error', 'Validasi gagal', $errorValidation->errors());
        } catch (Exception $errorException) {
            return ResponseResource::json(500, 'error', self::ERROR_MESSAGE, ['error' => $errorException->getMessage()]);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/auth/logout",
     *     tags={"Auth"},
     *     summary="Logout",
     *     description="Menghapus cookie JWT dan refresh_token",
     *     @OA\Response(
     *         response=200,
     *         description="Logout berhasil",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Logout berhasil")
     *         )
     *     )
     * )
     */
    public function logout(Request $request)
    {
        try {
            $user = Auth::user();
            if ($user) {
                $user->remember_token = null;
                $user->save();
            }

            Cookie::queue(Cookie::forget('token'));
            Cookie::queue(Cookie::forget('refresh_token'));
            Auth::logout();

            return ResponseResource::json(200, 'success', 'Logout berhasil', null);
        } catch (Exception $errorException) {
            return ResponseResource::json(500, 'error', self::ERROR_MESSAGE, ['error' => $errorException->getMessage()]);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/auth/refresh",
     *     tags={"Auth"},
     *     summary="Refresh Token",
     *     description="Memperbarui access token menggunakan refresh token dari cookie",
     *     @OA\Response(
     *         response=200,
     *         description="Token berhasil diperbarui",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Token berhasil diperbarui"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="token", type="string", example="new.jwt.token")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Refresh token tidak ditemukan"),
     *     @OA\Response(response=401, description="Refresh token tidak valid atau kedaluwarsa"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     */
    public function refresh(Request $request)
    {
        try {
            $refreshToken = $request->cookie('refresh_token');
            if (!$refreshToken) {
                return ResponseResource::json(400, 'error', 'Refresh token tidak ditemukan', null);
            }
            $user = User::where('remember_token', $refreshToken)->first();
            if (!$user) {
                return ResponseResource::json(401, 'error', 'Refresh token tidak valid atau kedaluwarsa', null);
            }
            $newToken = bin2hex(random_bytes(32));
            $newRefreshToken = bin2hex(random_bytes(32));
            $user->remember_token = $newRefreshToken;
            $user->save();

            Cookie::queue(cookie('token', $newToken, 60 * 24, null, null, true, true, false, 'Strict'));
            Cookie::queue(cookie('refresh_token', $newRefreshToken, 60 * 24 * 7, null, null, true, true, false, 'Strict'));

            return ResponseResource::json(200, 'success', 'Token berhasil diperbarui', ['token' => $newToken]);
        } catch (Exception $errorException) {
            return ResponseResource::json(500, 'error', self::ERROR_MESSAGE, ['error' => $errorException->getMessage()]);
        }
    }
}
