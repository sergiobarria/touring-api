<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tour_start_dates', function (Blueprint $table) {
            $table->index('start_datetime_utc');
        });
    }

    public function down(): void
    {
        Schema::table('tour_start_dates', function (Blueprint $table) {
            $table->dropIndex(['start_datetime_utc']);
        });
    }
};
