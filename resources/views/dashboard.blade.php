<x-layouts.app :title="__('Dashboard')">
    @livewireStyles

    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">

        <div class="mt-6 p-4 border border-neutral-200 dark:border-neutral-700 rounded-xl bg-white dark:bg-neutral-900">
            <livewire:notes />
        </div>

        <div class="mt-6 p-4 border border-neutral-200 dark:border-neutral-700 rounded-xl bg-white dark:bg-neutral-900">
            <livewire:tag-form />
        </div>

    </div>

    @livewireScripts
</x-layouts.app>
