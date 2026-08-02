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
        Schema::create('tour_start_dates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->timestamps();
            $table->foreignUlid('tour_id')->constrained('tours')->onDelete('cascade');
            $table->timestamp('start_datetime_utc');
            $table->unsignedTinyInteger('available_spots')->default(0);
            $table->boolean('is_active')->default(true);

            // Indexes
            $table->index(['tour_id', 'start_datetime_utc']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tour_start_dates');
    }
};
