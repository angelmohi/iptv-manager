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
        Schema::create('channel_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('order')->default(1);

            $table->timestamps();
            $table->softDeletes();
        });
        
        Schema::create('channels', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('category_id')->nullable();

            $table->string('name');
            $table->text('tvg_id')->nullable();
            $table->string('logo')->nullable();
            $table->text('user_agent')->nullable();
            $table->text('manifest_type')->nullable();
            $table->text('license_type')->nullable();
            $table->text('api_key')->nullable();
            $table->text('url_channel')->nullable();
            $table->string('catchup')->nullable();
            $table->string('catchup_days')->nullable();
            $table->text('catchup_source')->nullable();
            $table->unsignedInteger('order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->boolean('apply_token')->default(false);
            $table->boolean('parental_control')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('category_id')->references('id')->on('channel_categories');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channels');
        Schema::dropIfExists('channel_categories');
    }
};
