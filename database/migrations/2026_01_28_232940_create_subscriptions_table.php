<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();

            $table->enum('status', [
                'active',
                'expired',
                'cancelled',
                'suspended'
            ])->default('active');

            $table->dateTime('starts_at');
            $table->dateTime('expires_at');


            $table->boolean('auto_renew')->default(false);
            $table->boolean('is_renewable')->default(true);

            $table->foreignId('renewed_from_subscription_id')
                ->nullable()
                ->constrained('subscriptions');

            $table->string('payment_id')->nullable();
            
            $table->string('mikrotik_device_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('mac_address', 50)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
