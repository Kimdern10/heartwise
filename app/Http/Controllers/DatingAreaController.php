<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Public "Dating in Texas" / "Dating in Houston, TX" pages so people can find
 * Heartwise on Google. They never show member photos or names — only an
 * honest member count once there are enough members to count.
 */
class DatingAreaController extends Controller
{
    /** Below this we don't show a number (a tiny count would put people off and could identify members). */
    private const MIN_COUNT_TO_SHOW = 25;

    public static function stateSlug(string $code): string
    {
        return Str::slug(config('dating.us_states.'.$code));
    }

    public static function stateFromSlug(string $slug): ?string
    {
        foreach (config('dating.us_states') as $code => $name) {
            if (Str::slug($name) === $slug) {
                return $code;
            }
        }

        return null;
    }

    public function index(): View
    {
        return view('areas.index');
    }

    public function state(string $state): View
    {
        $code = self::stateFromSlug($state) ?? abort(404);

        return view('areas.show', [
            'code' => $code,
            'stateName' => config('dating.us_states.'.$code),
            'city' => null,
            'members' => $this->memberCount($code),
            'cities' => collect(Config::array('us_cities'))->filter(fn ($c) => $c[1] === $code),
        ]);
    }

    public function city(string $state, string $city): View
    {
        $code = self::stateFromSlug($state) ?? abort(404);
        $entry = config('us_cities.'.$city);

        abort_unless($entry && $entry[1] === $code, 404);

        return view('areas.show', [
            'code' => $code,
            'stateName' => config('dating.us_states.'.$code),
            'city' => $entry[0],
            'members' => $this->memberCount($code),
            'cities' => collect(Config::array('us_cities'))->filter(fn ($c, $slug) => $c[1] === $code && $slug !== $city),
        ]);
    }

    public function sitemap(): Response
    {
        $urls = [route('home'), route('register'), route('areas.index'), route('legal.safety'), route('legal.guidelines'), route('legal.terms'), route('legal.privacy')];

        foreach (array_keys(config('dating.us_states')) as $code) {
            $urls[] = route('areas.state', self::stateSlug((string) $code));
        }

        foreach (config('us_cities') as $slug => [, $code]) {
            $urls[] = route('areas.city', [self::stateSlug($code), $slug]);
        }

        return response()->view('areas.sitemap', ['urls' => $urls])->header('Content-Type', 'application/xml');
    }

    private function memberCount(string $code): ?int
    {
        $count = Cache::remember('area-count:'.$code, now()->addHours(6), fn () => User::query()
            ->discoverable()->where('is_sample', false)
            ->whereHas('profile', fn ($q) => $q->where('state', $code))->count());

        return $count >= self::MIN_COUNT_TO_SHOW ? $count : null;
    }
}
