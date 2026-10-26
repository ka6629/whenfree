@php
    // メンバーの一覧で「不明」の人に付ける印
    $unknownBadge = ['text' => '？ 不明', 'class' => 'bg-gray-100 text-gray-700'];
    $weekdays = ['日', '月', '火', '水', '木', '金', '土'];
    $dates = array_keys($cells);
    $times = $dates ? array_keys($cells[$dates[0]]) : [];
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $team->name }} の1週間</h2>
    </x-slot>

    <div class="py-8" x-data="weekPicker(@js($endTimes))" @keydown.escape.window="close()">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Google カレンダーを読み込むボタン（つないでいる人にだけ出る・担当C Issue #54） --}}
            <x-google-calendar-sync-button />

            @if ($memberCount === 0)
                <div class="bg-white p-6 shadow sm:rounded-lg">メンバーがいません。</div>
            @else
                {{-- 表の見方 --}}
                <div class="bg-white p-6 shadow sm:rounded-lg">
                    <h3 class="font-semibold mb-2">表の見方</h3>
                    <ul class="space-y-1 text-sm">
                        <li><span class="inline-block w-12 text-center px-2 py-1 rounded bg-green-300 text-green-950">○</span> 全員が空いている</li>
                        <li><span class="inline-block w-12 text-center px-2 py-1 rounded bg-green-100 text-green-900">△2/4</span> 予定がある人がいて、全員ではない（数字は 空いている人数／全員）</li>
                        <li><span class="inline-block w-12 text-center px-2 py-1 rounded bg-red-50 text-red-700">×</span> 全員に予定がある</li>
                        <li><span class="inline-block w-12 text-center px-2 py-1 rounded bg-gray-100 text-gray-700">？3/4</span> 予定を確かめていない人がいる（くわしくは下の「メンバーの予定の更新」）</li>
                    </ul>
                    <p class="text-sm text-gray-600 mt-3">マスを押すと、だれが空いているかを見て、そのまま候補を作れます。空いている人が多いほど、緑が濃くなります。</p>
                </div>

                <div class="bg-white p-6 shadow sm:rounded-lg overflow-x-auto">
                    <table class="w-full text-sm text-center border-collapse">
                        <thead>
                            <tr>
                                <th class="p-2 border sticky left-0 z-10 bg-white">時刻</th>
                                @foreach ($dates as $date)
                                    @php $day = \Illuminate\Support\Carbon::parse($date); @endphp
                                    <th class="p-2 border whitespace-nowrap">{{ $day->format('n/j') }}（{{ $weekdays[$day->dayOfWeek] }}）</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($times as $time)
                                <tr>
                                    <th class="p-2 border whitespace-nowrap sticky left-0 z-10 bg-white">{{ $time }}</th>
                                    @foreach ($dates as $date)
                                        @php $cell = $cells[$date][$time]; @endphp
                                        <td class="p-0 border whitespace-nowrap">
                                            @if ($cell['past'])
                                                {{-- 過ぎた時間は押せない --}}
                                                <span class="block p-2 opacity-40 {{ $cell['class'] }}" title="{{ $cell['label'] }}（過ぎた時間）">{{ $cell['text'] }}</span>
                                            @else
                                                <button type="button"
                                                        class="w-full p-2 {{ $cell['class'] }} hover:ring-2 hover:ring-inset hover:ring-indigo-400 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-500"
                                                        title="{{ $cell['label'] }}"
                                                        aria-label="{{ $cell['dateLabel'] }} {{ $cell['time'] }} {{ $cell['label'] }}"
                                                        @click="open(@js($cell))">{{ $cell['text'] }}</button>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- メンバーの予定の更新（Issue #41） --}}
                <div class="bg-white p-6 shadow sm:rounded-lg">
                    <h3 class="font-semibold mb-2">メンバーの予定の更新</h3>
                    <p class="text-sm text-gray-600 mb-3">毎週{{ $weekdays[$checkDay] }}曜 {{ $checkTime }} を過ぎると、全員がいったん「不明」になります。<a href="{{ route('schedules.index') }}" class="text-indigo-600 hover:underline">自分の予定</a>の画面で予定を登録・編集・削除するか、「予定は変わっていません」を押すと、「最後に更新した日」が新しくなり、「不明」が外れます。まだ予定を登録していない人は「不明」のままです。</p>
                    <ul id="member-updates" class="divide-y text-sm">
                        @foreach ($members as $member)
                            <li class="py-2 flex flex-wrap items-center gap-3">
                                <span class="font-medium">{{ $member['name'] }}</span>
                                <span class="text-gray-600">最後に更新した日：{{ $member['updated_at'] ?? 'まだ登録していません' }}</span>
                                @if ($member['unknown'])
                                    <span class="px-2 py-1 rounded {{ $unknownBadge['class'] }}">{{ $unknownBadge['text'] }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>

                {{-- マスを押した時の小窓（#56） --}}
                <div x-show="cell" style="display: none"
                     class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                     @click.self="close()">
                    <div class="bg-white rounded-lg shadow-xl w-full max-w-md" role="dialog" aria-modal="true">
                        <div class="flex items-center justify-between bg-gray-800 text-white px-5 py-3 rounded-t-lg">
                            <h3 class="font-semibold" x-text="cell ? cell.dateLabel + ' ' + cell.time + '〜' : ''"></h3>
                            <button type="button" class="text-xl leading-none" @click="close()" aria-label="閉じる">&times;</button>
                        </div>

                        <template x-if="cell">
                            <div class="p-5 space-y-4 text-sm">
                                <dl class="space-y-3">
                                    <div>
                                        <dt class="font-medium text-green-800">空いている（<span x-text="cell.free.length"></span>人）</dt>
                                        <dd class="text-gray-700" x-text="cell.free.length ? cell.free.join('・') : 'いません'"></dd>
                                    </div>
                                    <div x-show="cell.busy.length">
                                        <dt class="font-medium text-red-700">予定あり（<span x-text="cell.busy.length"></span>人）</dt>
                                        <dd class="text-gray-700" x-text="cell.busy.join('・')"></dd>
                                    </div>
                                    <div x-show="cell.unknown.length">
                                        <dt class="font-medium text-gray-700">不明（<span x-text="cell.unknown.length"></span>人）</dt>
                                        <dd class="text-gray-700" x-text="cell.unknown.join('・')"></dd>
                                    </div>
                                </dl>

                                <form method="POST" action="{{ route('candidates.store', $team) }}" class="space-y-3 border-t pt-4">
                                    @csrf
                                    <input type="hidden" name="date" :value="cell.date">
                                    <input type="hidden" name="start_time" :value="cell.time">

                                    <div class="flex items-center gap-2">
                                        <span x-text="cell.time"></span>
                                        <span>〜</span>
                                        <select name="end_time" class="border-gray-300 rounded-md shadow-sm" aria-label="終了の時刻">
                                            <template x-for="t in endOptions()" :key="t">
                                                <option :value="t" x-text="t"></option>
                                            </template>
                                        </select>
                                    </div>

                                    <x-text-input name="title" placeholder="タイトル（任意）" class="w-full" />

                                    <div class="flex justify-end">
                                        <x-primary-button>この時間で候補を作る</x-primary-button>
                                    </div>
                                </form>
                            </div>
                        </template>
                    </div>
                </div>
            @endif

            <a href="{{ route('teams.show', $team) }}" class="text-indigo-600 hover:underline">← チームの画面へ</a>
        </div>
    </div>

    <script>
        // マスを押した時の小窓（#56）。開始は押したマスの時刻で、終了だけ選べる
        function weekPicker(endTimes) {
            return {
                cell: null,
                open(cell) { this.cell = cell; },
                close() { this.cell = null; },
                endOptions() {
                    return this.cell ? endTimes.filter((t) => t > this.cell.time) : [];
                },
            };
        }
    </script>
</x-app-layout>