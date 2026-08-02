<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tour_start_dates', function (Blueprint $table): void {
            $table->unsignedTinyInteger('reserved_spots')->default(0)->after('available_spots');
        });

        Schema::create('bookings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->timestamps();
            $table->foreignUlid('user_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('tour_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('tour_start_date_id')->constrained()->restrictOnDelete();
            $table->string('reference', 24)->unique();
            $table->string('status', 32)->index();
            $table->unsignedTinyInteger('ticket_quantity');
            $table->unsignedBigInteger('unit_amount');
            $table->unsignedBigInteger('total_amount');
            $table->char('currency', 3);
            $table->string('tour_name');
            $table->timestamp('departure_datetime_utc');
            $table->string('purchaser_name');
            $table->string('purchaser_email');
            $table->decimal('discount_percent', 5, 2)->nullable();
            $table->char('idempotency_key_hash', 64);
            $table->char('request_hash', 64);
            $table->string('stripe_checkout_session_id')->nullable()->unique();
            $table->text('stripe_checkout_url')->nullable();
            $table->string('stripe_payment_intent_id')->nullable()->unique();
            $table->string('stripe_refund_id')->nullable()->unique();
            $table->string('stripe_refund_status', 32)->nullable();
            $table->unsignedTinyInteger('refund_attempt')->default(0);
            $table->timestamp('hold_expires_at')->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancellation_requested_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('failure_code')->nullable();
            $table->unique(['user_id', 'idempotency_key_hash']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('booking_travelers', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->timestamps();
            $table->foreignUlid('booking_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('email');
            $table->string('phone', 32);
        });

        Schema::create('stripe_webhook_events', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('type');
            $table->timestamp('processed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_webhook_events');
        Schema::dropIfExists('booking_travelers');
        Schema::dropIfExists('bookings');
        Schema::table('tour_start_dates', function (Blueprint $table): void {
            $table->dropColumn('reserved_spots');
        });
    }
};
