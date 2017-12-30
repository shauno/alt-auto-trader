<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateExchangeRateLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('exchange_rate_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('exchange_rate_id')->unsigned();
            $table->foreign('exchange_rate_id')->references('id')->on('exchange_rates');
            $table->decimal('ask_rate', 32, 16);
            $table->decimal('bid_rate', 32, 16);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('exchange_rate_logs');
    }
}
