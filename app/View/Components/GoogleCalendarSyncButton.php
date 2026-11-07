<?php

namespace App\View\Components;

use App\Models\GoogleCalendar;
use Illuminate\View\Component;
use Illuminate\View\View;

// Google カレンダーを「今すぐ読み込む」ボタン（Issue #54・担当C）。つないでいる人にだけ出す
class GoogleCalendarSyncButton extends Component
{
    public ?GoogleCalendar $calendar;

    public function __construct()
    {
        $this->calendar = GoogleCalendar::of(auth()->user());
    }

    public function shouldRender(): bool
    {
        return $this->calendar !== null;
    }

    public function render(): View
    {
        return view('components.google-calendar-sync-button');
    }
}
