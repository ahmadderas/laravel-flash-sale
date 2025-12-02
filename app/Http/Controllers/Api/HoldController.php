<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hold;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HoldController extends Controller
{
    /**
     * Create a temporary hold/reservation
     */
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1|max:10', // Limit max quantity
        ]);
        
        $productId = $request->input('product_id');
        $quantity = $request->input('quantity');
        
        try {
            DB::beginTransaction();
            
            // Lock the product row to prevent race conditions
            $product = Product::where('id', $productId)
                ->lockForUpdate()
                ->firstOrFail();
            
            // Calculate available stock
            $availableStock = $product->available_stock;
            
            if ($availableStock < $quantity) {
                DB::rollBack();
                return response()->json([
                    'error' => 'Insufficient stock available',
                    'available_stock' => $availableStock
                ], 422);
            }
            
            // Create hold
            $hold = Hold::create([
                'product_id' => $productId,
                'quantity' => $quantity,
                'hold_token' => Hold::generateToken(),
                'expires_at' => now()->addMinutes(2), // 2 minute hold
                'is_used' => false,
            ]);
            
            // Clear cache for this product
            Product::clearStockCache($productId);
            
            DB::commit();
            
            Log::info('Hold created', [
                'hold_id' => $hold->id,
                'product_id' => $productId,
                'quantity' => $quantity,
            ]);
            
            return response()->json([
                'data' => [
                    'hold_id' => $hold->id,
                    'hold_token' => $hold->hold_token,
                    'expires_at' => $hold->expires_at,
                    'quantity' => $hold->quantity,
                    'product_id' => $hold->product_id,
                ]
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Hold creation failed', [
                'error' => $e->getMessage(),
                'product_id' => $productId,
                'quantity' => $quantity,
            ]);
            
            return response()->json([
                'error' => 'Failed to create hold. Please try again.'
            ], 500);
        }
    }
}