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
        Schema::create('event', function (Blueprint $table) {
            $table->id();
            
            // Relations
            $table->foreignId('club_id')->constrained('clubs')->onDelete('cascade');
            $table->foreignId('created_by')->constrained('persons')->onDelete('restrict');
            $table->foreignId('validated_by')->nullable()->constrained('persons')->onDelete('restrict');
            
            // Basic Event Info
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('category', 100)->nullable();
            
            // Date & Location
            $table->timestamp('event_date');
            $table->timestamp('registration_deadline')->nullable();
            $table->string('location')->nullable();
            
            // Capacity & Registration
            $table->integer('capacity')->nullable();
            $table->integer('registered_count')->default(0);
            $table->integer('attendees_count')->default(0);
            
            // Status & Visibility
            $table->enum('status', ['pending', 'approved', 'refused', 'cancelled', 'completed'])->default('pending');
            $table->boolean('is_public')->default(true);
            
            // Media
            $table->string('banner_image', 500)->nullable();
            
            // Ticketing
            $table->boolean('requires_ticket')->default(false);
            $table->boolean('tickets_for_all')->default(false);
            $table->decimal('price', 10, 2)->default(0.00);
            
            // Recap (for completed events)
            $table->text('recap_description')->nullable();
            $table->json('recap_images')->nullable();
            $table->timestamp('completed_at')->nullable();
            
            // Timestamps
            $table->timestamps();
            
            // Indexes for performance
            $table->index('club_id');
            $table->index('status');
            $table->index('event_date');
            $table->index(['status', 'event_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};