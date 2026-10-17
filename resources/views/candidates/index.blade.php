@php
    $statusLabels = [
        \App\Models\Candidate::OPEN => '募集中',
        \App\Models\Candidate::DECIDED => '確定',
        \App\Models\Candidate::CANCELED => '取り消し',
    ];
    $answerLabels = [
        \App\Models\Response::YES => 'はい',
        \App\Models\Response::NO => 'いいえ',
    ];
    $weekdays = ['日', '月', '火', '水', '木', '金', '土'];
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $team->name }} の候補</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="bg-green-50 text-green-700 p-4 rounded">{{ session('status') }}</div>
            @endif
            <x-input-error :messages="$errors->get('answer')" />

            <div class="bg-white p-6 shadow sm:rounded-lg">
                <h3 class="font-semibold mb-4">候補を作る</h3>
                <form method="POST" action="{{ route('candidates.store', $team) }}" class="space-y-4">
                    @csrf
                    <div class="flex flex-wrap gap-4">
                        <div>
                            <x-input-label for="date" value="日付" />
                            <x-text-input id="date" name="date" type="date" class="mt-1" :value="old('date')" required />
                            <x-input-error class="mt-2" :messages="$errors->get('date')" />
                        </div>
                        <div>
                            <x-input-label for="start_time" value="開始" />
                            <x-text-input id="start_time" name="start_time" type="time" class="mt-1" :value="old('start_time')" required />
                            <x-input-error class="mt-2" :messages="$errors->get('start_time')" />
                        </div>
                        <div>
                            <x-input-label for="end_time" value="終了" />
                            <x-text-input id="end_time" name="end_time" type="time" class="mt-1" :value="old('end_time')" required />
                            <x-input-error class="mt-2" :messages="$errors->get('end_time')" />
                        </div>
                    </div>
                    <div>
                        <x-input-label for="title" value="タイトル（なくてもよい）" />
                        <x-text-input id="title" name="title" type="text" class="mt-1 block w-full" :value="old('title')" />
                        <x-input-error class="mt-2" :messages="$errors->get('title')" />
                    </div>
                    <div>
                        <x-input-label for="needed_count" value="必要な人数（空ならチーム全員）" />
                        <x-text-input id="needed_count" name="needed_count" type="number" min="1" class="mt-1" :value="old('needed_count')" />
                        <x-input-error class="mt-2" :messages="$errors->get('needed_count')" />
                    </div>
                    <x-primary-button>作成</x-primary-button>
                </form>
            </div>

            <div class="bg-white p-6 shadow sm:rounded-lg">
                @forelse ($candidates as $candidate)
                    <div class="py-4 border-b last:border-b-0">
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <p class="font-semibold">
                                    {{ $candidate->date->format('n/j') }}（{{ $weekdays[$candidate->date->dayOfWeek] }}）
                                    {{ substr($candidate->start_time, 0, 5) }}〜{{ substr($candidate->end_time, 0, 5) }}
                                </p>
                                @if ($candidate->title)
                                    <p class="text-gray-700">{{ $candidate->title }}</p>
                                @endif
                            </div>
                            <span class="text-sm px-2 py-1 rounded bg-gray-100">{{ $statusLabels[$candidate->status] }}</span>
                        </div>

                        <p class="text-sm text-gray-500 mt-2">あと{{ $candidate->remainingCount() }}人の返事待ち</p>
                        <p class="text-sm mt-1">
                            今の返事：{{ $answerLabels[$myAnswers[$candidate->id] ?? ''] ?? 'まだ' }}
                        </p>

                        @if ($candidate->status !== \App\Models\Candidate::CANCELED)
                            <form method="POST" action="{{ route('candidates.answer', $candidate) }}" class="flex gap-2 mt-2">
                                @csrf
                                <x-primary-button name="answer" value="{{ \App\Models\Response::YES }}">はい</x-primary-button>
                                <x-secondary-button type="submit" name="answer" value="{{ \App\Models\Response::NO }}">いいえ</x-secondary-button>
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="text-gray-500">まだ候補はありません。</p>
                @endforelse
            </div>

        </div>
    </div>
</x-app-layout>
