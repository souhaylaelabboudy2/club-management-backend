<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('persons', function (Blueprint $table) {
            $table->string('two_factor_secret')->nullable()->after('password');
            $table->boolean('two_factor_enabled')->default(false)->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_enabled');
        });
    }

    public function down()
    {
        Schema::table('persons', function (Blueprint $table) {
            $table->dropColumn(['two_factor_secret', 'two_factor_enabled', 'two_factor_confirmed_at']);
        });
    }
};