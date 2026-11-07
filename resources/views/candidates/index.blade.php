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
            <x-input-error :messages="$errors->get('status')" />

            {{-- まだ返事をしていない、募集中の候補の数（Issue #25） --}}
            @if ($unansweredCount > 0)
                <div class="bg-yellow-50 text-yellow-800 p-4 rounded">まだ返事をしていない候補が{{ $unansweredCount }}件あります。</div>
            @endif

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
                                    {{ $candidate->dateLabel() }}
                                    {{ substr($candidate->start_time, 0, 5) }}〜{{ substr($candidate->end_time, 0, 5) }}
                                </p>
                                @if ($candidate->title)
                                    <p class="text-gray-700">{{ $candidate->title }}</p>
                                @endif
                            </div>
                            <span class="text-sm px-2 py-1 rounded bg-gray-100">{{ $candidate->statusLabel() }}</span>
                        </div>

                        <p class="text-sm text-gray-500 mt-2">あと<span data-remaining="{{ $candidate->id }}">{{ $remainingCounts[$candidate->id] }}</span>人の返事待ち</p>
                        <p class="text-sm mt-1">
                            今の返事：{{ \App\Models\Response::labelFor($myAnswers[$candidate->id] ?? null) }}
                        </p>

                        @if ($candidate->isAnswerable())
                            <form method="POST" action="{{ route('candidates.answer', $candidate) }}" class="flex gap-2 mt-2">
                                @csrf
                                <x-primary-button name="answer" value="{{ \App\Models\Response::YES }}">はい</x-primary-button>
                                <x-secondary-button type="submit" name="answer" value="{{ \App\Models\Response::NO }}">いいえ</x-secondary-button>
                            </form>
                        @endif

                        {{-- 確定・取り消しは、候補を作った人だけ（Issue #23） --}}
                        @if ($candidate->canChangeStatusBy(auth()->user()))
                            <form method="POST" action="{{ route('candidates.status', $candidate) }}" class="flex gap-2 mt-2">
                                @csrf
                                <x-secondary-button type="submit" name="status" value="{{ \App\Models\Candidate::DECIDED }}">確定する</x-secondary-button>
                                <x-danger-button name="status" value="{{ \App\Models\Candidate::CANCELED }}">取り消す</x-danger-button>
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="text-gray-500">まだ候補はありません。</p>
                @endforelse
            </div>

        </div>
    </div>

    {{-- 10秒おきに「あと◯人の返事待ち」だけを取りに行って書きかえる（Issue #24）。ページは読み込み直さないので、フォームの入力は消えない --}}
    <script>
        setInterval(async () => {
            if (document.hidden) {
                return; // ほかのタブを見ている間は取りに行かない
            }
            try {
                const response = await fetch(@json(route('candidates.counts', $team)), { headers: { Accept: 'application/json' } });
                if (!response.ok) {
                    return;
                }
                const counts = await response.json();
                document.querySelectorAll('[data-remaining]').forEach((element) => {
                    const count = counts[element.dataset.remaining];
                    if (count !== undefined) {
                        element.textContent = count;
                    }
                });
            } catch (error) {
                // つながらないときは、次の10秒後にまた試す
            }
        }, 10000);
    </script>
</x-app-layout>
