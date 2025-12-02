<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = ['hold_id', 'product_id', 'quantity', 'amount', 'status'];
    
    /**
     * Relationships
     */
    public function hold()
    {
        return $this->belongsTo(Hold::class);
    }
    
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    
    /**
     * Mark order as paid
     */
    public function markAsPaid()
    {
        $this->update(['status' => 'paid']);
        // Hold remains as used (no need to change)
    }
    
    /**
     * Cancel order and release the hold
     */
    public function cancel()
    {
        $this->update(['status' => 'cancelled']);
        
        // Mark hold as used so stock is released
        if ($this->hold) {
            $this->hold->update(['is_used' => true]);
        }
        
        // Clear product cache
        Product::clearStockCache($this->product_id);
    }
}