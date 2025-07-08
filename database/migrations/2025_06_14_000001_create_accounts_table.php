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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();

            $table->string('username')->unique();
            $table->string('password');
            $table->string('name')->nullable();
            $table->text('device_id')->nullable();
            $table->boolean('parental_control')->default(false);
            $table->text('token')->nullable();
            $table->timestamp('token_expires_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('download_log', function (Blueprint $table) {
            $table->unsignedBigInteger('account_id')->nullable()->after('id');
            $table->foreign('account_id')->references('id')->on('accounts');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('download_log', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->dropColumn('account_id');
        });
        
        Schema::dropIfExists('accounts');
    }
};
