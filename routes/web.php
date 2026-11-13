<?php

use App\Http\Controllers\DownloadController;
use App\Http\Controllers\LogoutController;
use App\Http\Controllers\StreamController;
use App\Livewire\Artist;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Favorites;
use App\Livewire\Genre;
use App\Livewire\Home;
use App\Livewire\Library;
use App\Livewire\NewReleases;
use App\Livewire\Profile;
use App\Livewire\Recap;
use App\Livewire\Search;
use App\Livewire\ShowPlaylist;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::middleware('guest')->group(function () {
    Route::livewire('/login', Login::class)->name('login');
    Route::livewire('/register', Register::class)->name('register');
});

Route::middleware('auth')->group(function () {
    Route::livewire('/', Home::class)->name('home');
    Route::livewire('/search', Search::class)->name('search');
    Route::livewire('/new', NewReleases::class)->name('new-releases');
    Route::livewire('/genres/{genre}', Genre::class)->name('genres.show');
    Route::livewire('/artist', Artist::class)->name('artist');
    Route::livewire('/library', Library::class)->name('library');
    Route::livewire('/favorites', Favorites::class)->name('favorites');
    Route::livewire('/playlists/{playlist}', ShowPlaylist::class)->name('playlists.show');
    Route::livewire('/recap', Recap::class)->name('recap');
    Route::livewire('/profile', Profile::class)->name('profile');
    Route::get('/download/{videoId}', DownloadController::class)->name('download');

    Route::post('/logout', LogoutController::class)->name('logout');
});

/*
 | El proxy de audio no usa sesión (el navegador lo llama decenas de veces por
 | canción), así que se protege con una URL firmada que solo se entrega a
 | quien ya ha iniciado sesión y que caduca con el propio enlace del stream.
 */
Route::get('/stream/{videoId}/{index?}', StreamController::class)
    ->whereNumber('index')
    ->middleware('signed:relative')
    ->withoutMiddleware([
        StartSession::class,
        ShareErrorsFromSession::class,
        PreventRequestForgery::class,
    ])
    ->name('stream');
