<?php

namespace Database\Seeders;

use App\Domain\Messaging\Models\MessageTemplate;
use Illuminate\Database\Seeder;

class MessageTemplateSeeder extends Seeder
{
    /**
     * Starting texts from the prototype (`szablony`). Trainers edit them in the UI,
     * so an existing template is never overwritten. No payment confirmation — see SC-34.
     */
    public function run(): void
    {
        foreach (self::templates() as $key => $body) {
            MessageTemplate::query()->firstOrCreate(['key' => $key], ['body' => $body]);
        }
    }

    /**
     * @return array<string, string>
     */
    public static function templates(): array
    {
        return [
            'payment_request' => 'Cześć {imie}! Sesja {data} — {kwota}. BLIK na {blik} albo gotówka na kolejnej sesji. {trener} · SAMtrening',
            'reminder' => 'Cześć {imie}, przypominamy o nierozliczonych sesjach: {saldo}. BLIK na {blik}. Jeśli już zapłaciłeś/aś — zignoruj. SAMtrening',
            'file_ready' => '{imie}, Twój plan jest gotowy: {linkPliku} (link wygasa po 14 dniach). {trener} · SAMtrening',
            're_engagement' => 'Cześć {imie}! Dawno Cię nie było — mam wolne terminy w tym tygodniu. Napisz, dogadamy godzinę. {trener} · SAMtrening',
            'statement_subject' => 'SAMtrening — {miesiacB}: {sumaListy} do zapłaty',
            'statement_body' => <<<'TEXT'
                Cześć {imie},

                poniżej sesje z {miesiac}:

                {lista}

                Do zapłaty za {miesiacB}: {sumaListy}

                BLIK na numer {blik} albo gotówką na kolejnej sesji. Płacisz tylko za odbyte sesje — odwołanie zgłoszone z wyprzedzeniem nie jest naliczane.

                {trenerPelny}
                SAMtrening · Plac Na Groblach 23, Kraków
                TEXT,
        ];
    }
}
