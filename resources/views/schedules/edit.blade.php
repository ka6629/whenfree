@php($days = ['日', '月', '火', '水', '木', '金', '土'])

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            予定の編集（{{ $schedule->type === 'weekly' ? '毎週' : '単発' }}）
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 shadow sm:rounded-lg">
                <form method="POST" action="{{ route('schedules.update', $schedule) }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    @if ($schedule->type === 'weekly')
                        <div>
                            <label class="block text-sm mb-1">曜日</label>
                            <select name="day_of_week" class="border-gray-300 rounded-md shadow-sm">
                                @foreach ($days as $i => $label)
                                    <option value="{{ $i }}" @selected(old('day_of_week', $schedule->day_of_week) == $i)>{{ $label }}曜</option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <div>
                            <label class="block text-sm mb-1">日付</label>
                            <x-text-input type="date" name="date" :value="old('date', $schedule->date?->format('Y-m-d'))" />
                        </div>
                    @endif

                    <div class="flex items-center gap-2">
                        <x-text-input type="time" name="start_time" :value="old('start_time', substr($schedule->start_time, 0, 5))" />
                        <span>〜</span>
                        <x-text-input type="time" name="end_time" :value="old('end_time', substr($schedule->end_time, 0, 5))" />
                    </div>

                    <div>
                        <x-text-input name="title" placeholder="タイトル（任意）" class="w-full" :value="old('title', $schedule->title)" />
                    </div>

                    <x-input-error :messages="$errors->all()" />

                    <div class="flex items-center gap-4">
                        <x-primary-button>保存</x-primary-button>
                        <a href="{{ route('schedules.index') }}" class="text-gray-600 hover:underline">戻る</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>