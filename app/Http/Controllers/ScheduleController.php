<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    // 自分の予定の一覧（追加のフォームも同じ画面）
    public function index(Request $request)
    {
        $user = $request->user();

        $weekly = $user->schedules()->where('type', 'weekly')
            ->orderBy('day_of_week')->orderBy('start_time')->get();

        // 単発の予定は今日以降だけ出す
        $single = $user->schedules()->where('type', 'single')
            ->whereDate('date', '>=', today())
            ->orderBy('date')->orderBy('start_time')->get();

        return view('schedules.index', compact('weekly', 'single'));
    }

    // 予定を追加する
    public function store(Request $request)
    {
        $request->user()->schedules()->create($this->validated($request));
        $this->markUpdated($request);

        return redirect()->route('schedules.index')->with('status', '予定を追加しました。');
    }

    // 編集の画面
    public function edit(Request $request, Schedule $schedule)
    {
        $this->authorizeOwner($request, $schedule);

        return view('schedules.edit', compact('schedule'));
    }

    // 予定を更新する（毎週／単発の種類は変えない）
    public function update(Request $request, Schedule $schedule)
    {
        $this->authorizeOwner($request, $schedule);

        $request->merge(['type' => $schedule->type]);
        $schedule->update($this->validated($request));
        $this->markUpdated($request);

        return redirect()->route('schedules.index')->with('status', '予定を更新しました。');
    }

    // 予定を削除する
    public function destroy(Request $request, Schedule $schedule)
    {
        $this->authorizeOwner($request, $schedule);

        $schedule->delete();
        $this->markUpdated($request);

        return redirect()->route('schedules.index')->with('status', '予定を削除しました。');
    }

    // 入力のチェック（終了は開始より後・日をまたぐ予定は受け付けない）
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'type' => ['required', 'in:weekly,single'],
            'title' => ['nullable', 'string', 'max:50'],
            'day_of_week' => ['required_if:type,weekly', 'nullable', 'integer', 'between:0,6'],
            'date' => ['required_if:type,single', 'nullable', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
        ], [
            'day_of_week.required_if' => '曜日を選んでください。',
            'date.required_if' => '日付を入れてください。',
            'start_time.required' => '開始の時刻を入れてください。',
            'end_time.required' => '終了の時刻を入れてください。',
            'end_time.after' => '終了の時刻は開始の時刻より後にしてください。',
        ]);

        // 毎週なら日付、単発なら曜日は使わない
        if ($data['type'] === 'weekly') {
            $data['date'] = null;
        } else {
            $data['day_of_week'] = null;
        }

        return $data;
    }

    // 「最後に更新した日」を今にする
    private function markUpdated(Request $request): void
    {
        $request->user()->forceFill(['schedule_updated_at' => now()])->save();
    }

    // 自分の予定でなければ 403
    private function authorizeOwner(Request $request, Schedule $schedule): void
    {
        abort_unless($schedule->user_id === $request->user()->id, 403);
    }
}