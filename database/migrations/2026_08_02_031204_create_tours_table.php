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
        Schema::create('tours', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->timestamps();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedTinyInteger('duration_days');
            $table->unsignedTinyInteger('max_group_size');
            $table->enum('difficulty', ['easy', 'moderate', 'difficult'])->default('easy');
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('price_discount_percent', 5, 2)->nullable();
            $table->decimal('rating_avg', 3, 2)->nullable();
            $table->unsignedInteger('rating_count')->default(0);
            $table->string('summary', 500);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);

            // Indexes
            $table->index('difficulty');
            $table->index('duration_days');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tours');
    }
};
