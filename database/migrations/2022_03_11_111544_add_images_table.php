<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Images that have been resized
        Schema::create('images', function (Blueprint $table) {
            $table->increments('id');
            $table->text('url')->nullable();
            $table->text('path')->nullable();
            $table->string('size_in_kb')->nullable();
            $table->string('width')->nullable();
            $table->string('height')->nullable();
            $table->string('quality')->nullable();
            $table->integer('nr_times_processed')->default(0); // The number of times we've tried to process this image
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('processed_at')->nullable();
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
        Schema::dropIfExists('images');
    }
};
