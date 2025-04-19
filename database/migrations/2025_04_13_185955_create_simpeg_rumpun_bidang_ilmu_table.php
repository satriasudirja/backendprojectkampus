<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('simpeg_rumpun_bidang_ilmu', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode', 5);
            $table->string('nama_bidang', 100);
            $table->string('parent_category', 100);
            $table->string('sub_parent_category', 100);
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_rumpun_bidang_ilmu');
    }
};
