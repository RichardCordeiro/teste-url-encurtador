<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-4">
                    <div class="text-sm text-gray-500">Total de links</div>
                    <div class="text-2xl font-semibold" id="metric-total-links">0</div>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-4">
                    <div class="text-sm text-gray-500">Ativos</div>
                    <div class="text-2xl font-semibold" id="metric-active-links">0</div>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-4">
                    <div class="text-sm text-gray-500">Expirados</div>
                    <div class="text-2xl font-semibold" id="metric-expired-links">0</div>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-4">
                    <div class="text-sm text-gray-500">Cliques no período</div>
                    <div class="text-2xl font-semibold" id="metric-clicks-period">0</div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 sm:p-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <h3 class="text-lg font-semibold">Top 10 links por cliques</h3>
                        <div class="flex items-center gap-2">
                            <label for="metric-period" class="text-sm text-gray-500">Período</label>
                            <select id="metric-period" class="rounded-md border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm">
                                <option value="today">Hoje</option>
                                <option value="7d" selected>7 dias</option>
                                <option value="30d">30 dias</option>
                                <option value="all">Tudo</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead>
                                <tr class="text-gray-600 dark:text-gray-300">
                                    <th class="py-2 pr-4">Slug</th>
                                    <th class="py-2 pr-4">Original</th>
                                    <th class="py-2 pr-4 text-right">Cliques</th>
                                    <th class="py-2 pl-4 text-right">Ações</th>
                                </tr>
                            </thead>
                            <tbody id="metric-top-links">
                                <tr>
                                    <td class="py-2 pr-4 text-gray-500" colspan="4">Sem dados ainda</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 sm:p-6">
                    <h3 class="text-lg font-semibold mb-4">Links recentes</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-gray-600 dark:text-gray-300 border-b border-gray-200 dark:border-gray-700">
                                    <th class="py-2 pr-4 text-left">Slug</th>
                                    <th class="py-2 pr-4 text-left">Original</th>
                                    <th class="py-2 pr-4 text-left">Status</th>
                                    <th class="py-2 pr-4 text-left">Expira em</th>
                                    <th class="py-2 pr-4 text-right">Cliques</th>
                                    <th class="py-2 pl-4 text-right">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse(($linksDashboard ?? collect()) as $link)
                                    <tr data-link-row data-link-id="{{ $link->id }}">
                                        <td class="py-3 pr-4 align-top">
                                            <a href="{{ url('/s/'.$link->slug) }}" target="_blank" class="text-blue-600 dark:text-blue-400 underline">{{ $link->slug }}</a>
                                        </td>
                                        <td class="py-3 pr-4 align-top max-w-[28rem]">
                                            <div class="truncate" title="{{ $link->original_url }}">{{ $link->original_url }}</div>
                                        </td>
                                        <td class="py-3 pr-4 align-top">
                                            @php
                                                $statusColor = [
                                                    'active' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200',
                                                    'expired' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
                                                    'inactive' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
                                                ][$link->status] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200';
                                            @endphp
                                            <span data-link-status class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $statusColor }}">{{ $link->status }}</span>
                                        </td>
                                        <td class="py-3 pr-4 align-top">
                                            @if ($link->expires_at)
                                                <span class="js-countdown font-medium"
                                                      data-expires-at="{{ $link->expires_at->toIso8601String() }}"
                                                      data-expire-url="{{ route('links.expire', $link) }}"
                                                      title="Expira em: {{ $link->expires_at->format('d/m/Y H:i') }}">
                                                </span>
                                            @else
                                                <span class="text-gray-500">—</span>
                                            @endif
                                        </td>
                                        <td class="py-3 pr-4 align-top text-right">
                                            <span data-link-clicks>{{ $link->click_count }}</span>
                                        </td>
                                        <td class="py-3 pl-4 align-top">
                                            <div class="flex gap-2 justify-end">
                                                <button type="button" data-copy data-copy-text="{{ url('/s/'.$link->slug) }}" class="inline-flex items-center rounded-md bg-gray-100 dark:bg-gray-700 px-2 py-1 text-xs hover:bg-gray-200 dark:hover:bg-gray-600">Copiar</button>
                                                <a href="{{ url('/s/'.$link->slug) }}" target="_blank" class="inline-flex items-center rounded-md bg-blue-600 text-white px-2 py-1 text-xs hover:bg-blue-700">Abrir</a>
                                                <button type="button"
                                                        data-qrcode-toggle
                                                        data-qrcode-url="{{ route('links.qrcode', $link) }}"
                                                        class="inline-flex items-center rounded-md bg-emerald-600 text-white px-2 py-1 text-xs hover:bg-emerald-700">
                                                    QR Code
                                                </button>
                                            </div>
                                            <div class="mt-2 hidden" data-qrcode-container>
                                                <div class="rounded border border-gray-200 dark:border-gray-700 p-3 bg-gray-50 dark:bg-gray-800">
                                                    <div class="text-xs text-gray-500 mb-2">QR de {{ url('/s/'.$link->slug) }}</div>
                                                    <div data-qrcode-canvas class="w-[256px] h-[256px] flex items-center justify-center text-gray-400">Carregando...</div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="py-6 text-center text-gray-500">Nenhum link recente.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
