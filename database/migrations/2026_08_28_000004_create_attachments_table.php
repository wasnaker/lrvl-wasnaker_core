<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->string('rel_type');          // mis. 'invoice', 'client', 'task'
            $table->unsignedBigInteger('rel_id');
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('disk')->default('local');
            $table->string('path');              // path relatif di disk
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('extension')->nullable();
            $table->timestamps();

            $table->index(['rel_type', 'rel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
