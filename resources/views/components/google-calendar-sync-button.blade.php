<div class="bg-white p-4 shadow sm:rounded-lg space-y-2">
    @if (session('status'))
        <div class="bg-green-50 text-green-700 p-3 rounded text-sm">{{ session('status') }}</div>
    @endif

    <div class="flex flex-wrap items-center gap-3">
        <form method="POST" action="{{ route('google-calendar.sync') }}">
            @csrf
            <x-secondary-button type="submit">Google カレンダーを読み込む</x-secondary-button>
        </form>
        <span class="text-sm text-gray-600">最後に読み込んだ日時：{{ $calendar->syncedAtLabel() }}</span>
    </div>
</div>
