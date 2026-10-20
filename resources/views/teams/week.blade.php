@php
    // 判定の値 → 画面の文字と色（色だけで分けないよう、文字と印も出す）
    $labels = [
        \App\Services\AvailabilityCalculator::FREE => ['text' => '○ 空き', 'class' => 'bg-green-50 text-green-800'],
        \App\Services\AvailabilityCalculator::BUSY => ['text' => '× 埋まり', 'class' => 'bg-red-50 text-red-800'],
        \App\Services\AvailabilityCalculator::UNKNOWN => ['text' => '？ 不明', 'class' => 'bg-gray-100 text-gray-700'],
    ];
    $weekdays = ['日', '月', '火', '水', '木', '金', '土'];
    $dates = array_keys($week);
    $times = $dates ? array_keys($week[$dates[0]]) : [];
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $team->name }} の1週間</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if ($memberCount === 0)
                <div class="bg-white p-6 shadow sm:rounded-lg">メンバーがいません。</div>
            @else
                {{-- 表の見方 --}}
                <div class="bg-white p-6 shadow sm:rounded-lg">
                    <h3 class="font-semibold mb-2">表の見方</h3>
                    <ul class="flex flex-wrap gap-4 text-sm">
                        <li><span class="px-2 py-1 rounded {{ $labels['free']['class'] }}">{{ $labels['free']['text'] }}</span> メンバー全員が空いている</li>
                        <li><span class="px-2 py-1 rounded {{ $labels['busy']['class'] }}">{{ $labels['busy']['text'] }}</span> 予定が入っている人が1人以上いる</li>
                        <li><span class="px-2 py-1 rounded {{ $labels['unknown']['class'] }}">{{ $labels['unknown']['text'] }}</span> 予定の更新が7日を超えて無い人がいる</li>
                    </ul>
                </div>

                <div class="bg-white p-6 shadow sm:rounded-lg overflow-x-auto">
                    <table class="w-full text-sm text-center border-collapse">
                        <thead>
                            <tr>
                                <th class="p-2 border">時刻</th>
                                @foreach ($dates as $date)
                                    @php $day = \Illuminate\Support\Carbon::parse($date); @endphp
                                    <th class="p-2 border whitespace-nowrap">{{ $day->format('n/j') }}（{{ $weekdays[$day->dayOfWeek] }}）</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($times as $time)
                                <tr>
                                    <th class="p-2 border whitespace-nowrap">{{ $time }}</th>
                                    @foreach ($dates as $date)
                                        @php $label = $labels[$week[$date][$time]['team']]; @endphp
                                        <td class="p-2 border whitespace-nowrap {{ $label['class'] }}">{{ $label['text'] }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- メンバーの予定の更新（Issue #41） --}}
                <div class="bg-white p-6 shadow sm:rounded-lg">
                    <h3 class="font-semibold mb-2">メンバーの予定の更新</h3>
                    <p class="text-sm text-gray-600 mb-3">{{ $staleDays }}日を超えて予定の更新が無い人と、まだ予定を登録していない人は「不明」として扱います。</p>
                    <ul id="member-updates" class="divide-y text-sm">
                        @foreach ($members as $member)
                            <li class="py-2 flex flex-wrap items-center gap-3">
                                <span class="font-medium">{{ $member['name'] }}</span>
                                <span class="text-gray-600">最後に更新した日：{{ $member['updated_at'] ?? 'まだ登録していません' }}</span>
                                @if ($member['unknown'])
                                    <span class="px-2 py-1 rounded {{ $labels['unknown']['class'] }}">{{ $labels['unknown']['text'] }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <a href="{{ route('teams.show', $team) }}" class="text-indigo-600 hover:underline">← チームの画面へ</a>
        </div>
    </div>
</x-app-layout>
