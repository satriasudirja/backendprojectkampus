<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOutputPenelitianTable extends Migration
{
    public function up()
    {
        Schema::create('simpeg_master_output_penelitian', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode', 5);
            $table->string('output_penelitian', 100);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('simpeg_master_output_penelitian');
    }
}