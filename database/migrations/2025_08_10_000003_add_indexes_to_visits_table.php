<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            if (!Schema::hasColumn('visits', 'link_id')) {
                // safeguard; expected to exist in base migration
            }
            $table->index(['link_id', 'created_at'], 'visits_link_id_created_at_index');
            $table->index('link_id', 'visits_link_id_index');
            $table->index('created_at', 'visits_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropIndex('visits_link_id_created_at_index');
            $table->dropIndex('visits_link_id_index');
            $table->dropIndex('visits_created_at_index');
        });
    }
};


