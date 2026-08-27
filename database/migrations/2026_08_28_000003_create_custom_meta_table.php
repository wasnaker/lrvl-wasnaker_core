<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_meta', function (Blueprint $table) {
            $table->id();
            $table->string('meta_key');
            $table->text('meta_value')->nullable();
            $table->string('meta_type');
            $table->unsignedBigInteger('meta_id');
            $table->timestamps();

            $table->index(['meta_type', 'meta_id']);
            $table->index('meta_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_meta');
    }
};
