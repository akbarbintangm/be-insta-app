<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Auth\AuthController;

use App\Http\Controllers\Social\User\UserController;
use App\Http\Controllers\Social\User\UserProfileController;
use App\Http\Controllers\Social\User\FollowController;

use App\Http\Controllers\Social\Post\PostController;
use App\Http\Controllers\Social\Post\PostMediaController;
use App\Http\Controllers\Social\Post\LikeController;
use App\Http\Controllers\Social\Post\CommentController;

use App\Http\Controllers\SearchController;

Route::controller(AuthController::class)->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/register', 'register');
        Route::post('/login', 'login');
        Route::middleware('auth:api')->group(function () {
            Route::post('/logout', 'logout');
            Route::post('/refresh', 'refresh');
        });
        // Route::post('/logout', 'logout');
        // Route::post('/refresh', 'refresh');
    });
});

Route::middleware('auth:api')->group(function () {
    // USER + PROFILE
    Route::prefix('users')->controller(UserController::class)->group(function () {
        Route::get('/', 'index');
        Route::get('/me', 'me');
        Route::get('/{id}', 'show');
    });

    // PROFILE
    Route::prefix('profile')->controller(UserProfileController::class)->group(function () {
        Route::get('/{userId}', 'show');
        // TODO Route::get('/{username}', 'show');
        Route::put('/', 'update');
    });

    // FOLLOW
    Route::prefix('follow')->controller(FollowController::class)->group(function () {
        Route::post('/{id}', 'follow');
        // TODO by username
        Route::delete('/{id}', 'unfollow');
        // TODO by username
    });

    // POSTS
    Route::prefix('posts')->controller(PostController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{id}', 'show');
        Route::put('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
    });

    // POST MEDIA
    Route::prefix('post-media')->controller(PostMediaController::class)->group(function () {
        Route::post('/{postId}', 'upload');
    });

    // LIKE
    Route::prefix('likes')->controller(LikeController::class)->group(function () {
        Route::post('/{postId}', 'like');
        Route::delete('/{postId}', 'unlike');
    });

    // COMMENT
    Route::prefix('comments')->controller(CommentController::class)->group(function () {
        Route::post('/{postId}', 'store');
        Route::get('/{postId}', 'index');
        // TODO Route::update('/{postId}', 'update');
        Route::delete('/{postId}/{commentId}', 'delete');
    });
});