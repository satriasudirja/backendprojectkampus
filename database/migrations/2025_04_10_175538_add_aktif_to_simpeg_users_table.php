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
        Schema::table('simpeg_users', function (Blueprint $table) {
            $table->boolean('aktif')->default(true)->after('password');
        });
    }
    
    public function down()
    {
        Schema::table('simpeg_users', function (Blueprint $table) {
            $table->dropColumn('aktif');
        });
    }
};
