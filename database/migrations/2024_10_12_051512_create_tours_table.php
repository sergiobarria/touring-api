<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tours', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->timestamps();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->integer('price');
            $table->integer('duration');
            $table->integer('max_group_size');
            $table->enum('difficulty', ['easy', 'medium', 'difficult']);
            $table->float('rating')->default('0.0');
            $table->integer('ratings_quantity')->default(0);
            $table->text('summary');
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(true);
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
