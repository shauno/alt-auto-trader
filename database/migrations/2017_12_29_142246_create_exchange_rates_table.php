<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateExchangeRatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('exchange_id')->unsigned();
            $table->foreign('exchange_id')->references('id')->on('exchanges');
            $table->string('name', 255);
            $table->string('base_iso', 12);
            $table->string('counter_iso', 12);
            $table->unique(['exchange_id', 'base_iso', 'counter_iso']);
            $table->decimal('ask_rate', 32, 16);
            $table->decimal('bid_rate', 32, 16);
            $table->timestamps();
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('exchange_rates');
    }
}
