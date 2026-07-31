<div class="space-y-4">
    @if (session()->has('message'))
        <div class="text-green-600 text-sm">{{ session('message') }}</div>
    @endif

    <h2 class="text-xl font-bold">Add a tag</h2>

    <form wire:submit.prevent="save" class="space-y-2">
        <input type="text" wire:model="name" placeholder="New tag name"
               class="border rounded px-3 py-1 text-sm w-full" />
        <button type="submit" class="bg-blue-500 text-white px-4 py-2">
            Add Tag
        </button>
    </form>

    @error('name') <div class="text-red-500 text-xs">{{ $message }}</div> @enderror
</div>
