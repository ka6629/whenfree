<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $team->name }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="bg-green-50 text-green-700 p-4 rounded">{{ session('status') }}</div>
            @endif

            <div class="bg-white p-6 shadow sm:rounded-lg">
                <h3 class="font-semibold mb-2">招待コード</h3>
                <p class="text-2xl font-mono tracking-widest">{{ $team->invite_code }}</p>
                <p class="text-sm text-gray-500 mt-1">このコードをメンバーに伝えると、チームに参加できます。</p>
            </div>

            <div class="bg-white p-6 shadow sm:rounded-lg">
                <h3 class="font-semibold mb-4">メンバー（{{ $members->count() }}人）</h3>
                <ul class="space-y-1">
                    @foreach ($members as $member)
                        <li>{{ $member->name }}</li>
                    @endforeach
                </ul>
            </div>

            <a href="{{ route('teams.index') }}" class="text-indigo-600 hover:underline">← チーム一覧へ</a>
        </div>
    </div>
</x-app-layout>