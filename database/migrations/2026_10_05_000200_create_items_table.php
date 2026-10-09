<?php

use App\Enums\UnitFamily;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Items are the things being traded. Each is bound to one family, so its
 * quantity is always recorded in units of that family, and it remembers the
 * unit it is normally traded in (a bag, a crate, a dozen).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            // Case-insensitive uniqueness that behaves the same on every driver.
            $table->string('name_key', 160)->unique();
            $table->string('family', 16)->default(UnitFamily::Count->value)->index();
            $table->string('default_unit_code', 32);
            $table->string('pack_size', 80)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->foreign('default_unit_code')->references('code')->on('units')
                ->restrictOnUpdate()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
