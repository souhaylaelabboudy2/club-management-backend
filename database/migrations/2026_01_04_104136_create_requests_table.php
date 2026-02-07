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
         Schema::create('request', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('club_id');
            $table->unsignedBigInteger('requested_by');
            $table->enum('type', ['event', 'member', 'budget', 'other'])->default('other');
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamp('requested_at')->useCurrent();
            $table->unsignedBigInteger('validated_by')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->text('validation_comment')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('club_id')->references('id')->on('clubs')->onDelete('cascade');
            $table->foreign('requested_by')->references('id')->on('persons')->onDelete('cascade');
            $table->foreign('validated_by')->references('id')->on('persons')->onDelete('set null');
        });
    }

    /**hp
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requests');
    }
};
