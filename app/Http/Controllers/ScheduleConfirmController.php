<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ScheduleConfirmController extends Controller
{
    // 「予定は変わっていません」＝自分の「最後に更新した日」だけを今にする（予定は変えない）
    public function __invoke(Request $request)
    {
        $request->user()->forceFill(['schedule_updated_at' => now()])->save();

        return redirect()->route('schedules.index')->with('status', '予定は変わっていないことを記録しました。');
    }
}
