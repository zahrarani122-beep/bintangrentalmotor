<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            AI Motor Insight
        </x-slot>

        @if ($insight)
            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Ringkasan AI</p>
                    <p class="mt-1 text-sm text-gray-950 dark:text-white">{{ $insight->summary }}</p>
                </div>

                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Motor Rekomendasi</p>
                    <p class="mt-1 text-sm text-gray-950 dark:text-white">{{ $insight->top_motor ?? '-' }}</p>
                </div>

                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Insight Singkat</p>
                    <p class="mt-1 text-sm text-gray-950 dark:text-white">{{ $insight->recommendation }}</p>
                </div>
            </div>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Belum ada hasil analisa AI. Klik tombol Analisa AI Motor untuk membuat insight pertama.
            </p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
