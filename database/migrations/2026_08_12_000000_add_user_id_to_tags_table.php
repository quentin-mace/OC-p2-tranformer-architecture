<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Added nullable first: existing tags predate this column and have no owner to backfill
        // automatically, so we assign them to the first user before enforcing NOT NULL below.
        Schema::table('tags', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->after('id')
                ->constrained('users')
                ->cascadeOnDelete();
        });

        DB::table('tags')->whereNull('user_id')->update([
            'user_id' => DB::table('users')->orderBy('id')->value('id'),
        ]);

        Schema::table('tags', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->unique(['user_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'name']);
            $table->dropConstrainedForeignId('user_id');
        });
    }
};