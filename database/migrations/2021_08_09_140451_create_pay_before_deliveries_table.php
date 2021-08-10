<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePayBeforeDeliveriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pay_before_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_id');
            $table->string('status');
            $table->string('transaction_ref');
            $table->string('charged_amount');
            $table->string('message');
            $table->json('data');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pay_before_deliveries');
    }
}
