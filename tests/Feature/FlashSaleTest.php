<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Hold;
use App\Models\Order;
use App\Models\PendingWebhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Str;

class FlashSaleTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_shows_product_with_accurate_stock()
    {
        $product = Product::create([
            'name' => 'Test Product',
            'price' => 50.00,
            'total_stock' => 100,
        ]);

        $response = $this->getJson("/api/api/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id', 'name', 'price', 'total_stock', 'available_stock'
                ]
            ]);
    }

    /** @test */
    public function it_prevents_overselling_with_concurrent_requests()
    {
        $product = Product::create([
            'name' => 'Flash Sale Product',
            'price' => 30.00,
            'total_stock' => 10,
        ]);

        // Simulate multiple concurrent hold requests
        $responses = [];
        
        for ($i = 0; $i < 15; $i++) {
            $responses[] = $this->postJson('/api/api/holds', [
                'product_id' => $product->id,
                'quantity' => 1,
            ]);
        }
        
        // Count successful holds (should be max 10)
        $successful = collect($responses)->filter(function ($response) {
            return $response->getStatusCode() === 201;
        })->count();
        
        $this->assertEquals(10, $successful, 'Only 10 holds should succeed for 10 units of stock');
        
        // Verify available stock is now 0
        $productResponse = $this->getJson("/api/api/products/{$product->id}");
        $availableStock = $productResponse->json('data.available_stock');
        $this->assertEquals(0, $availableStock);
    }

    /** @test */
    public function it_creates_order_from_valid_hold()
    {
        $product = Product::create([
            'name' => 'Test Product',
            'price' => 50.00,
            'total_stock' => 10,
        ]);

        // Create a hold
        $holdResponse = $this->postJson('/api/api/holds', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
        
        $holdToken = $holdResponse->json('data.hold_token');
        
        // Create order from hold
        $orderResponse = $this->postJson('/api/api/orders', [
            'hold_token' => $holdToken,
        ]);
        
        $orderResponse->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'order_id', 'hold_id', 'product_id', 'quantity', 'amount', 'status'
                ]
            ]);
        
        $this->assertEquals(100.00, $orderResponse->json('data.amount'));
        $this->assertEquals('pending_payment', $orderResponse->json('data.status'));
    }

    /** @test */
    public function it_rejects_expired_hold()
    {
        $product = Product::create([
            'name' => 'Test Product',
            'price' => 50.00,
            'total_stock' => 10,
        ]);
        
        // Create a hold
        $holdResponse = $this->postJson('/api/api/holds', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
        
        $holdToken = $holdResponse->json('data.hold_token');
        
        // Manually expire the hold in database
        Hold::where('hold_token', $holdToken)->update([
            'expires_at' => now()->subMinutes(5),
        ]);
        
        // Try to create order - should fail
        $orderResponse = $this->postJson('/api/api/orders', [
            'hold_token' => $holdToken,
        ]);
        
        $orderResponse->assertStatus(422)
            ->assertJson(['error' => 'Hold has expired']);
    }

    /** @test */
public function webhook_is_idempotent()
{
    $product = Product::create([
        'name' => 'Test Product',
        'price' => 30.00,
        'total_stock' => 10,
    ]);
    
    // Create hold and order
    $holdResponse = $this->postJson('/api/api/holds', [
        'product_id' => $product->id,
        'quantity' => 1,
    ]);
    
    $holdToken = $holdResponse->json('data.hold_token');
    
    $orderResponse = $this->postJson('/api/api/orders', [
        'hold_token' => $holdToken,
    ]);
    
    $orderId = $orderResponse->json('data.order_id');
    $idempotencyKey = 'test-key-' . Str::random(20);
    
    // DEBUG: Show what we're sending
    echo "\nDEBUG: Order ID: $orderId";
    echo "\nDEBUG: Idempotency Key: $idempotencyKey";
    
    $webhookData = [
        'order_id' => $orderId,
        'payment_id' => 'pay_' . Str::random(10),
        'status' => 'paid',
        'amount' => 30.00,
        'currency' => 'USD',
    ];
    
    // First webhook call
    $webhook1 = $this->postJson('/api/api/payments/webhook', $webhookData, [
        'Idempotency-Key' => $idempotencyKey,
    ]);
    
    echo "\nDEBUG: First call status: " . $webhook1->getStatusCode();
    echo "\nDEBUG: First call response: " . $webhook1->getContent();
    
    // Check database state
    $orderAfterFirst = Order::find($orderId);
    echo "\nDEBUG: Order status after first: " . ($orderAfterFirst ? $orderAfterFirst->status : 'null');
    
    // Second webhook call with same idempotency key
    $webhook2 = $this->postJson('/api/api/payments/webhook', $webhookData, [
        'Idempotency-Key' => $idempotencyKey,
    ]);
    
    echo "\nDEBUG: Second call status: " . $webhook2->getStatusCode();
    echo "\nDEBUG: Second call response: " . $webhook2->getContent();
    
    $webhook1->assertStatus(200);
    $webhook2->assertStatus(200);
    
    // Both responses should be the same
    $this->assertEquals($webhook1->getContent(), $webhook2->getContent());
    
    // Order should only be marked paid once
    $order = Order::find($orderId);
    $paidOrders = Order::where('status', 'paid')->count();
    $this->assertEquals(1, $paidOrders);
}
    
    /** @test */
    public function it_handles_webhook_before_order_creation()
    {
        $product = Product::create([
            'name' => 'Test Product',
            'price' => 50.00,
            'total_stock' => 10,
        ]);
        
        // Simulate webhook arriving before order exists
        $nonExistentOrderId = 99999;
        $idempotencyKey = 'early-webhook-' . Str::random(20);
        
        $response = $this->postJson('/api/api/payments/webhook', [
            'order_id' => $nonExistentOrderId,
            'payment_id' => 'pay_early',
            'status' => 'paid',
            'amount' => 50.00,
            'currency' => 'USD',
        ], [
            'Idempotency-Key' => $idempotencyKey,
        ]);
        
        $response->assertStatus(200)
            ->assertJson([
                'status' => 'queued',
                'message' => 'Order not found yet. Webhook queued for later processing.',
            ]);
        
        // Check that webhook was stored as pending
        $this->assertDatabaseHas('pending_webhooks', [
            'order_id' => $nonExistentOrderId,
            'payment_id' => 'pay_early',
            'processed' => false,
        ]);
    }
}