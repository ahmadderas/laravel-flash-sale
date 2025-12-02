<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Hold extends Model
{
    use HasFactory;

    protected $fillable = ['product_id', 'quantity', 'hold_token', 'expires_at', 'is_used'];
    
    protected $casts = [
        'expires_at' => 'datetime',
        'is_used' => 'boolean',
    ];
    
    /**
     * Generate a unique hold token
     */
    public static function generateToken()
    {
        do {
            $token = 'hold_' . Str::random(32);
        } while (self::where('hold_token', $token)->exists());
        
        return $token;
    }
    
    /**
     * Check if hold is still valid (not expired and not used)
     */
    public function isValid()
    {
        return !$this->is_used && $this->expires_at->isFuture();
    }
    
    /**
     * Relationships
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    
    public function order()
    {
        return $this->hasOne(Order::class);
    }
}