<?php

namespace App\Console\Commands;

use App\Models\Hold;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpireHolds extends Command
{
    protected $signature = 'holds:expire';
    protected $description = 'Expire old holds and release stock';

    public function handle()
    {
        $this->info('Starting hold expiration process...');
        
        $expiredCount = 0;
        
        // Find expired holds that haven't been used
        $expiredHolds = Hold::where('expires_at', '<=', now())
            ->where('is_used', false)
            ->get();
        
        foreach ($expiredHolds as $hold) {
            DB::transaction(function () use ($hold, &$expiredCount) {
                // Mark hold as used (releases stock)
                $hold->update(['is_used' => true]);
                
                // Clear product cache
                Product::clearStockCache($hold->product_id);
                
                $expiredCount++;
                
                Log::info('Hold expired', [
                    'hold_id' => $hold->id,
                    'product_id' => $hold->product_id,
                    'quantity' => $hold->quantity,
                ]);
            });
        }
        
        $this->info("Expired {$expiredCount} holds.");
        
        if ($expiredCount > 0) {
            Log::info("Hold expiration completed", ['expired_count' => $expiredCount]);
        }
        
        return 0;
    }
}