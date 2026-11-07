<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">チーム</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="bg-white p-6 shadow sm:rounded-lg">
                <h3 class="font-semibold mb-4">自分のチーム</h3>
                @forelse ($teams as $team)
                    <a href="{{ route('teams.show', $team) }}" class="block py-2 text-indigo-600 hover:underline">
                        {{ $team->name }}
                    </a>
                @empty
                    <p class="text-gray-500">まだチームに所属していません。</p>
                @endforelse
            </div>

            <div class="bg-white p-6 shadow sm:rounded-lg">
                <h3 class="font-semibold mb-4">チームを作る</h3>
                <form method="POST" action="{{ route('teams.store') }}" class="flex gap-2">
                    @csrf
                    <x-text-input name="name" placeholder="チーム名を入力" class="flex-1" :value="old('name')" />
                    <x-primary-button>作成</x-primary-button>
                </form>
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div class="bg-white p-6 shadow sm:rounded-lg">
                <h3 class="font-semibold mb-4">招待コードで参加する</h3>
                <form method="POST" action="{{ route('teams.join') }}" class="flex gap-2">
                    @csrf
                    <x-text-input name="invite_code" placeholder="招待コードを入力" class="flex-1" :value="old('invite_code')" />
                    <x-primary-button>参加</x-primary-button>
                </form>
                <x-input-error :messages="$errors->get('invite_code')" class="mt-2" />
            </div>

        </div>
    </div>
</x-app-layout>