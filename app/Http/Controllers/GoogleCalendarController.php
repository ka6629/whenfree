<?php

namespace App\Http\Controllers;

use App\Models\GoogleCalendar;
use App\Services\GoogleCalendarService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

// Google カレンダーをつなぐ画面（Issue #54・担当C）
class GoogleCalendarController extends Controller
{
    public function show(Request $request)
    {
        $calendar = GoogleCalendar::of($request->user());
        $configured = filled(config('services.google.client_id'));

        return view('google-calendar.show', compact('calendar', 'configured'));
    }

    // Google の「つなぐ」画面へ
    public function redirect(Request $request, GoogleCalendarService $service)
    {
        $state = Str::random(40);
        $request->session()->put('google_calendar_state', $state);

        return redirect()->away($service->authUrl($state));
    }

    // Google から戻ってきたところ
    public function callback(Request $request, GoogleCalendarService $service)
    {
        $state = $request->session()->pull('google_calendar_state');

        if (! $state || ! hash_equals($state, (string) $request->query('state')) || ! $request->filled('code')) {
            return redirect()->route('google-calendar.show')->with('status', 'Google カレンダーをつなげませんでした。もう一度お試しください。');
        }

        try {
            $service->connect($request->user(), $request->query('code'));
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('google-calendar.show')->with('status', 'Google カレンダーをつなげませんでした。もう一度お試しください。');
        }

        return redirect()->route('google-calendar.show')->with('status', 'Google カレンダーをつなぎました。');
    }

    // 今すぐ読み込む（この画面とチームの1週間の画面から押せる。押した画面に戻る）
    public function sync(Request $request, GoogleCalendarService $service)
    {
        $calendar = GoogleCalendar::of($request->user());
        abort_unless($calendar, 404);

        try {
            $service->sync($calendar);
            $message = '予定を読み込みました。';
        } catch (Throwable $e) {
            report($e);
            $message = '読み込めませんでした。Google カレンダーの画面からつなぎ直してください。';
        }

        return back(fallback: route('google-calendar.show'))->with('status', $message);
    }

    public function destroy(Request $request, GoogleCalendarService $service)
    {
        $service->disconnect($request->user());

        return redirect()->route('google-calendar.show')->with('status', 'Google カレンダーとのつながりを外しました。');
    }
}
