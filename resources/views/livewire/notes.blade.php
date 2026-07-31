<div class="space-y-4">

    @if (session()->has('message'))
        <div class="text-green-600">{{ session('message') }}</div>
    @endif

    <form wire:submit.prevent="save" class="space-y-2">
        <textarea wire:model="text" placeholder="Write your note..." class="w-full border p-2"></textarea>

        @isset($tags)
        <select wire:model="tag_id" class="w-full border p-2">
            <option value="">-- Select Tag --</option>
            @foreach ($tags as $tag)
                <option value="{{ $tag->id }}">{{ $tag->name }}</option>
            @endforeach
        </select>
        @endisset

        <button type="submit" class="bg-blue-500 text-white px-4 py-2">Add Note</button>
    </form>

    <hr>

    <h2 class="text-xl font-bold">Your Notes</h2>

    @isset($notes)
    @foreach ($notes as $note)
        <div class="border p-3 flex justify-between items-start">
            <div>
                <p>{{ $note->text }}</p>
                <small class="text-gray-500">Tag: {{ $note->tag->name ?? '—' }}</small>
            </div>
            <button wire:click="delete({{ $note->id }})" class="text-red-500 text-sm">Delete</button>
        </div>
    @endforeach
    @endisset

</div>
