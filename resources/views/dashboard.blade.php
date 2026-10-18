<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">ホーム</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- 予定の更新状況 --}}
            @if ($isStale)
                <div class="bg-yellow-50 border border-yellow-200 p-6 sm:rounded-lg">
                    <h3 class="font-semibold text-yellow-800 mb-1">予定を確認してください</h3>
                    <p class="text-sm text-yellow-800">
                        @if ($updatedAt)
                            最後に更新したのは {{ $updatedAt->format('Y/m/d') }} です。
                        @else
                            まだ予定を登録していません。
                        @endif
                        {{ $staleDays }}日を超えて更新が無いと、チームの1週間で「不明」として扱われます。
                    </p>
                    <a href="{{ route('schedules.index') }}" class="inline-block mt-3 text-indigo-600 hover:underline">予定を確認する →</a>
                </div>
            @else
                <div class="bg-white p-6 shadow sm:rounded-lg">
                    <h3 class="font-semibold mb-1">予定</h3>
                    <p class="text-sm text-gray-600">最後に更新した日：{{ $updatedAt->format('Y/m/d H:i') }}</p>
                    <a href="{{ route('schedules.index') }}" class="inline-block mt-3 text-indigo-600 hover:underline">予定を見る →</a>
                </div>
            @endif

            {{-- 自分のチーム --}}
            <div class="bg-white p-6 shadow sm:rounded-lg">
                <h3 class="font-semibold mb-4">自分のチーム</h3>

                @forelse ($teams as $team)
                    <div class="py-3 border-b last:border-b-0 flex items-center justify-between gap-4">
                        <div>
                            <a href="{{ route('teams.show', $team) }}" class="font-medium text-indigo-600 hover:underline">{{ $team->name }}</a>
                            <span class="text-sm text-gray-500 ml-2">{{ $team->users_count }}人</span>
                        </div>
                        <a href="{{ route('candidates.index', $team) }}" class="text-sm text-indigo-600 hover:underline">候補を見る →</a>
                    </div>
                @empty
                    <p class="text-gray-500">まだチームに所属していません。</p>
                    <a href="{{ route('teams.index') }}" class="inline-block mt-3 text-indigo-600 hover:underline">チームを作る・参加する →</a>
                @endforelse
            </div>

        </div>
    </div>
</x-app-layout>