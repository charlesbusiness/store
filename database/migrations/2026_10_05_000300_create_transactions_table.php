<?php

use App\Enums\RecordType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A transaction is one sale or one purchase: a header with its lines. The
 * original app stored a flat list where each cart line was its own record;
 * grouping them keeps a multi-item sale atomic while still allowing any single
 * line to be deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('type', 16)->default(RecordType::Purchase->value)->index();
            $table->date('transacted_on')->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['type', 'transacted_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
