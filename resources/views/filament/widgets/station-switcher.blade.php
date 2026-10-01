<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-base font-semibold">المحطة الحالية</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    جميع شاشات التشغيل والبيانات مرتبطة بهذه المحطة.
                </p>
            </div>

            <form wire:submit="switchStation" class="flex flex-1 items-end gap-3 sm:max-w-xl">
                <div class="flex-1">
                    {{ $this->form }}
                </div>

                <x-filament::button type="submit">
                    تطبيق
                </x-filament::button>
            </form>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
