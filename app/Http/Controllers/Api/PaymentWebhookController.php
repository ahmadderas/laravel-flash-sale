<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IdempotencyKey;
use App\Models\Order;
use App\Models\PendingWebhook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentWebhookController extends Controller
{
    /**
     * Handle payment webhook (idempotent)
     */
    public function handleWebhook(Request $request)
    {
        // Get idempotency key from header
        $idempotencyKey = $request->header('Idempotency-Key');
        
        if (!$idempotencyKey) {
            return response()->json([
                'error' => 'Idempotency-Key header is required'
            ], 400);
        }
        
        // Create hash of the request content
        $requestHash = hash('sha256', $request->getContent());
        
        // Check if we've already processed this exact request
        $cachedResponse = IdempotencyKey::getResponse($idempotencyKey, $requestHash);
        
        if ($cachedResponse) {
            Log::info('Returning cached response for idempotency key', ['key' => $idempotencyKey]);
            return response()->json(json_decode($cachedResponse, true));
        }
        
        // Validate webhook payload
        $validated = $request->validate([
            'order_id' => 'required|integer',
            'payment_id' => 'required|string',
            'status' => 'required|in:paid,failed,cancelled',
            'amount' => 'required|numeric',
            'currency' => 'required|string|size:3',
        ]);
        
        $orderId = $validated['order_id'];
        $status = $validated['status'];
        
        DB::beginTransaction();
        
        try {
            // Handle webhook arriving before order creation
            if (!Order::where('id', $orderId)->exists()) {
                Log::warning('Webhook received for non-existent order', [
                    'order_id' => $orderId,
                    'payment_id' => $validated['payment_id'],
                ]);
                
                // Store as pending webhook to process later
                PendingWebhook::create([
                    'order_id' => $orderId,
                    'payment_id' => $validated['payment_id'],
                    'status' => $status,
                    'payload' => json_encode($validated),
                    'processed' => false,
                ]);
                
                $response = [
                    'status' => 'queued',
                    'message' => 'Order not found yet. Webhook queued for later processing.',
                    'order_id' => $orderId,
                ];
                
                IdempotencyKey::storeResponse($idempotencyKey, $requestHash, json_encode($response));
                DB::commit();
                
                return response()->json($response);
            }
            
            // Normal processing - order exists
            $order = Order::where('id', $orderId)->lockForUpdate()->firstOrFail();
            
            // Prevent duplicate processing
            if ($order->status === 'paid' && $status === 'paid') {
                $response = [
                    'status' => 'already_processed',
                    'message' => 'Order is already paid',
                    'order_id' => $orderId,
                ];
                
                IdempotencyKey::storeResponse($idempotencyKey, $requestHash, json_encode($response));
                DB::commit();
                
                return response()->json($response);
            }
            
            // Process based on status
            if ($status === 'paid') {
                // Verify amount matches
                if ($order->amount != $validated['amount']) {
                    throw new \Exception('Payment amount mismatch');
                }
                
                $order->markAsPaid();
                $message = 'Payment successful';
            } else {
                // Failed or cancelled
                $order->cancel();
                $message = 'Payment ' . $status;
            }
            
            // Clear product cache
            \App\Models\Product::clearStockCache($order->product_id);
            
            $response = [
                'status' => 'processed',
                'message' => $message,
                'order_id' => $orderId,
                'order_status' => $order->status,
            ];
            
            // Store response for idempotency
            IdempotencyKey::storeResponse($idempotencyKey, $requestHash, json_encode($response));
            
            DB::commit();
            
            Log::info('Webhook processed successfully', [
                'order_id' => $orderId,
                'status' => $status,
                'idempotency_key' => $idempotencyKey,
            ]);
            
            return response()->json($response);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Webhook processing failed', [
                'error' => $e->getMessage(),
                'order_id' => $orderId,
                'idempotency_key' => $idempotencyKey,
            ]);
            
            return response()->json([
                'error' => 'Webhook processing failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Process pending webhooks for orders that now exist
     */
    public function processPendingWebhooks()
    {
        $pending = PendingWebhook::where('processed', false)
            ->where('created_at', '>=', now()->subHours(24))
            ->get();
        
        $processed = 0;
        
        foreach ($pending as $webhook) {
            if (Order::where('id', $webhook->order_id)->exists()) {
                // Simulate webhook call
                $order = Order::find($webhook->order_id);
                
                if ($webhook->status === 'paid') {
                    $order->markAsPaid();
                } else {
                    $order->cancel();
                }
                
                $webhook->update(['processed' => true]);
                $processed++;
            }
        }
        
        return response()->json([
            'processed' => $processed,
            'pending' => $pending->count(),
        ]);
    }
}