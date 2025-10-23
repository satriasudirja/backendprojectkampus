<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('simpeg_master_pangkat', function (Blueprint $table) {
            //
            $table->decimal('potongan', 15, 2)->nullable();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simpeg_master_pangkat', function (Blueprint $table) {
            //
            $table->dropColumn(['potongan']);
        });
    }
};
