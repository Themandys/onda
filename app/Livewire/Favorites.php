<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithLibrary;
use App\Models\Favorite;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Favoritos')]
class Favorites extends Component
{
    use InteractsWithLibrary;

    #[On('library-changed')]
    public function refreshLibrary(): void {}

    public function render(): View
    {
        $tracks = Favorite::latest('id')->get()->map(fn (Favorite $favorite) => $favorite->toTrack());

        return view('livewire.favorites', [
            'tracks' => $tracks->all(),
            'covers' => $tracks->pluck('thumbnail_url')->all(),
        ]);
    }
}
