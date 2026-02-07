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
       Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('event')->onDelete('cascade');
            $table->foreignId('person_id')->constrained('persons')->onDelete('cascade');
            $table->string('qr_code')->unique();
            $table->enum('status', ['valid', 'scanned', 'expired', 'cancelled'])->default('valid');
            $table->boolean('auto_generated')->default(true);
            $table->foreignId('generated_by')->nullable()->constrained('persons')->onDelete('restrict');
            $table->timestamp('generated_at')->useCurrent();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('scanned_at')->nullable();
            $table->foreignId('scanned_by')->nullable()->constrained('persons')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
