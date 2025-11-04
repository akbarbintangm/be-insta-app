<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *     title="InstaApp API",
 *     version="1.0.0",
 *     description="API backend untuk aplikasi InstaApp",
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
*/
abstract class Controller
{
    
}
