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
     Schema::create('persons', function (Blueprint $table) {
    $table->id();

    $table->string('first_name');
    $table->string('last_name');
    $table->string('email')->unique();
    $table->string('password')->nullable();

    // Google OAuth
    $table->string('google_id')->nullable()->unique();
    $table->string('google_email')->nullable();
    $table->text('google_token')->nullable();
    $table->text('google_refresh_token')->nullable();

    // Other fields
    $table->string('member_code')->nullable();
    $table->string('cne')->nullable();
    $table->string('avatar')->nullable();
    $table->string('phone')->nullable();
    $table->string('role')->default('user');
    $table->boolean('is_active')->default(true);
    $table->timestamp('email_verified_at')->nullable();

    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            if (Schema::hasColumn('persons', 'google_email')) {
                $table->dropColumn('google_email');
            }
            if (Schema::hasColumn('persons', 'google_token')) {
                $table->dropColumn('google_token');
            }
            if (Schema::hasColumn('persons', 'google_refresh_token')) {
                $table->dropColumn('google_refresh_token');
            }
        });
    }
};