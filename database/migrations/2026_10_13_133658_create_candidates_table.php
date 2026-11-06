<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('title')->nullable();
            $table->unsignedTinyInteger('needed_count')->nullable(); // null ならチーム全員
            $table->enum('status', ['open', 'decided', 'canceled'])->default('open'); // 募集中・確定・取り消し
            $table->timestamps();

            $table->index(['team_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidates');
    }
};
