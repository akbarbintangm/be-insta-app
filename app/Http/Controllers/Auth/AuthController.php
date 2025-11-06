<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Follow;
use Illuminate\Support\Facades\Hash;
use App\Http\Resources\ResponseResource;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Cookie;
use Tymon\JWTAuth\Facades\JWTAuth;
use Exception;

/**
 * @OA\Tag(
 *     name="Auth",
 *     description="API Auth"
 * )
 */
class AuthController extends Controller
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
                $response = $this->errorEmailFoundOrNotFound($request, true);
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
            $response = null;
            $request->validate([
                'email' => 'required|string|email',
                'password' => 'required|string',
                'remember' => 'boolean',
            ]);

            $user = User::where('email', $request->email)->first();
            if (!$user) {
                $response = $this->errorEmailFoundOrNotFound($request, false);
            }

            if (!Hash::check($request->password, $user->password)) {
                $response = $this->errorWrongPassword($request);
            } else {
                $response = $this->handleSuccessfulLogin($request, $user);
            }

            return $response;
        } catch (ValidationException $errorValidation) {
            return ResponseResource::json(422, 'error', 'Validasi gagal', $errorValidation->errors());

        } catch (Exception $errorException) {
            return ResponseResource::json(500, 'error', self::ERROR_MESSAGE, [
                'error' => $errorException->getMessage()
            ]);
        }
    }

    private function errorEmailFoundOrNotFound(Request $request, bool $found)
    {
        $message = $found ? 'Email sudah terdaftar' : 'Email tidak ditemukan';
        $code = $found ? 400 : 404;

        return ResponseResource::json(
            $code,
            'error',
            $message,
            ['email' => $request->email]
        );
    }

    private function errorWrongPassword(Request $request)
    {
        return ResponseResource::json(
            401,
            'error',
            'Password salah',
            ['email' => $request->email]
        );
    }

    private function handleSuccessfulLogin(Request $request, User $user)
    {
        $this->setTokenTTL($request);

        $credentials = $request->only('email', 'password');
        $token = JWTAuth::attempt($credentials);

        if (!$token) {
            return ResponseResource::json(401, 'error', 'Email atau password salah', []);
        }

        $refreshToken = $this->handleRememberToken($request, $user);

        $this->queueCookies($token, $refreshToken, $request);

        $userSanitized = $user->only(['id', 'name', 'username', 'email', 'created_at']);

        $followerData = Follow::where('user_id', $userSanitized['id'])->get();

        $followingData = Follow::where('follower_id', $userSanitized['id'])->get();

        return ResponseResource::json(200, 'success', 'Login berhasil', [
            'user' => $userSanitized,
            'follower' => $followerData,
            'following' => $followingData,
            'token' => $token,
            'remember' => (bool) $request->remember
        ]);
    }

    private function setTokenTTL(Request $request)
    {
        if ($request->remember) {
            JWTAuth::factory()->setTTL(60 * 24 * 7);
        } else {
            JWTAuth::factory()->setTTL(60);
        }
    }

    private function handleRememberToken(Request $request, User $user)
    {
        if ($request->remember) {
            $refreshToken = bin2hex(random_bytes(32));
            $user->remember_token = $refreshToken;
        } else {
            $refreshToken = null;
            $user->remember_token = null;
        }

        $user->save();
        return $refreshToken;
    }

    private function queueCookies($token, $refreshToken, Request $request)
    {
        $ttl = JWTAuth::factory()->getTTL();

        Cookie::queue(cookie(
            'token',
            $token,
            $ttl,
            httpOnly: true,
            secure: true,
            sameSite: 'Strict'
        ));

        if ($request->remember) {
            Cookie::queue(cookie(
                'refresh_token',
                $refreshToken,
                60 * 24 * 7,
                httpOnly: true,
                secure: true,
                sameSite: 'Strict'
            ));
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
        $response = null;
        try {
            // Ambil refresh token dari cookie
            $refreshToken = $request->cookie('refresh_token');

            if (!$refreshToken) {
                $response = ResponseResource::json(400, 'error', 'Refresh token tidak ditemukan', null);
            }

            // Cari user berdasarkan refresh token
            $user = User::where('remember_token', $refreshToken)->first();

            if (!$user) {
                $response = ResponseResource::json(401, 'error', 'Refresh token tidak valid atau kedaluwarsa', null);
            } else {
                // Buat access token JWT baru
                $newJwt = JWTAuth::fromUser($user);

                // Buat refresh token baru
                $newRefresh = bin2hex(random_bytes(32));
                $user->remember_token = $newRefresh;
                $user->save();

                // Set cookie access token (httpOnly)
                Cookie::queue(cookie(
                    'token',
                    $newJwt,
                    60, // 1 jam
                    '/',
                    null,
                    true,       // secure
                    true,       // httpOnly
                    false,
                    'Strict'
                ));

                // Set cookie refresh token baru
                Cookie::queue(cookie(
                    'refresh_token',
                    $newRefresh,
                    60 * 24 * 7, // 7 hari
                    '/',
                    null,
                    true,
                    true,
                    false,
                    'Strict'
                ));

                $userSanitized = $user->only([
                    'id',
                    'name',
                    'username',
                    'email',
                    'created_at',
                ]);

                $response = ResponseResource::json(200, 'success', 'Token berhasil diperbarui', [
                    'token' => $newJwt,
                    'user'  => $userSanitized,
                    'remember' => true
                ]);
            }
            return $response;
        } catch (Exception $e) {
            return ResponseResource::json(500, 'error', self::ERROR_MESSAGE, [
                'error' => $e->getMessage()
            ]);
        }
    }
}
