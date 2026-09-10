<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vats', function (Blueprint $table) {
            if (! Schema::hasColumn('vats', 'address')) {
                $table->string('address', 1024)->nullable()->after('name');
            }
            if (! Schema::hasColumn('vats', 'province_id')) {
                $table->unsignedBigInteger('province_id')->nullable()->after('address');
                $table->foreign('province_id')->references('id')->on('provinces')->nullOnDelete();
            }
            if (! Schema::hasColumn('vats', 'regency_id')) {
                $table->unsignedBigInteger('regency_id')->nullable()->after('province_id');
                $table->foreign('regency_id')->references('id')->on('regencies')->nullOnDelete();
            }
            if (! Schema::hasColumn('vats', 'postal_code')) {
                $table->string('postal_code', 10)->nullable()->after('regency_id');
            }
            if (! Schema::hasColumn('vats', 'owner_id')) {
                $table->unsignedBigInteger('owner_id')->nullable()->after('postal_code');
                $table->foreign('owner_id')->references('id')->on('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('vats', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->dropForeign(['regency_id']);
            $table->dropForeign(['province_id']);
            $table->dropColumn(['owner_id', 'postal_code', 'regency_id', 'province_id', 'address']);
        });
    }
};
