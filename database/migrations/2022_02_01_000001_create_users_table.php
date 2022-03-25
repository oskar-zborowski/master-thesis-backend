<?php

use App\Http\Libraries\Validation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up() {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->char('name', 48); // Kodowane automatycznie
            $table->enum('default_avatar', Validation::getAvatars());
            $table->string('producer', 30)->nullable();
            $table->string('model', 50)->nullable();
            $table->enum('os_name', Validation::getOsNames())->nullable();
            $table->string('os_version', 10)->nullable();
            $table->enum('app_version', Validation::getAppVersions());
            $table->char('uuid', 112)->nullable(); // Kodowane automatycznie
            $table->timestamp('blocked_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down() {
        Schema::dropIfExists('users');
    }
};
