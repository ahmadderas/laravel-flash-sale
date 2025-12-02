# Laravel Flash Sale Checkout System

A Laravel API for flash sales with concurrency control, temporary holds, and idempotent payment processing.

## How to Run the App

### Prerequisites
- PHP 8.2+
- Composer
- SQLite (included with PHP)

### Installation
```bash
# Clone and install
git clone https://github.com/yourusername/laravel-flash-sale.git
cd laravel-flash-sale
composer install

# Setup database
php artisan migrate
php artisan db:seed

# Start server
php artisan serve
```

### How to Run Tests
```php artisan test --filter=FlashSaleTest --testdox```

### Expected Output
```Tests\Feature\FlashSaleTest

✓ it shows product with accurate stock

✓ it prevents overselling with concurrent requests

✓ it creates order from valid hold

✓ it rejects expired hold

✓ webhook is idempotent

✓ it handles webhook before order creation

Tests:    6 passed
Duration: 0.98s
```

### Assumptions & Invariants
1. Single Product: One flash sale product (ID: 1) with 100 units stock

2. Hold Duration: 2-minute temporary reservations

3. Webhook Delivery: At-least-once, may arrive multiple times

4. Idempotency Keys: Provided in Idempotency-Key header

5. Max Quantity: 10 units per hold request

6. No Overselling: ```active_holds + paid_orders ≤ total_stock```

### API Endpoints

#### Get Products
GET /api/api/products/1

#### Create Hold
POST /api/api/holds
Content-Type: application/json

{"product_id": 1, "quantity": 2}

#### Payment Webhook
POST /api/api/payments/webhook
Content-Type: application/json
Idempotency-Key: unique-key-123

{"order_id": 1, "payment_id": "pay_123", "status": "paid", "amount": 99.98, "currency": "USD"}

### Expected Test Results

Tests\Feature\FlashSaleTest

✓ it shows product with accurate stock

✓ it prevents overselling with concurrent requests

✓ it creates order from valid hold

✓ it rejects expired hold
✓ webhook is idempotent
✓ it handles webhook before order creation

Tests:    6 passed
Duration: 0.98s
