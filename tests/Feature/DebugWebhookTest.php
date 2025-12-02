<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebugWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_debug_webhook_error()
    {
        // First check if tables exist
        echo "\nChecking tables...\n";
        
        // Create minimal data
        $product = \App\Models\Product::create([
            'name' => 'Test',
            'price' => 100,
            'total_stock' => 10,
        ]);
        
        $hold = \App\Models\Hold::create([
            'product_id' => $product->id,
            'quantity' => 1,
            'hold_token' => 'debug_token_' . uniqid(),
            'expires_at' => now()->addMinutes(10),
            'is_used' => false,
        ]);
        
        $order = \App\Models\Order::create([
            'hold_id' => $hold->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'amount' => 100,
            'status' => 'pending_payment',
        ]);
        
        echo "Order ID: {$order->id}\n";
        
        // Test webhook
        $response = $this->postJson('/api/api/payments/webhook', [
            'order_id' => $order->id,
            'payment_id' => 'pay_debug123',
            'status' => 'paid',
            'amount' => 100,
            'currency' => 'USD',
        ], [
            'Idempotency-Key' => 'debug-key-' . uniqid(),
        ]);
        
        echo "\nResponse Status: " . $response->getStatusCode() . "\n";
        echo "Response Content: " . $response->getContent() . "\n";
        
        // Check Laravel logs for error
        if ($response->getStatusCode() === 500) {
            echo "\n⚠️  500 Error! Check Laravel logs for details.\n";
            echo "Common issues:\n";
            echo "1. Missing IdempotencyKey model/table\n";
            echo "2. Missing PendingWebhook model/table\n";
            echo "3. Database constraint error\n";
        }
    }
}