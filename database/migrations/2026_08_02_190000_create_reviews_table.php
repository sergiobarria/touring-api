<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tour_id')->constrained('tours')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('review');
            $table->timestamps();
            $table->unique(['tour_id', 'user_id']);
            $table->index(['tour_id', 'created_at', 'id']);
        });

        DB::table('tours')->update(['rating_avg' => null, 'rating_count' => 0]);
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
