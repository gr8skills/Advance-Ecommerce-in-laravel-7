<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddItemDetailsToSavedOrder extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasColumn('orders', 'items_detail')){
            Schema::table('orders', function (Blueprint $table){
                $table->json('items_detail')->nullable()->after('transaction_id');
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
        if(Schema::hasColumn('orders', 'items_detail')){
            Schema::table('orders', function (Blueprint $table){
                $table->dropColumn('items_detail');
            });
        }
    }
}
