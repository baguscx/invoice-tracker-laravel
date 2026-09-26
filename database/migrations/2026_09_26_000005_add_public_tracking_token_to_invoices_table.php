<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('public_tracking_token', 64)->nullable()->unique()->after('id');
        });

        DB::table('invoices')->whereNull('public_tracking_token')->orderBy('id')->eachById(function ($invoice) {
            DB::table('invoices')->where('id', $invoice->id)->update([
                'public_tracking_token' => Str::random(64),
            ]);
        }, 100, 'id');
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['public_tracking_token']);
            $table->dropColumn('public_tracking_token');
        });
    }
};
