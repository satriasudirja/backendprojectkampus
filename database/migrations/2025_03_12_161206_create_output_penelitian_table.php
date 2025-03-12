<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOutputPenelitianTable extends Migration
{
    public function up()
    {
        Schema::create('output_penelitian', function (Blueprint $table) {
            $table->string('kode', 4)->primary();
            $table->string('output_penelitian', 200);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('output_penelitian');
    }
}