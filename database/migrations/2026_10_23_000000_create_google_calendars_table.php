<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // つないだ Google カレンダー（1人1つ）。トークンはモデルで暗号化して入れる
        Schema::create('google_calendars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('synced_at')->nullable(); // 最後に読み込んだ日時
            $table->timestamps();
        });

        // Google カレンダーから読み込んだ「埋まり」の時間。予定のタイトルなどの中身は持たない
        Schema::create('google_busy_times', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();

            $table->index(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_busy_times');
        Schema::dropIfExists('google_calendars');
    }
};
