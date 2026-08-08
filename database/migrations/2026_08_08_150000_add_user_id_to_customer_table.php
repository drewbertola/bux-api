<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customer', function (Blueprint $table) {
            $table->foreignId('userId')->nullable()->after('id')->constrained('users')->nullOnDelete();
        });

        // Backfill any pre-existing customers to the first user in the
        // system so an upgrade doesn't orphan existing data. Every
        // customer created from here on gets an explicit owner from the
        // authenticated request.
        $firstUserId = DB::table('users')->orderBy('id')->value('id');

        if ($firstUserId) {
            DB::table('customer')->whereNull('userId')->update(['userId' => $firstUserId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer', function (Blueprint $table) {
            $table->dropForeign(['userId']);
            $table->dropColumn('userId');
        });
    }
};
