<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'request_hash', 'response'];
    
    public $timestamps = false;
    
    /**
     * Check if a request with this key and hash was already processed
     */
    public static function existsForRequest($key, $requestHash)
    {
        return self::where('key', $key)
            ->where('request_hash', $requestHash)
            ->exists();
    }
    
    /**
     * Store response for idempotent request
     */
    public static function storeResponse($key, $requestHash, $response)
    {
        self::create([
            'key' => $key,
            'request_hash' => $requestHash,
            'response' => $response,
        ]);
    }
    
    /**
     * Get stored response for a key and hash
     */
    public static function getResponse($key, $requestHash)
    {
        $record = self::where('key', $key)
            ->where('request_hash', $requestHash)
            ->first();
        
        return $record ? $record->response : null;
    }
}