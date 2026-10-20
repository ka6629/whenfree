<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ScheduleConfirmController extends Controller
{
    // 「予定は変わっていません」＝自分の「最後に更新した日」だけを今にする（予定は変えない）
    public function __invoke(Request $request)
    {
        $user = $request->user();

        // 予定を1回も登録していない人は押せない（押すと「不明」が外れて、1週間ずっと「空き」に見えるため）
        if ($user->schedule_updated_at === null) {
            return redirect()->route('schedules.index')->with('status', '先に予定を登録してください。');
        }

        $user->forceFill(['schedule_updated_at' => now()])->save();

        return redirect()->route('schedules.index')->with('status', '予定は変わっていないことを記録しました。');
    }
}
