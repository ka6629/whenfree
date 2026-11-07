<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('answer', ['yes', 'no']);
            $table->timestamps();

            $table->unique(['candidate_id', 'user_id']); // 1人1つの返事。答え直しは上書き
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('responses');
    }
};
