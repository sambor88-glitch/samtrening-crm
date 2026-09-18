<?php

namespace App\Domain\Agent;

use App\Domain\Clients\Models\Client;
use Illuminate\Support\Collection;

/**
 * How a client is written in Google Calendar — docs/AGENT-API.md §4. The dashboard matches an
 * event title against these, on whole words, so "bogucka" never catches "bogucki".
 *
 * A card may carry its own list in `calendar_aliases`; when it does not, this works one out from
 * the name. What it generates covers the ordinary case — the surname, the full name, the first
 * name, the usual Polish short form — and deliberately stops where a name stops being evidence:
 * "Ula I Gosia" for two people training together has to be written down by hand.
 *
 * The one rule worth stating: an alias that could mean two clients is not generated at all. A
 * missed event shows up as a gap somebody notices; an event charged to the wrong client is money
 * in the wrong place, and nobody notices.
 */
class CalendarAliases
{
    /**
     * Short forms Polish speakers use without thinking, for the calendar entries that say
     * "Kasia trening" and nothing else. Only the ones that are unambiguous in normal use.
     *
     * @var array<string, list<string>>
     */
    private const array SHORT_FORMS = [
        'aleksandra' => ['ola'],
        'aleksander' => ['olek'],
        'agnieszka' => ['aga'],
        'anna' => ['ania'],
        'barbara' => ['basia'],
        'bartosz' => ['bartek'],
        'elżbieta' => ['ela'],
        'jakub' => ['kuba'],
        'joanna' => ['asia'],
        'karolina' => ['karola'],
        'katarzyna' => ['kasia'],
        'krzysztof' => ['krzysiek'],
        'magdalena' => ['magda'],
        'małgorzata' => ['gosia', 'małgosia'],
        'maciej' => ['maciek'],
        'michał' => ['michałek'],
        'paweł' => ['pawcio'],
        'piotr' => ['piotrek'],
        'stanisław' => ['staszek'],
        'tomasz' => ['tomek'],
        'urszula' => ['ula'],
        'wojciech' => ['wojtek'],
        'zofia' => ['zosia'],
    ];

    /**
     * Aliases for one client, given everybody they could be confused with. Pass the whole roster
     * — the ambiguity check is the point, and it cannot be made one card at a time.
     *
     * @param  Collection<int, Client>  $roster
     * @return list<string>
     */
    public function for(Client $client, Collection $roster): array
    {
        // An explicit list on the card wins outright, empty array included: somebody decided.
        if ($client->calendar_aliases !== null) {
            return $this->clean($client->calendar_aliases);
        }

        return $this->clean($this->generate($client, $roster));
    }

    /**
     * Every client's aliases in one pass, keyed by client id — what the API endpoint needs.
     *
     * @param  Collection<int, Client>  $roster
     * @return array<int, list<string>>
     */
    public function forRoster(Collection $roster): array
    {
        return $roster
            ->mapWithKeys(fn (Client $client) => [$client->getKey() => $this->for($client, $roster)])
            ->all();
    }

    /**
     * @param  Collection<int, Client>  $roster
     * @return list<string>
     */
    private function generate(Client $client, Collection $roster): array
    {
        $parts = $this->words($client->name);

        if ($parts === []) {
            return [];
        }

        $first = $parts[0];
        $surname = count($parts) > 1 ? $parts[count($parts) - 1] : null;

        // The full name always works: nothing else in the studio reads the same.
        $aliases = [$this->normalise($client->name)];

        // The surname is the minimum the spec asks for — kept even when two clients share it,
        // because without it a card can end up with nothing to match on at all.
        if ($surname !== null) {
            $aliases[] = $surname;
        }

        // A bare first name only when it belongs to exactly one client. "Anna trening" is a real
        // calendar entry; "Anna trening" with two Annas on the books is a coin toss.
        foreach ([$first, ...$this->shortFormsOf($first)] as $candidate) {
            if (! $this->isAmbiguous($candidate, $client, $roster)) {
                $aliases[] = $candidate;
            }

            // "Kasia Bogucka" stays safe whether or not the bare "Kasia" was.
            if ($surname !== null && $candidate !== $first) {
                $aliases[] = $candidate.' '.$surname;
            }
        }

        return $aliases;
    }

    /**
     * Would this word point at anybody else? Checks first names and their short forms across the
     * roster, so "ola" is refused when the studio trains both an Aleksandra and an Ola.
     *
     * @param  Collection<int, Client>  $roster
     */
    private function isAmbiguous(string $candidate, Client $client, Collection $roster): bool
    {
        return $roster
            ->reject(fn (Client $other) => $other->getKey() === $client->getKey())
            ->contains(function (Client $other) use ($candidate) {
                $words = $this->words($other->name);

                if ($words === []) {
                    return false;
                }

                return $candidate === $words[0]
                    || in_array($candidate, $this->shortFormsOf($words[0]), true);
            });
    }

    /**
     * @return list<string>
     */
    private function shortFormsOf(string $first): array
    {
        return self::SHORT_FORMS[$first] ?? [];
    }

    /**
     * Lower case, no punctuation, single spaces — the shape the dashboard compares against.
     */
    private function normalise(string $value): string
    {
        $value = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/u', ' ', mb_strtolower($value, 'UTF-8')) ?? '');
    }

    /**
     * @return list<string>
     */
    private function words(string $name): array
    {
        $normalised = $this->normalise($name);

        return $normalised === '' ? [] : explode(' ', $normalised);
    }

    /**
     * Normalise, add an unaccented twin for anything with Polish letters — half the calendar is
     * typed without them — then drop blanks and duplicates while keeping the order.
     *
     * @param  list<string>  $aliases
     * @return list<string>
     */
    private function clean(array $aliases): array
    {
        $result = [];

        foreach ($aliases as $alias) {
            foreach ([$this->normalise((string) $alias), $this->unaccented((string) $alias)] as $candidate) {
                if ($candidate !== '' && ! in_array($candidate, $result, true)) {
                    $result[] = $candidate;
                }
            }
        }

        return $result;
    }

    /**
     * "żurek" → "zurek". Transliteration would also turn "ł" into "l", which iconv on its own
     * does not, so that pair is mapped first.
     */
    private function unaccented(string $value): string
    {
        $value = strtr($this->normalise($value), ['ł' => 'l', 'Ł' => 'L']);

        return $this->normalise((string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value));
    }
}
