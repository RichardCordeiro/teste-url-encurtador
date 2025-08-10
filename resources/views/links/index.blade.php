<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Encurtador de URL') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="bg-green-50 dark:bg-green-900 text-green-800 dark:text-green-100 px-4 py-2 rounded">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <form method="POST" action="{{ route('links.store') }}" class="space-y-4">
                        @csrf
                        <div>
                            <x-input-label for="original_url" value="URL Original" />
                            <x-text-input id="original_url" name="original_url" type="url" class="mt-1 block w-full" required placeholder="https://exemplo.com/minha-pagina" />
                            <x-input-error :messages="$errors->get('original_url')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="expires_in_minutes" value="Expira em (minutos, opcional)" />
                            <x-text-input id="expires_in_minutes" name="expires_in_minutes" type="number" min="1" step="1" class="mt-1 block w-full" placeholder="Ex.: 60" />
                            <x-input-error :messages="$errors->get('expires_in_minutes')" class="mt-2" />
                        </div>
                        <x-primary-button>Encurtar</x-primary-button>
                    </form>
                </div>
            </div>
            @if (!empty($newLink))
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100 space-y-4">
                        <h3 class="font-semibold">Link gerado</h3>

                        <div class="space-y-2">
                            <div class="text-sm text-gray-500">URL curta</div>
                            <div class="flex gap-2 items-stretch">
                                <input type="text" readonly class="w-full rounded-md border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm px-3 py-2" value="{{ url('/s/'.$newLink->slug) }}">
                                <button type="button" data-copy data-copy-text="{{ url('/s/'.$newLink->slug) }}" class="inline-flex items-center rounded-md bg-blue-600 text-white px-3 py-2 text-sm hover:bg-blue-700 focus:outline-none" aria-label="Copiar URL encurtada">Copiar</button>
                            </div>
                            <a href="{{ url('/s/'.$newLink->slug) }}" target="_blank" class="text-blue-600 dark:text-blue-400 underline break-all">Abrir link curto</a>
                        </div>

                        <div>
                            <div class="text-sm text-gray-500">QR Code</div>
                            @if (class_exists(\SimpleSoftwareIO\QrCode\Facades\QrCode::class))
                                {!! SimpleSoftwareIO\QrCode\Facades\QrCode::size(256)->generate(url('/s/'.$newLink->slug)) !!}
                            @else
                                <span class="text-gray-500">Habilite QR: ext-gd + composer require simplesoftwareio/simple-qrcode</span>
                            @endif
                        </div>

                        <div class="text-sm text-gray-500">Original</div>
                        <div class="break-all">
                            <a href="{{ $newLink->original_url }}" target="_blank" class="text-blue-600 dark:text-blue-400 underline">{{ $newLink->original_url }}</a>
                        </div>

                        <div class="flex items-center gap-2 text-sm">
                            <span>Status:</span>
                            <span data-link-status>{{ $newLink->status }}</span>
                            @if ($newLink->expires_at)
                                <span class="ml-4">
                                    Expira em:
                                    <span class="js-countdown font-medium"
                                          data-expires-at="{{ $newLink->expires_at->toIso8601String() }}"
                                          data-expire-url="{{ route('links.expire', $newLink) }}"
                                          title="Expira em: {{ $newLink->expires_at->format('d/m/Y H:i') }}"></span>
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            
        </div>
    </div>
</x-app-layout>


