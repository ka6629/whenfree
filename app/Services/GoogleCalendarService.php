<?php

namespace App\Services;

use App\Models\GoogleBusyTime;
use App\Models\GoogleCalendar;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Google カレンダーとつないで、「埋まり」の時間を読み込む（Issue #54・担当C）。
 * freeBusy API を使うので、Google から受け取るのは埋まっている時間だけ（タイトルなどの中身は受け取らない）。
 * くり返しの予定も、Google が1回ずつに分けて返してくれる。
 */
class GoogleCalendarService
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const FREE_BUSY_URL = 'https://www.googleapis.com/calendar/v3/freeBusy';

    private const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';

    /** 空き・埋まりだけを読む権限（予定の中身は読めない） */
    private const SCOPE = 'https://www.googleapis.com/auth/calendar.freebusy';

    /** 今日から何日先まで読み込むか */
    private const DAYS = 14;

    /** 最後に読み込んでからこの分数が過ぎたら、チームの1週間を開いたときに読み込み直す */
    private const STALE_MINUTES = 15;

    /** Google の「つなぐ」画面の URL */
    public function authUrl(string $state): string
    {
        return self::AUTH_URL.'?'.http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => config('services.google.redirect'),
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'access_type' => 'offline', // あとで読み込み直せるよう refresh_token をもらう
            'prompt' => 'consent',
            'state' => $state,
        ]);
    }

    /** Google から戻ってきたときの code をトークンに替えて保存し、すぐに読み込む */
    public function connect(User $user, string $code): GoogleCalendar
    {
        $token = Http::asForm()->post(self::TOKEN_URL, [
            'code' => $code,
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => config('services.google.redirect'),
            'grant_type' => 'authorization_code',
        ])->throw()->json();

        $calendar = GoogleCalendar::updateOrCreate(['user_id' => $user->id], [
            'access_token' => $token['access_token'],
            'refresh_token' => $token['refresh_token'] ?? null,
            'expires_at' => now()->addSeconds($token['expires_in'] ?? 3600),
        ]);

        $this->sync($calendar);

        return $calendar;
    }

    /** つなぐのをやめる。読み込んだ時間も消す */
    public function disconnect(User $user): void
    {
        $calendar = GoogleCalendar::of($user);

        if ($calendar) {
            // Google 側の許可も取り消す（失敗しても whenfree 側は消す）
            rescue(fn () => Http::asForm()->post(self::REVOKE_URL, ['token' => $calendar->refresh_token ?? $calendar->access_token]));
            $calendar->delete();
        }

        GoogleBusyTime::deleteFor($user->id);
    }

    /** 今日から DAYS 日ぶんの「埋まり」を読み込み直す */
    public function sync(GoogleCalendar $calendar): void
    {
        $timezone = config('app.timezone');
        $from = CarbonImmutable::today($timezone);
        $to = $from->addDays(self::DAYS);

        $busy = Http::withToken($this->accessToken($calendar))
            ->post(self::FREE_BUSY_URL, [
                'timeMin' => $from->toRfc3339String(),
                'timeMax' => $to->toRfc3339String(),
                'timeZone' => $timezone,
                'items' => [['id' => 'primary']],
            ])->throw()->json('calendars.primary.busy', []);

        $rows = [];
        foreach ($busy as $period) {
            $start = CarbonImmutable::parse($period['start'])->setTimezone($timezone);
            $end = CarbonImmutable::parse($period['end'])->setTimezone($timezone);
            array_push($rows, ...$this->splitByDay($calendar->user_id, $start, $end));
        }

        GoogleBusyTime::replaceFor($calendar->user_id, $rows);

        $calendar->update(['synced_at' => now()]);
        // 読み込めた人は予定が今のものなので、「不明」にしない
        $calendar->user->forceFill(['schedule_updated_at' => now()])->save();
    }

    /** チームの1週間を開いたときに、しばらく読み込んでいない人だけ読み込み直す。失敗しても画面は出す */
    public function syncIfStale(iterable $userIds): void
    {
        GoogleCalendar::needsSync($userIds, self::STALE_MINUTES)
            ->each(function (GoogleCalendar $calendar) {
                try {
                    $this->sync($calendar);
                } catch (Throwable $e) {
                    report($e); // 前に読み込んだ時間をそのまま使う
                }
            });
    }

    /** 期限が切れていたら refresh_token で新しいトークンをもらう */
    private function accessToken(GoogleCalendar $calendar): string
    {
        if ($calendar->expires_at && $calendar->expires_at->isAfter(now()->addMinute())) {
            return $calendar->access_token;
        }

        $token = Http::asForm()->post(self::TOKEN_URL, [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'refresh_token' => $calendar->refresh_token,
            'grant_type' => 'refresh_token',
        ])->throw()->json();

        $calendar->update([
            'access_token' => $token['access_token'],
            'expires_at' => now()->addSeconds($token['expires_in'] ?? 3600),
        ]);

        return $calendar->access_token;
    }

    /** 日をまたぐ時間は、日ごとに分ける（夜0時で区切る。その日の終わりは 23:59 とする） */
    private function splitByDay(int $userId, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $rows = [];
        $now = now();

        while ($start < $end) {
            $nextDay = $start->startOfDay()->addDay();
            $rows[] = [
                'user_id' => $userId,
                'date' => $start->format('Y-m-d'),
                'start_time' => $start->format('H:i'),
                'end_time' => $end < $nextDay ? $end->format('H:i') : '23:59',
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $start = $nextDay;
        }

        return $rows;
    }
}
