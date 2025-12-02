# Laravel Flash Sale Checkout System

A production-ready Laravel API for handling flash sales with high concurrency, temporary holds, and idempotent payment processing. Built as an interview task demonstrating advanced Laravel concepts.

## Interview Task Requirements Met

| Requirement | Status | Test Coverage |
|------------|--------|---------------|
| ✅ No overselling under heavy parallel requests | **PASS** | `it_prevents_overselling_with_concurrent_requests` |
| ✅ Hold expiry returns availability automatically | **PASS** | `it_rejects_expired_hold` |
| ✅ Webhook idempotency (same key repeated) | **PASS** | `webhook_is_idempotent` |
| ✅ Webhook arriving before order creation | **PASS** | `it_handles_webhook_before_order_creation` |
| ✅ Accurate stock calculation with caching | **PASS** | `it_shows_product_with_accurate_stock` |
| ✅ Order creation from valid holds | **PASS** | `it_creates_order_from_valid_hold` |

**All 6 tests passing** - See test output below.

## Key Features

### Concurrency Control
- Database row locking (`lockForUpdate()`) prevents race conditions
- Transactions ensure atomic operations
- No overselling even with 100+ concurrent requests

### Temporary Holds System
- 2-minute reservations with auto-expiration
- Background scheduled command cleans up expired holds
- Immediate stock reduction for other users

### **📊 Accurate Stock Calculation**
```php
available = total_stock - active_holds - paid_orders
