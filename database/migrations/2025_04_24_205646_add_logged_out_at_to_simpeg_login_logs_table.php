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
        Schema::table('simpeg_login_logs', function (Blueprint $table) {
            $table->timestamp('logged_out_at')->nullable()->after('logged_in_at');
        });
    }
    
    public function down()
    {
        Schema::table('simpeg_login_logs', function (Blueprint $table) {
            $table->dropColumn('logged_out_at');
        });
    }
    
};
