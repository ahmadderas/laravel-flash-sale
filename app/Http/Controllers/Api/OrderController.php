<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hold;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Create an order from a hold
     */
    public function store(Request $request)
    {
        $request->validate([
            'hold_token' => 'required|string|exists:holds,hold_token',
        ]);
        
        $holdToken = $request->input('hold_token');
        
        DB::beginTransaction();
        
        try {
            // Find the hold and lock it
            $hold = Hold::where('hold_token', $holdToken)
                ->lockForUpdate()
                ->firstOrFail();
            
            // Validate hold
            if ($hold->is_used) {
                DB::rollBack();
                return response()->json([
                    'error' => 'This hold has already been used'
                ], 422);
            }
            
            if ($hold->expires_at->isPast()) {
                DB::rollBack();
                
                // Auto-mark as used if expired
                $hold->update(['is_used' => true]);
                Product::clearStockCache($hold->product_id);
                
                return response()->json([
                    'error' => 'Hold has expired'
                ], 422);
            }
            
            // Get product price
            $product = Product::findOrFail($hold->product_id);
            
            // Create order
            $order = Order::create([
                'hold_id' => $hold->id,
                'product_id' => $hold->product_id,
                'quantity' => $hold->quantity,
                'amount' => $hold->quantity * $product->price,
                'status' => 'pending_payment',
            ]);
            
            // Mark hold as used
            $hold->update(['is_used' => true]);
            
            // Clear product cache
            Product::clearStockCache($hold->product_id);
            
            DB::commit();
            
            return response()->json([
                'data' => [
                    'order_id' => $order->id,
                    'hold_id' => $order->hold_id,
                    'product_id' => $order->product_id,
                    'quantity' => $order->quantity,
                    'amount' => $order->amount,
                    'status' => $order->status,
                    'created_at' => $order->created_at,
                ]
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'error' => 'Failed to create order: ' . $e->getMessage()
            ], 500);
        }
    }
}