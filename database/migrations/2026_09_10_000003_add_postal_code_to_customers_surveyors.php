<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['customers', 'surveyors'] as $table) {
            if (! Schema::hasColumn($table, 'postal_code')) {
                Schema::table($table, function (Blueprint $t) use ($table) {
                    $t->string('postal_code', 10)->nullable()->after('address');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['customers', 'surveyors'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('postal_code');
            });
        }
    }
};
