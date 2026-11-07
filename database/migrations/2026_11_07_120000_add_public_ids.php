<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Random codes for page addresses (/people/k3v9q2mz7xwp, /messages/…)
 * instead of database numbers.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['users', 'matches'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('public_id', 16)->nullable()->after('id');
            });

            DB::table($table)->whereNull('public_id')->orderBy('id')->each(function ($row) use ($table) {
                DB::table($table)->where('id', $row->id)->update(['public_id' => Str::lower(Str::random(12))]);
            });

            Schema::table($table, function (Blueprint $t) {
                $t->unique('public_id');
            });
        }
    }

    public function down(): void
    {
        foreach (['users', 'matches'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropUnique(['public_id']);
                $t->dropColumn('public_id');
            });
        }
    }
};
