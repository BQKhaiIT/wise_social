<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class InsertLoginFail extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void 
    {
        Schema::table('users', function(Blueprint $table) {
            $table->integer('login_fail')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
