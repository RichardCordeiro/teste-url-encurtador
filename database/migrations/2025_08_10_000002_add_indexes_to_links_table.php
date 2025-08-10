<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('links', function (Blueprint $table) {
            $table->index('user_id', 'links_user_id_index');

            $table->index(['user_id', 'status'], 'links_user_id_status_index');

            $table->index(['user_id', 'id'], 'links_user_id_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('links', function (Blueprint $table) {
            $table->dropIndex('links_user_id_index');
            $table->dropIndex('links_user_id_status_index');
            $table->dropIndex('links_user_id_id_index');
        });
    }
};


