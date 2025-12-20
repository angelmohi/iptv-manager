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
        Schema::create('channel_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_id');
            $table->text('pssh')->nullable();
            $table->text('api_key')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            
            $table->timestamp('created_at')->nullable();

            $table->foreign('channel_id')->references('id')->on('channels')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channel_history');
    }
};
