<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_webhooks', function (Blueprint $table) {
            $table->id();
            $table->integer('order_id');
            $table->string('payment_id');
            $table->string('status');
            $table->text('payload');
            $table->boolean('processed')->default(false);
            $table->timestamps();
            
            $table->index(['order_id', 'processed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_webhooks');
    }
};