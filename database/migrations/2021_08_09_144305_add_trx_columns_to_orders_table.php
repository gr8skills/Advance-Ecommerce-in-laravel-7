<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTrxColumnsToOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumns('orders', ['transaction_id','online_trx_status','transaction_ref','charged_amount','message','data'])) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('online_trx_status')->nullable()->after('address2');
                $table->string('transaction_ref')->nullable()->after('online_trx_status');
                $table->string('charged_amount')->nullable()->after('transaction_ref');
                $table->string('message')->nullable()->after('charged_amount');
                $table->json('data')->nullable()->after('message');
                $table->json('transaction_id')->nullable()->after('data');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumns('orders', ['transaction_id','online_trx_status','transaction_ref','charged_amount','message','data'])) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('online_trx_status');
                $table->dropColumn('transaction_ref');
                $table->dropColumn('charged_amount');
                $table->dropColumn('message');
                $table->dropColumn('data');
                $table->dropColumn('transaction_id');
            });
        }
    }
}
