<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('visit_aggregates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('link_id')->constrained('links')->cascadeOnDelete();
            $table->date('day');
            $table->unsignedBigInteger('clicks')->default(0);
            $table->timestamps();

            $table->unique(['link_id', 'day'], 'visit_aggr_link_day_unique');
            $table->index('day', 'visit_aggr_day_index');
            $table->index(['link_id', 'day'], 'visit_aggr_link_day_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_aggregates');
    }
};


