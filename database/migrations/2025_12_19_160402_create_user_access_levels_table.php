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
        Schema::create('user_access_level', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        // Insert default access levels
        \DB::table('user_access_level')->insert([
            ['name' => 'Full Administrator', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'List Manager', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Get the ID for Full Administrator
        $fullAdminId = \DB::table('user_access_level')->where('name', 'Full Administrator')->value('id');

        Schema::table('users', function (Blueprint $table) use ($fullAdminId) {
            $table->unsignedBigInteger('access_level_id')->default($fullAdminId)->after('password');
            $table->foreign('access_level_id')->references('id')->on('user_access_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['access_level_id']);
            $table->dropColumn('access_level_id');
        });

        Schema::dropIfExists('user_access_level');
    }
};
