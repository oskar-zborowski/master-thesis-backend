<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name', 30);
            $table->char('token', 64)->unique(); // Kodowane przez dostawcę
            $table->char('refresh_token', 64)->unique()->nullable(); // Kodowane podczas przetwarzania
            $table->text('abilities')->nullable();
            $table->timestamp('expiry_alert_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('personal_access_tokens');
    }
};
