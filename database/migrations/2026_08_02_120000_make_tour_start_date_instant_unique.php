<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tour_start_dates', function (Blueprint $table) {
            $table->dropIndex(['tour_id', 'start_datetime_utc']);
            $table->unique(['tour_id', 'start_datetime_utc']);
        });
    }

    public function down(): void
    {
        Schema::table('tour_start_dates', function (Blueprint $table) {
            $table->dropUnique(['tour_id', 'start_datetime_utc']);
            $table->index(['tour_id', 'start_datetime_utc']);
        });
    }
};
