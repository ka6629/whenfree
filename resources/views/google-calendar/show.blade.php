<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Google カレンダー</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="bg-green-50 text-green-700 p-4 rounded">{{ session('status') }}</div>
            @endif

            <div class="bg-white p-6 shadow sm:rounded-lg space-y-4">
                <p class="text-sm text-gray-600">
                    Google カレンダーをつなぐと，予定が入っている時間がチームの1週間で「埋まり」になります．<br>
                    読み込むのは予定が入っている時間だけです．予定のタイトルなどは読み込まないので，ほかの人には見えません．
                </p>

                @if ($calendar)
                    <p>つないでいます（最後に読み込んだ日時：{{ $calendar->syncedAtLabel() }}）</p>

                    <div class="flex gap-3">
                        <form method="POST" action="{{ route('google-calendar.sync') }}">
                            @csrf
                            <x-primary-button type="submit">今すぐ読み込む</x-primary-button>
                        </form>
                        <form method="POST" action="{{ route('google-calendar.destroy') }}">
                            @csrf
                            @method('DELETE')
                            <x-secondary-button type="submit">つながりを外す</x-secondary-button>
                        </form>
                    </div>
                @elseif ($configured)
                    <a href="{{ route('google-calendar.redirect') }}"
                       class="inline-flex items-center px-4 py-2 bg-gray-800 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                        Google カレンダーをつなぐ
                    </a>
                @else
                    <p class="text-sm text-red-600">Google との連携の設定（GOOGLE_CLIENT_ID など）がまだ入っていません．</p>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
