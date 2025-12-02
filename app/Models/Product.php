<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Product extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'price', 'total_stock'];
    
    protected $appends = ['available_stock'];
    
    /**
     * Calculate available stock considering holds and orders
     */
    public function getAvailableStockAttribute()
    {
        $cacheKey = "product_{$this->id}_available_stock";
        $cacheTime = 5; // seconds
        
        return Cache::remember($cacheKey, $cacheTime, function () {
            // Sum of active holds (not expired, not used)
            $activeHolds = Hold::where('product_id', $this->id)
                ->where('expires_at', '>', now())
                ->where('is_used', false)
                ->sum('quantity');
            
            // Sum of paid orders
            $paidOrders = Order::where('product_id', $this->id)
                ->where('status', 'paid')
                ->sum('quantity');
            
            return max(0, $this->total_stock - $activeHolds - $paidOrders);
        });
    }
    
    /**
     * Invalidate stock cache when something changes
     */
    public static function clearStockCache($productId)
    {
        Cache::forget("product_{$productId}_available_stock");
    }
    
    /**
     * Relationships
     */
    public function holds()
    {
        return $this->hasMany(Hold::class);
    }
    
    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}