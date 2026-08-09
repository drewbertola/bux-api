<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = ['customer', 'invoice', 'payment', 'line_item'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('user_id')->nullable()->after('id')->constrained('users');
            });
        }

        // Ownership was never tracked before this migration, so existing
        // rows can't be attributed to a specific account. Assign them to
        // the oldest user rather than leaving them orphaned/inaccessible.
        // The column stays nullable at the DB level (changing it to NOT
        // NULL requires doctrine/dbal, which isn't installed, and isn't
        // portable to sqlite); the application always sets user_id
        // explicitly on create, so this is not a gap in practice.
        $ownerId = DB::table('users')->orderBy('id')->value('id');

        if ($ownerId) {
            foreach ($this->tables as $table) {
                DB::table($table)->whereNull('user_id')->update(['user_id' => $ownerId]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropForeign(['user_id']);
                $blueprint->dropColumn('user_id');
            });
        }
    }
};
