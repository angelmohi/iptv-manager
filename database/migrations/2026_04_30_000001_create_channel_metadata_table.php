<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_metadata', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_id');

            $table->string('provider', 32)->default('tmdb');
            $table->string('external_id', 64);
            $table->string('imdb_id', 32)->nullable()->index();

            $table->string('title');
            $table->string('original_title')->nullable();
            $table->text('overview')->nullable();

            $table->smallInteger('release_year')->nullable();
            $table->smallInteger('runtime_minutes')->nullable();

            $table->text('poster_url')->nullable();
            $table->text('backdrop_url')->nullable();

            $table->decimal('rating', 3, 1)->nullable();
            $table->unsignedInteger('rating_count')->nullable();
            $table->decimal('rating_imdb', 3, 1)->nullable();
            $table->unsignedInteger('rating_imdb_count')->nullable();
            $table->decimal('rating_filmaffinity', 3, 1)->nullable();

            $table->json('genres')->nullable();
            $table->json('cast')->nullable();
            $table->text('trailer_url')->nullable();

            $table->enum('match_status', ['matched', 'ambiguous', 'not_found', 'manual'])->default('matched');
            $table->timestamp('enriched_at')->nullable();

            $table->timestamps();

            $table->unique('channel_id');
            $table->foreign('channel_id')->references('id')->on('channels')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_metadata');
    }
};
