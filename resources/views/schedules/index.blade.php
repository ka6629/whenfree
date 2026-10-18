@php($days = ['日', '月', '火', '水', '木', '金', '土'])

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">自分の予定</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="bg-green-50 text-green-700 p-4 rounded">{{ session('status') }}</div>
            @endif

            <p class="text-sm text-gray-600">
                最後に更新した日：{{ auth()->user()->schedule_updated_at?->format('Y/m/d H:i') ?? 'まだ登録していません' }}
            </p>

            {{-- 毎週の予定 --}}
            <div class="bg-white p-6 shadow sm:rounded-lg">
                <h3 class="font-semibold mb-4">毎週の予定</h3>

                <form method="POST" action="{{ route('schedules.store') }}" class="flex flex-wrap gap-2 items-center">
                    @csrf
                    <input type="hidden" name="type" value="weekly">
                    <select name="day_of_week" class="border-gray-300 rounded-md shadow-sm">
                        @foreach ($days as $i => $label)
                            <option value="{{ $i }}" @selected(old('type') === 'weekly' && old('day_of_week') == $i)>{{ $label }}曜</option>
                        @endforeach
                    </select>
                    <x-text-input type="time" name="start_time" :value="old('type') === 'weekly' ? old('start_time') : ''" />
                    <span>〜</span>
                    <x-text-input type="time" name="end_time" :value="old('type') === 'weekly' ? old('end_time') : ''" />
                    <x-text-input name="title" placeholder="タイトル（任意）" class="flex-1" :value="old('type') === 'weekly' ? old('title') : ''" />
                    <x-primary-button>追加</x-primary-button>
                </form>
                @if (old('type') === 'weekly')
                    <x-input-error :messages="$errors->all()" class="mt-2" />
                @endif

                <ul class="mt-4 divide-y">
                    @forelse ($weekly as $schedule)
                        <li class="py-2 flex items-center gap-3">
                            <span class="w-12">{{ $days[$schedule->day_of_week] }}曜</span>
                            <span class="w-32">{{ substr($schedule->start_time, 0, 5) }}〜{{ substr($schedule->end_time, 0, 5) }}</span>
                            <span class="flex-1">{{ $schedule->title }}</span>
                            <a href="{{ route('schedules.edit', $schedule) }}" class="text-indigo-600 hover:underline">編集</a>
                            <form method="POST" action="{{ route('schedules.destroy', $schedule) }}" onsubmit="return confirm('削除しますか？')">
                                @csrf
                                @method('DELETE')
                                <button class="text-red-600 hover:underline">削除</button>
                            </form>
                        </li>
                    @empty
                        <li class="py-2 text-gray-500">まだありません。</li>
                    @endforelse
                </ul>
            </div>

            {{-- 単発の予定 --}}
            <div class="bg-white p-6 shadow sm:rounded-lg">
                <h3 class="font-semibold mb-4">単発の予定</h3>

                <form method="POST" action="{{ route('schedules.store') }}" class="flex flex-wrap gap-2 items-center">
                    @csrf
                    <input type="hidden" name="type" value="single">
                    <x-text-input type="date" name="date" :value="old('type') === 'single' ? old('date') : ''" />
                    <x-text-input type="time" name="start_time" :value="old('type') === 'single' ? old('start_time') : ''" />
                    <span>〜</span>
                    <x-text-input type="time" name="end_time" :value="old('type') === 'single' ? old('end_time') : ''" />
                    <x-text-input name="title" placeholder="タイトル（任意）" class="flex-1" :value="old('type') === 'single' ? old('title') : ''" />
                    <x-primary-button>追加</x-primary-button>
                </form>
                @if (old('type') === 'single')
                    <x-input-error :messages="$errors->all()" class="mt-2" />
                @endif

                <ul class="mt-4 divide-y">
                    @forelse ($single as $schedule)
                        <li class="py-2 flex items-center gap-3">
                            <span class="w-28">{{ $schedule->date->format('n/j') }}（{{ $days[$schedule->date->dayOfWeek] }}）</span>
                            <span class="w-32">{{ substr($schedule->start_time, 0, 5) }}〜{{ substr($schedule->end_time, 0, 5) }}</span>
                            <span class="flex-1">{{ $schedule->title }}</span>
                            <a href="{{ route('schedules.edit', $schedule) }}" class="text-indigo-600 hover:underline">編集</a>
                            <form method="POST" action="{{ route('schedules.destroy', $schedule) }}" onsubmit="return confirm('削除しますか？')">
                                @csrf
                                @method('DELETE')
                                <button class="text-red-600 hover:underline">削除</button>
                            </form>
                        </li>
                    @empty
                        <li class="py-2 text-gray-500">まだありません。</li>
                    @endforelse
                </ul>
            </div>

        </div>
    </div>
</x-app-layout>