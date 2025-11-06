<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Tymon\JWTAuth\Contracts\JWTSubject;

class PostMedia extends Model
{
    use HasUuids;

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    protected $table = 'post_medias';

    protected $fillable = [
        'post_id',
        'media_url',
        'type',
    ];

    public function post() {
        return $this->belongsTo(Post::class);
    }
}
