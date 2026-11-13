<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithLibrary;
use App\Models\MonthlyPlay;
use App\Models\Play;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Resumen mensual')]
class Recap extends Component
{
    use InteractsWithLibrary;

    #[Url(as: 'mes', except: '')]
    public string $month = '';

    #[On('library-changed')]
    #[On('play-recorded')]
    public function refreshRecap(): void {}

    public function selectMonth(string $yearMonth): void
    {
        if (preg_match('/^\d{4}-\d{2}$/', $yearMonth)) {
            $this->month = $yearMonth;
        }
    }

    public function render(): View
    {
        $this->syncFromPlaysIfEmpty();

        $currentMonth = now()->format('Y-m');
        $months = MonthlyPlay::query()
            ->select('year_month')
            ->distinct()
            ->orderByDesc('year_month')
            ->pluck('year_month')
            ->all();

        if (! in_array($currentMonth, $months, true)) {
            array_unshift($months, $currentMonth);
        }

        $selectedMonth = in_array($this->month, $months, true) ? $this->month : ($months[0] ?? $currentMonth);

        $entries = MonthlyPlay::query()
            ->where('year_month', $selectedMonth)
            ->whereNotIn('video_id', $this->excludedIds)
            ->orderByDesc('play_count')
            ->orderByDesc('last_played_at')
            ->limit(50)
            ->get();

        $totalPlays = (int) $entries->sum('play_count');
        $totalSeconds = (int) $entries->sum(fn (MonthlyPlay $item) => $item->play_count * max(120, $item->duration ?: 210));
        $totalMinutes = (int) round($totalSeconds / 60);

        $topArtists = $entries
            ->filter(fn (MonthlyPlay $item) => filled($item->artist))
            ->groupBy(fn (MonthlyPlay $item) => Str::lower($item->artist))
            ->map(function ($group): array {
                /** @var MonthlyPlay $first */
                $first = $group->first();

                return [
                    'name' => $first->artist,
                    'thumbnail_url' => $first->thumbnail_url,
                    'plays' => (int) $group->sum('play_count'),
                    'songs_count' => $group->count(),
                ];
            })
            ->sortByDesc('plays')
            ->take(6)
            ->values()
            ->all();

        $topTracks = $entries->map(fn (MonthlyPlay $item) => [
            ...$item->toTrack(),
            'play_count' => $item->play_count,
        ])->all();

        return view('livewire.recap', [
            'selectedMonth' => $selectedMonth,
            'selectedMonthLabel' => $this->formatMonthLabel($selectedMonth),
            'availableMonths' => array_map(fn (string $ym) => [
                'value' => $ym,
                'label' => $this->formatMonthLabel($ym),
            ], $months),
            'topTracks' => $topTracks,
            'topArtists' => $topArtists,
            'stats' => [
                'total_plays' => $totalPlays,
                'total_minutes' => $totalMinutes,
                'unique_tracks' => count($topTracks),
                'unique_artists' => $entries->pluck('artist')->filter()->map(fn (string $a) => Str::lower($a))->unique()->count(),
                'top_track' => $topTracks[0] ?? null,
            ],
        ]);
    }

    private function syncFromPlaysIfEmpty(): void
    {
        if (MonthlyPlay::count() > 0 || Play::count() === 0) {
            return;
        }

        foreach (Play::all() as $play) {
            $yearMonth = ($play->last_played_at ?? now())->format('Y-m');
            MonthlyPlay::updateOrCreate(
                ['year_month' => $yearMonth, 'video_id' => $play->video_id],
                [
                    'title' => $play->title,
                    'artist' => $play->artist,
                    'thumbnail_url' => $play->thumbnail_url,
                    'duration' => 210,
                    'play_count' => max(1, (int) $play->play_count),
                    'last_played_at' => $play->last_played_at ?? now(),
                ],
            );
        }
    }

    private function formatMonthLabel(string $yearMonth): string
    {
        try {
            return Str::ucfirst(Carbon::createFromFormat('Y-m', $yearMonth)->locale('es')->isoFormat('MMMM YYYY'));
        } catch (\Throwable) {
            return $yearMonth;
        }
    }
}
