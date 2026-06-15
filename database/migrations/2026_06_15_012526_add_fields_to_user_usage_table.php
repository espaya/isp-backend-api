<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('user_usages', function (Blueprint $table) {
            $table->foreignId('subscription_id')->nullable()->after('user_id')->constrained();
            $table->decimal('gb_used', 10, 2)->default(0)->after('bytes_used');
            $table->decimal('data_limit_gb', 10, 2)->nullable()->after('gb_used');
            $table->decimal('percentage_used', 10, 2)->default(0)->after('data_limit_gb');
        });
    }

    public function down()
    {
        Schema::table('user_usages', function (Blueprint $table) {
            $table->dropColumn(['subscription_id', 'gb_used', 'data_limit_gb', 'percentage_used']);
        });
    }
};
