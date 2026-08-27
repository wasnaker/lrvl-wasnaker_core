<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->text('value')->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->timestamps();

            // Composite unique: tiap tenant (termasuk global = tenant_id NULL)
            // boleh punya key yang sama dengan nilai berbeda. Global setting
            // direpresentasikan sebagai tenant_id NULL.
            $table->unique(['key', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
