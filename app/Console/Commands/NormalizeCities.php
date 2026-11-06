<?php

namespace App\Console\Commands;

use App\Models\Profile;
use App\Support\CityResolver;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('app:normalize-cities {--dry-run : Only show what would change}')]
#[Description('Give every member the official name of their city (e.g. "nyc" and "Brooklyn" become "New York") so people in the same place match')]
class NormalizeCities extends Command
{
    public function handle(): int
    {
        if (! CityResolver::enabled()) {
            $this->components->error('The city lookup is turned off (GEOCODER_ENABLED=false).');

            return self::FAILURE;
        }

        $places = Profile::query()
            ->whereNotNull('city')->where('city', '!=', '')
            ->select('state', DB::raw('MIN(city) as city'), DB::raw('COUNT(*) as members'))
            ->groupBy('state', DB::raw('LOWER(TRIM(city))'))
            ->get();

        $changed = 0;
        $unknown = [];

        foreach ($places as $i => $place) {
            // OpenStreetMap asks for no more than one lookup per second.
            if ($i > 0) {
                usleep(1_100_000);
            }

            $official = CityResolver::normalize($place->city, $place->state);

            if ($official === CityResolver::NOT_FOUND) {
                $unknown[] = "{$place->city}, {$place->state} ({$place->members})";

                continue;
            }

            if ($official === null || $official === $place->city) {
                continue;
            }

            $this->line("  {$place->city}, {$place->state} → <info>{$official}</info> ({$place->members} members)");

            if (! $this->option('dry-run')) {
                $changed += Profile::query()
                    ->where('state', $place->state)
                    ->whereRaw('LOWER(TRIM(city)) = ?', [mb_strtolower(trim($place->city))])
                    ->update(['city' => $official]);
            }
        }

        if ($unknown) {
            $this->components->warn('Not found (left as they are): '.implode('; ', $unknown));
        }

        $this->components->info($this->option('dry-run') ? 'Dry run: nothing was changed.' : "Updated {$changed} profiles.");

        return self::SUCCESS;
    }
}
