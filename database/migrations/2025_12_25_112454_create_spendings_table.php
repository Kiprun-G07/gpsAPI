<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('spendings', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            // user_id to link spending to a user
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            // amount spent
            $table->decimal('amount', 10, 2);
            // description of the spending
            $table->string('description');
            // spending_type_id to link to spending_types table
            $table->unsignedBigInteger('spending_type_id'); 
            $table->foreign('spending_type_id')->references('id')->on('spending_types')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spendings');
    }
};
