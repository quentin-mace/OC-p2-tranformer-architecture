<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Tag;

class TagForm extends Component
{
    public $name = '';

    protected $rules = [
        'name' => 'required|string|max:50|unique:tags,name',
    ];

    public function save()
    {
        $this->validate();

        Tag::create(['name' => $this->name]);

        $this->reset('name');

        $this->dispatch('tagCreated');

        session()->flash('message', 'Tag added!');
    }

    public function render()
    {
        return view('livewire.tag-form');
    }
}
