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
       Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->onDelete('cascade');
            $table->foreignId('created_by')->constrained('persons')->onDelete('restrict');
            $table->foreignId('validated_by')->nullable()->constrained('persons')->onDelete('restrict');
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('category', 100)->nullable();
            $table->timestamp('event_date')->useCurrent()->useCurrentOnUpdate();
            $table->timestamp('registration_deadline')->nullable();
            $table->string('location')->nullable();
            $table->integer('capacity')->nullable();
            $table->integer('registered_count')->default(0);
            $table->integer('attendees_count')->default(0);
            $table->enum('status', ['pending', 'approved', 'refused', 'cancelled', 'completed'])->default('pending');
            $table->boolean('is_public')->default(true);
            $table->string('banner_image')->nullable();
            $table->boolean('requires_ticket')->default(false);
            $table->boolean('tickets_for_all')->default(false);
            $table->decimal('price', 10, 2)->default(0.00);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
