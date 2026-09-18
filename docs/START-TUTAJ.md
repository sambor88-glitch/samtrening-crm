# SAMtrening CRM — start developmentu

Ten plik jest punktem wejścia. Czytaj w kolejności: §1–§4 zanim napiszesz pierwszą linię, §5–§9 przy implementacji, §13 przed sięgnięciem do `SPEC-EKRANY.md`.

- `START-TUTAJ.md` (ten plik) — stack, zakres, architektura, baza, kolejność budowy, definicja gotowości.
- `SPEC-EKRANY.md` — szczegółowa specyfikacja każdego ekranu: teksty, odstępy, stany, walidacje. Źródło prawdy dla wyglądu i treści. **Powstawał w trakcie projektowania — §13 wymienia miejsca, które są nieaktualne.**
- `SAMtrening CRM.dc.html` — działający prototyp. Otwiera się w przeglądarce, ma dane demo i skróty logowania. Referencja, nie kod do kopiowania.
- `styles.css` — klasy systemu wizualnego z prototypu.

**Konwencja nazewnicza:** identyfikatory w kodzie — tabele, kolumny, modele, klasy, metody, zmienne, pliki — **po angielsku**. Polski zostaje wyłącznie w treści widzianej przez użytkownika (teksty UI, szablony wiadomości, komunikaty walidacji) i w tej dokumentacji. Model danych w `SPEC-EKRANY.md` używa polskich nazw pól z okresu projektowania — **kanoniczne nazwy są w §5 tego pliku**.

---

## 1. Co budujemy

CRM dla studia treningu personalnego SAMtrening (Plac Na Groblach 23, Kraków). **Trzech użytkowników**, kilkuset klientów docelowo. Nie jest to produkt dla wielu studiów — nie buduj multi-tenancy.

Cztery zdania, z których wynika cała architektura:

1. **Brak pakietów i abonamentów.** Należność powstaje w momencie, gdy trener wbije odbytą sesję. Nigdy wcześniej. Klient może jednak zapłacić z góry — wpłata to pula pieniędzy na karcie, z której schodzą kolejne należności (decyzja właściciela z 15.09.2026, §6 „Przedpłata").
2. **Brak kalendarza.** Grafik zostaje w Google Calendar. CRM rejestruje fakty po treningu. To decyzja właściciela — nie dodawaj rezerwacji.
3. **100% stawki idzie do trenera.** Studio nie pobiera prowizji. Nie ma rozliczeń studio–trener.
4. **Klient nie ma konta.** Nie loguje się nigdzie. Dostaje SMS-y, e-maile i linki do plików.

Użytkownicy: Maciej Samborski (właściciel — jest jednocześnie trenerem i przełącza się między panelem trenera i admina), Katarzyna, Bartosz (trenerzy).

---

## 2. Stack

Ustalony z właścicielem (wersje podniesione 11.09.2026, SC-8 — Laravel 11 nie dostaje poprawek bezpieczeństwa od 12.03.2026):

| Warstwa | Wybór | Dlaczego |
| --- | --- | --- |
| Backend | **Laravel 13** | Policies dla „trener widzi tylko swoich", queues dla SMS-ów, scheduler dla monitów, gotowe resety hasła |
| Frontend | **Blade + Livewire 4** | Przy trzech użytkownikach osobne API + SPA to warstwa bez zysku. Filtry, dialogi, edycja kwoty w miejscu i przełącznik zakresu działają bez pisania API |
| CSS | **Tailwind 4** z tokenami z §12 | W prototypie styl siedzi przy elementach, żeby dało się go szybko przestawiać. W aplikacji tokeny idą do bloku `@theme` w `resources/css/app.css` |
| Baza | **MySQL 8** | Decyzja z 11.09.2026 (SC-9). Lokalnie w Dockerze (`compose.yaml`), na produkcji na serwerze z Forge |
| Hosting | **Laravel Forge** | Worker kolejki, scheduler, SSL i kopie bazy z jednego panelu — ten sam hosting co ADV Factory |
| Auth | **Laravel Breeze** (Blade) | Sanctum niepotrzebny — panel nie ma API ani aplikacji mobilnej. Read-only `/api/agent/v1` (`AGENT-API.md`) chodzi na własnym tokenie Bearer, nie na Sanctumie, i nie dotyka panelu |
| Kolejki | **database driver** | Przy tym wolumenie Redis to przesada |

**Bez SPA, bez React, bez Inertii.**

Strefa czasowa: **`Europe/Warsaw`** w `config/app.php`. Daty i znaczniki czasu zapisuje Laravel w tej strefie; „dziś", tydzień i miesiąc liczy PHP i przekazuje do zapytań jako parametry — **bez `NOW()` i `CURDATE()` w SQL**, bo połączenie z bazą pracuje w strefie serwera (UTC). Tygodnie **ISO** (poniedziałek pierwszy). Od tego zależą: domykacz tygodnia, próg 21 dni ciszy i monity. Locale: **`pl`** — odmiana liczebników ma trzy formy (§7).

---

## 3. Zakres

### Budujemy

16 ekranów z `SPEC-EKRANY.md` §Screens: 3 ekrany dostępu, 7 zakładek panelu trenera (Pulpit, Klienci, Sesje, Płatności, Zarobki, Wiadomości, Ustawienia) + karta klienta, 5 zakładek panelu admina (Pulpit studia, Trenerzy, Kartoteka studia, Zaległości, Log zmian). Plus 5 okien dialogowych.

### NIE budujemy — decyzje podjęte, nie pominięcia

| Czego nie ma | Dlaczego |
| --- | --- |
| **Kalendarza i rezerwacji** | Grafik zostaje w Google Calendar |
| **Bramki płatniczej** | BLIK idzie ręcznie z telefonu trenera na telefon klienta. Bez Stripe'a, bez Przelewy24, bez subkont, bez webhooków |
| **Automatycznego odznaczania wpłat** | Wpłata idzie na prywatne konto trenera — studio jej nie widzi. Status zmienia człowiek |
| **Importu danych** | Kartoteka startuje pusta, klienci wpisywani ręcznie |
| **Konta dla klienta** | Klient nigdy się nie loguje |
| **Automatycznej wysyłki podsumowań** | Trener klika „Wyślij N podsumowań" sam. Kwota bierze się z ręcznie wbitych sesji — automat wysłałby zaniżoną sumę, gdyby ktoś zapomniał wbić dwie sesje z końca miesiąca |
| **Trybu offline / PWA** | W biurze jest WiFi |
| **Pakietów, karnetów, prowizji** | Model biznesowy ich nie ma. Wpłata z góry (§6 „Przedpłata") nie jest karnetem: nie ma liczby wejść ani terminu ważności, jest kwota, z której schodzą wbite sesje |
| **Multi-tenancy** | Jedno studio |
| **Usuwania kont trenerów** | Odejście z zespołu domyka blokada, nie kasowanie. Kartoteki wiszą na trenerze, a zarobki liczą się przez klienta — skasowanie przepisałoby cudze przychody albo osierociło kartoteki. `clients.trainer_id` ma klucz obcy bez kaskady, więc baza i tak by na to nie pozwoliła. Jeśli odchodzący trener zażąda usunięcia danych, idziemy w anonimizację konta wzorem `AnonymizeClient`, a nie w DELETE |
| **Hasła nadawanego trenerowi przez właściciela** | Dostęp daje „Link z ręki” (SC-56): trener sam ustawia hasło i nikt poza nim go nie zna, więc log zmian wskazuje jedną osobę — przy sporze o kwotę albo pytaniu, kto zaglądał w dane o zdrowiu, to jedyny dowód. Wariant z hasłem wpisywanym przez właściciela i wymuszoną zmianą przy pierwszym logowaniu (roboczo SC-58) odrzucony 14.09.2026: rozwiązywał to samo co link, czyli dostęp bez czekania na maila |

### Otwarte — do domknięcia przed wdrożeniem, nie przed startem kodowania

- ~~Dostawca SMS (SC-16)~~ — **SMSAPI, prepaid**. Wybrany za model rozliczeń: przy kilkudziesięciu monitach miesięcznie abonament SerwerSMS byłby kosztem stałym niezależnym od tego, czy ktoś zalega. Zostaje zgłoszenie nazwy nadawcy u operatora i umowa powierzenia.
- Poczta transakcyjna: Google Workspace, nadawca `noreply@samtrening.com`, Reply-To = e-mail trenera (§9). Do zrobienia **SPF/DKIM/DMARC na `samtrening.com`** — bez tego wszystko idzie w spam (SC-17).
- Treść zgody RODO na dane o zdrowiu (art. 9) — CRM zapisuje tylko fakt i datę, treść odbierana jest poza systemem.
- Umowa powierzenia przetwarzania z trenerami, jeśli pracują na własnych działalnościach.
- Numeracja rachunków — tylko jeśli dojdą faktury dla firm (§13).

---

## 4. Architektura modułowa

Logika nie mieszka w komponentach Livewire. Sześć modułów domenowych, każdy z własnymi akcjami i zapytaniami; Livewire tylko orkiestruje.

```
app/
├── Domain/
│   ├── Clients/
│   │   ├── Models/{Client, ClientTag, ClientFile}.php
│   │   ├── Actions/{CreateClient, UpdateClient, ArchiveClient, SetClientRate}.php
│   │   └── Queries/{ClientRoster, DormantClients}.php        // filtry, cisza 21+ dni
│   ├── Training/
│   │   ├── Models/TrainingSession.php
│   │   ├── Actions/{LogSession, UpdateSessionPrice, DeleteSession, RestoreSession}.php
│   │   └── Queries/{SessionHistory, WeekGrid}.php            // domykacz tygodnia
│   ├── Billing/
│   │   ├── Balance.php                                       // JEDYNE miejsce liczące saldo
│   │   ├── Earnings.php                                      // zarobek i liczba odbytych w zakresie
│   │   ├── PrepaymentPool.php                                // JEDYNE miejsce decydujące, co opłaciła wpłata z góry
│   │   ├── Models/Prepayment.php
│   │   ├── Actions/{MarkAsPaid, RequestBlikPayment, RecordPrepayment, DeletePrepayment, RestorePrepayment}.php
│   │   └── Export/SessionCsvExport.php
│   ├── Messaging/
│   │   ├── TemplateRenderer.php                              // podstawianie {imie}, {blik}, …
│   │   ├── SmsSegmentCounter.php                             // UCS-2, 70/67 znaków
│   │   ├── Actions/{SendPaymentRequest, SendReminder, SendReEngagement, SendMonthlyStatement}.php
│   │   └── Providers/{SmsProvider.php (interface), SmsApiProvider, LogSmsProvider}.php
│   ├── Team/
│   │   ├── Models/User.php
│   │   ├── Enums/UserStatus.php                              // active | invited | blocked
│   │   ├── Queries/OwnerContact.php                          // kontakt na ekranach dostępu
│   │   ├── Notifications/ResetPasswordNotification.php       // mail z linkiem, po polsku
│   │   └── Actions/{InviteTrainer, ActivateAccount, BlockTrainer, ResetTrainerPassword}.php
│   ├── Privacy/
│   │   └── Actions/{ExportClientData, AnonymizeClient, SweepRetention}.php
│   └── Audit/
│       ├── Models/ActivityEntry.php
│       └── ActivityLogger.php                                // wołane z akcji, nie z komponentów
├── Livewire/
│   ├── Trainer/{Dashboard, ClientList, ClientCard, SessionList, Payments, Earnings, Messages, Settings}.php
│   ├── Admin/{StudioDashboard, TeamList, StudioRoster, Outstanding, ActivityLog}.php
│   └── Dialogs/{LogSessionDialog, ClientDialog, InviteTrainerDialog, DeleteDataDialog}.php
├── Support/
│   ├── Money.php               // cast grosze ⇄ wyświetlanie
│   ├── Plural.php              // polska odmiana liczebników (§7)
│   └── DateRange.php           // miesiąc „2026-09" albo rok „2026"
└── Policies/{ClientPolicy, TrainingSessionPolicy, UserPolicy}.php

resources/views/components/     // prymitywy UI — jedno miejsce na wzorzec
├── btn.blade.php, tag.blade.php, input.blade.php, seg.blade.php
├── stat-bar.blade.php          // pasek statystyk, powtarzany na 6 ekranach
├── data-table.blade.php        // powłoka tabeli + karty mobilne (data-label, §12)
├── empty-state.blade.php       // różne treści dla różnych przyczyn pustki
├── skeleton-rows.blade.php     // stan wczytywania
└── toast.blade.php             // wariant sukcesu, błędu i akcji „Cofnij"
```

**Zasady, które trzymają to w kupie:**

1. **Jedna akcja = jedna klasa z jedną metodą publiczną `handle()`.** Akcja jest testowalna bez Livewire'a i bez HTTP.
2. **Saldo i zarobek liczy wyłącznie `Billing\Balance` i `Billing\Earnings`.** Jeśli druga klasa zaczyna sumować `price`, to jest błąd — patrz §6. To, które sesje opłaciła wpłata z góry, zapisuje wyłącznie `Billing\PrepaymentPool`; `Balance` tylko to odczytuje.
3. **Log zmian woła `ActivityLogger` z wnętrza akcji**, nigdy z komponentu. Inaczej akcja wywołana z konsoli albo z kolejki nie zostawia śladu.
4. **Dostawca SMS za interfejsem.** `LogSmsProvider` w środowisku lokalnym — nikt nie wysyła prawdziwych SMS-ów podczas developmentu.
5. **Komponent Livewire nie zawiera zapytań.** Woła obiekt z `Queries/` i dostaje gotową kolekcję.
6. **Zero matematyki pieniędzy w Blade.** `Money` robi formatowanie, nic więcej.
7. **Każdy powtarzalny element UI ma jeden komponent Blade.** Pasek statystyk występuje na sześciu ekranach — sześć kopii rozjedzie się w trzy tygodnie.

---

## 5. Baza danych

Kwoty **w groszach, jako `integer`**. Nigdy `float`, nigdy `decimal` w PHP. Stawki mają krok 5 zł, czyli 500 groszy.

**Uwaga na nazwę tabeli:** sesje treningowe to `training_sessions` — `sessions` jest zajęte przez sterownik sesji Laravela.

```php
// trenerzy — rozszerzona tabela Breeze'a
Schema::table('users', function (Blueprint $t) {
    $t->string('specialty')->nullable();                          // tekst wolny
    $t->string('blik_number', 20)->nullable();                    // numer BLIK per trener
    $t->enum('status', ['active', 'invited', 'blocked'])->default('invited');
    $t->boolean('is_owner')->default(false);                      // dokładnie jeden rekord true
});
// users.password musi być nullable — konto zaproszone nie ma jeszcze hasła

Schema::create('clients', function (Blueprint $t) {
    $t->id();
    $t->foreignId('trainer_id')->constrained('users');
    $t->string('name');
    $t->string('phone', 20)->nullable();
    $t->string('email')->nullable();
    $t->unsignedInteger('rate');                    // grosze, krok 500
    $t->text('goal')->nullable();                   // cel i kontekst
    $t->text('baseline')->nullable();               // punkt startowy
    $t->text('contraindications')->nullable();      // DANE ZDROWOTNE — szyfruj, §11
    $t->text('trainer_notes')->nullable();          // prywatna notatka trenera
    $t->text('next_session_plan')->nullable();      // „na następny raz"
    $t->string('guardian')->nullable();             // wymagany dla osób < 18 lat
    $t->boolean('consent_given')->default(false);   // zgoda RODO
    $t->date('consent_date')->nullable();
    $t->string('company_name')->nullable();         // do faktury, §13
    $t->string('tax_id', 15)->nullable();
    $t->boolean('archived')->default(false);
    $t->timestamp('last_reminder_at')->nullable();   // monit najwyżej raz na 7 dni (§10)
    $t->timestamps();
    $t->index(['trainer_id', 'archived']);
});

Schema::create('client_tags', function (Blueprint $t) {          // charakterystyka klienta
    $t->id();
    $t->foreignId('client_id')->constrained()->cascadeOnDelete();
    $t->string('label');
    $t->enum('variant', ['accent', 'accent-2', 'neutral', 'outline'])->default('neutral');
});

Schema::create('training_sessions', function (Blueprint $t) {
    $t->id();
    $t->foreignId('client_id')->constrained();
    $t->date('date');
    $t->string('service');
    $t->unsignedInteger('price');                   // grosze; nadpisywalna niezależnie od rate
    $t->unsignedInteger('prepaid_amount')->default(0); // grosze opłacone z wpłaty z góry — pisze tylko PrepaymentPool
    $t->enum('kind', ['completed', 'cancelled', 'no_show'])->default('completed');
    $t->enum('payment_status', ['paid', 'balance', 'requested', 'waived', 'prepaid'])->default('balance');
    $t->text('notes')->nullable();                  // DANE ZDROWOTNE
    $t->softDeletes();                              // usunięcie sesji NIE jest DELETE
    $t->timestamps();
    $t->index(['client_id', 'date']);
    $t->index('date');
});

Schema::create('prepayments', function (Blueprint $t) {          // wpłaty z góry — §6 „Przedpłata"
    $t->id();
    $t->foreignId('client_id')->constrained();
    $t->unsignedInteger('amount');                  // grosze
    $t->date('paid_on');
    $t->softDeletes();                              // pomyłkę cofa się jak sesję — „Cofnij"
    $t->timestamps();
    $t->index(['client_id', 'paid_on']);
});

Schema::create('client_files', function (Blueprint $t) {
    $t->id();
    $t->foreignId('client_id')->constrained();
    $t->string('name');
    $t->string('extension', 8);
    $t->string('path');                             // prywatny disk, nigdy public
    $t->unsignedBigInteger('size');
    $t->timestamps();
});

Schema::create('activity_entries', function (Blueprint $t) {     // log zmian — append only
    $t->id();
    $t->foreignId('user_id')->nullable()->constrained();
    $t->string('actor_name');                       // denormalizowane — zostaje po usunięciu konta
    $t->string('action');
    $t->string('context')->nullable();              // klient, kwota, wartość przed/po
    $t->timestamp('happened_at');
    $t->index('happened_at');
});

Schema::create('settings', function (Blueprint $t) {             // singleton, jeden wiersz
    $t->id();
    $t->string('sms_provider')->nullable();
    $t->boolean('reminders_enabled')->default(true);
    $t->unsignedSmallInteger('reminder_threshold_days')->default(14);
    $t->unsignedSmallInteger('free_cancellation_hours')->default(24);
    $t->unsignedSmallInteger('retention_months')->default(60);
    $t->boolean('ticker_enabled')->default(true);
    $t->timestamps();
});

Schema::create('message_templates', function (Blueprint $t) {    // edytowalne w UI
    $t->id();
    $t->string('key')->unique();
    // payment_request | reminder | payment_confirmation | file_ready
    // re_engagement | statement_subject | statement_body
    $t->text('body');
    $t->timestamps();
});
```

**Bez tabeli `balances`.** Saldo jest liczone z sesji za każdym razem — §6. Kolumna z saldem rozjedzie się z rzeczywistością pierwszego dnia. Tabela `prepayments` nie jest saldem, tylko zapisem wpłaty — co z niej zeszło, wynika z sesji (`prepaid_amount`, §6 „Przedpłata").

Mapowanie na polskie nazwy z `SPEC-EKRANY.md`: `klient→client`, `trener→trainer`, `stawka→rate`, `cena→price`, `typ→kind`, `status→payment_status`, `kontuzje→contraindications`, `notatki→trainer_notes`, `plan→next_session_plan`, `opiekun→guardian`, `archiwalny→archived`, `dziennik→activity_entries`, `ustawienia→settings`, `szablony→message_templates`, `wpłata z góry→prepayment`.

---

## 6. Reguły wyliczeniowe

Serce systemu. Zaimplementuj dokładnie i pokryj testami — to jedyna część, w której błąd oznacza straconą gotówkę.

`kind` i `payment_status` to enumy z `Domain\Training\Enums` — literówka w stringu nie ma jak przejść.

```php
// Domain\Training\Models\TrainingSession
public function isPayable(): bool
{
    return $this->payment_status?->isPayable() ?? false;   // czyli ani paid, ani prepaid, ani waived
}

public function isCompleted(): bool
{
    return $this->kind === SessionKind::Completed;
}

// Domain\Billing\Balance — jedyne miejsce liczące saldo
public function forClient(Client $client): int          // grosze
{
    return (int) $client->sessions()
        ->whereNotIn('payment_status', PaymentStatus::SETTLED)
        ->sum(DB::raw('price - prepaid_amount'));        // część opłacona z przedpłaty nie jest długiem
}
```

**Kolumna `date` trzyma dzień, nie moment.** Mutator w `TrainingSession` zapisuje `Y-m-d`, bo Eloquent domyślnie wstawia `2026-09-30 00:00:00`: MySQL to przycina do DATE, SQLite zostawia — i ten sam zakres gubi ostatni dzień, ale tylko w testach.

**Zarobek trenera** — odwołania *naliczone* wchodzą do zarobku, ale **nie liczą się jako sesje**:

```php
// Domain\Billing\Earnings
$sessions = TrainingSession::whereHas('client', fn ($q) => $q->where('trainer_id', $trainerId))
    ->whereBetween('date', [$range->firstDay(), $range->lastDay()])     // dni, nie momenty
    ->get();

$revenue        = $sessions->sum('price');                             // z naliczonymi odwołaniami
$completedCount = $sessions->where('kind', SessionKind::Completed)->count();   // tylko odbyte
```

**Przedpłata** (`Billing\PrepaymentPool`, decyzja właściciela z 15.09.2026). Klient może zapłacić z góry — gotówką, przelewem albo BLIK-iem, odznaczane ręcznie jak każda wpłata (`Billing\Actions\RecordPrepayment`). Wpłata trafia do puli klienta, a pula opłaca sesje:

- Z puli schodzą wyłącznie sesje jeszcze należne (`balance`, `requested`), **od najstarszej** (data, potem id) — także wbite przed wpłatą, więc wpłata najpierw spłaca zaległe saldo. Zapłacone na miejscu i odwołania bez naliczenia puli nie ruszają; odwołanie naliczone i nieobecność schodzą z niej jak sesja.
- Sesja opłacona w całości dostaje `payment_status = prepaid` i `prepaid_amount = price`. Sesja, na której pula się kończy, zostaje należna z `prepaid_amount` równym opłaconej części — saldo liczy z niej tylko resztę. Każda późniejsza jest **poza przedpłatą** i należna w całości.
- **Nic nie jest liczone przyrostowo.** Każda akcja, która zmienia wpłatę, kwotę sesji albo to, czy sesja istnieje — `LogSession`, `UpdateSessionPrice`, `DeleteSession`, `RestoreSession`, `MarkAsPaid`, `RecordPrepayment`, `DeletePrepayment`, `RestorePrepayment` — woła na końcu `PrepaymentPool::allocate()`, a ta liczy klienta od zera. Nowa akcja tego rodzaju musi robić to samo.
- „Zapłacone" przy sesji opłaconej z puli w części: klient dopłaca resztę, a część z puli zostaje wydana (`paid` z `prepaid_amount > 0`) — inaczej te same pieniądze wróciłyby do puli drugi raz. Pula nigdy nie wydaje więcej, niż wpłynęło.
- `Balance::prepayment()` zwraca: wpłacone, zeszło na sesje, zostało. Zarobek się nie zmienia — należność i przychód nadal powstają przy wbiciu sesji — ale „Już na koncie" i „Opłacone w miesiącu" liczą sesje z przedpłaty jako opłacone. Monit i podsumowanie miesiąca pytają tylko o to, czego pula nie pokryła.
- Świadomie bez zwrotu niewykorzystanej kwoty (pomyłkę cofa się usunięciem wpłaty — soft delete z „Cofnij") i bez blokady archiwizacji, gdy w puli zostały pieniądze: pula zostaje na karcie.

**Klienci archiwalni**: wypadają ze statystyk, list i zaległości, ale **ich przeszłe sesje nadal liczą się do zarobków**. Nie filtruj ich z agregacji finansowych.

**Po terminie**: `days(najstarsza payable sesja) > settings.reminder_threshold_days`.

**Cisza w kalendarzu** (`Clients\Queries\DormantClients`): klienci aktywni, dla których `days(lastSessionDate) >= 21`, sortowani malejąco. Próg 21 jest stały — nie wystawiaj go w Ustawieniach, dopóki ktoś o to nie poprosi.

**Domykacz tygodnia** (`Training\Queries\WeekGrid`): siatka `Pn–Nd` bieżącego tygodnia ISO × aktywni klienci trenera, **z wyłączeniem tych z `DormantClients`** (inaczej się dublują). Pole wypełnione = sesja odbyta, `×` = odwołanie lub nieobecność, puste = brak wpisu. Wiersz bez ani jednego wpisu dostaje przycisk „Wbij sesję" z preselekcją klienta i dzisiejszej daty. *W prototypie tydzień jest zahardkodowany na `2026-09-07…13` — licz go z `Carbon::now()->startOfWeek()`.*

**Zakres miesiąc / rok** (`Support\DateRange`): prefiks daty — `2026-09` albo `2026`. Wspólny dla Zarobków trenera oraz Pulpitu studia i Trenerów w panelu admina. **Salda i zaległości są narastające i nie podlegają temu filtrowi** — dług nie należy do miesiąca.

---

## 7. Uprawnienia

```php
// ClientPolicy
public function view(User $user, Client $client): bool
{
    return $user->is_owner || $client->trainer_id === $user->id;
}
```

Scoping wymuszaj **w zapytaniu, nie w widoku**. Listy w panelu trenera zawęża `Client::query()->forTrainer($user)` — nigdy `@if` w Blade. Policy pilnuje pojedynczych rekordów, więc podmiana id w adresie kończy się na 403, a nieistniejący rekord na 404.

Policy wiąże się z modelem atrybutem `#[UsePolicy]`, bo modele nie mieszkają w `App\Models` i zgadywanie nazw byłoby loterią. `before()` w każdej policy odcina konta, które nie są `active`: zaproszony nie zobaczy danych przed aktywacją, a zablokowany traci dostęp także w sesji otwartej przed blokadą. Zasady studia chroni brama `manage-studio-rules`.

| | Trener | Właściciel |
| --- | --- | --- |
| Panel trenera, tylko swoi klienci | tak | tak |
| Przełącznik Trener/Admin | **nie widzi go wcale** | tak |
| Panel admina, kartoteka całego studia | nie | tak, z notatkami i danymi zdrowotnymi |
| Zakładanie, blokowanie kont, reset hasła innego trenera | nie | tak |
| Log zmian | nie | tak |
| Własny numer BLIK w Ustawieniach | tak | tak |
| Zasady studia w Ustawieniach (monit, progi, retencja, ticker) | tylko podgląd | tak |
| Możliwość zablokowania | tak | **nie** — konto właściciela jest nieblokowalne |

Właściciel w widoku trenera jest funkcjonalnie nieodróżnialny od pozostałych. Panel wynika z adresu: trasy `admin.*` (prefiks `/admin`) to panel admina, chroniony middlewarem `owner` — trener dostaje 403; reszta to panel trenera. Przełącznik roli to dwa linki do pulpitów, więc w sesji nie ma stanu, który dałoby się podmienić.

**Log zmian jest obowiązkowy** przy: wbiciu sesji, edycji kwoty, usunięciu i cofnięciu usunięcia sesji, zmianie stawki, dodaniu i edycji klienta, wysłaniu prośby o płatność i monitu, odznaczeniu gotówki, wpłacie z góry, usunięciu i cofnięciu usunięcia wpłaty z góry, archiwizacji, usunięciu danych RODO, zaproszeniu i blokadzie trenera, zmianie ustawień, resecie hasła, aktywacji konta, eksporcie CSV. Przy ręcznie ustalanych stawkach i nadpisywalnych kwotach bez logu nie da się rozstrzygnąć sporu „ja tego nie zmieniałem".

**Odmiana liczebników** (`Support\Plural`) — potrzebna w kilkunastu miejscach. Jeden helper, używany wszędzie:

```php
public static function of(int $n, string $one, string $few, string $many): string
{
    $last = $n % 10;
    $lastTwo = $n % 100;
    if ($n === 1) return "1 $one";
    if ($last >= 2 && $last <= 4 && ($lastTwo < 10 || $lastTwo >= 20)) return "$n $few";
    return "$n $many";
}
// of(1,'sesja','sesje','sesji') → „1 sesja"; of(3,…) → „3 sesje"; of(5,…) → „5 sesji"
```

---

## 8. Kolejność budowy

Każdy etap zostawia coś, co da się pokazać właścicielowi.

**Etap 1 — fundament (2–3 dni).** Laravel, Breeze, migracje z §5, seeder z jednym właścicielem. Tokeny w Tailwindzie (§12) i prymitywy Blade z §4. Layout: limonkowy pasek górny, nawigacja, ticker, kontener treści, toast. Logowanie z walidacją z `SPEC-EKRANY.md` §Ekrany dostępu — cztery różne komunikaty, każdy prowadzi do innego działania.

**Etap 2 — rdzeń wartości (3–4 dni).** Moduł `Clients`: lista z filtrami, dialog dodawania i edycji, karta klienta. Moduł `Training`: dialog „Wbij sesję" z ostrzeżeniem o duplikacie, lista, edycja kwoty w miejscu, usuwanie z „Cofnij". `Billing\Balance` + testy z §6. **Po tym etapie system już zarabia.**

**Etap 3 — pieniądze (2 dni).** Płatności: zaległości, odznaczanie gotówki i BLIK-a, panel zbiorczy. Zarobki z przełącznikiem zakresu. `SessionCsvExport` (§10).

**Etap 4 — komunikacja (2–3 dni).** `Messaging`: szablony z podstawianiem danych, licznik segmentów, dostawcy za interfejsem, kolejka. Walidacja braku `blik_number` przed każdą wysyłką.

**Etap 5 — panel admina (2 dni).** Pulpit studia, Trenerzy z zaproszeniami i blokadami, Kartoteka studia, Zaległości, Log zmian. Ekran ustawiania hasła w dwóch trybach (zaproszenie / reset).

**Etap 6 — domknięcie (2 dni).** Pulpit trenera: `WeekGrid`, `DormantClients`, sekcja „Na następny raz". Ustawienia. Moduł `Privacy`: eksport danych klienta, archiwizacja, usunięcie na żądanie. Stany wczytywania i błędów (§11). Widok mobilny tabel.

Etapy 1–2 są ścieżką krytyczną. Resztę można przestawiać.

---

## 9. Wiadomości

Cztery szablony SMS i jeden e-mail. Aktualne treści są w prototypie (obiekt `szablony` w `prototype/SAMtrening CRM.dc.html`) — **skopiuj je dokładnie**, są przemyślane pod długość i ton; §10 w `SPEC-EKRANY.md` ma jeszcze wersje z linkiem zamiast numeru BLIK. Klucze: `payment_request`, `reminder`, `file_ready`, `re_engagement`, `statement_subject`, `statement_body`. SMS-a z potwierdzeniem wpłaty nie wysyłamy (decyzja z 11.09.2026, SC-34).

Pola podstawiane zostają **po polsku** — trener je widzi i edytuje w UI: `{imie}`, `{trener}`, `{trenerPelny}`, `{data}`, `{kwota}`, `{saldo}`, `{blik}`, `{link}`, `{linkPliku}`, `{miesiac}` (dopełniacz: „września"), `{miesiacB}`, `{miesiacW}` (miejscownik: „wrześniu"), `{lista}`, `{sumaListy}`.

**Licznik segmentów SMS** (`SmsSegmentCounter`). Polskie znaki wymuszają UCS-2: 70 znaków w pojedynczej wiadomości, 67 w sklejanej. To realny mnożnik kosztu — licz po stronie serwera i pokazuj w UI:

```php
$ucs2 = preg_match('/[ąćęłńóśźżĄĆĘŁŃÓŚŹŻ]/u', $text) === 1;
$single = $ucs2 ? 70 : 160;
$multi  = $ucs2 ? 67 : 153;
$segments = mb_strlen($text) <= $single ? 1 : (int) ceil(mb_strlen($text) / $multi);
```

**E-mail z podsumowaniem miesiąca:**
- Nadawca `noreply@samtrening.com` (Google Workspace), **Reply-To = e-mail trenera**, podpis `{trenerPelny}`. Klient odpowiada trenerowi, nie studiu.
- Podaje **wyłącznie kwotę za wybrany miesiąc** (`{sumaListy}`). Pole `{saldo}` zostało z tego szablonu usunięte świadomie — pokazywanie całego długu obok sumy miesiąca mieszało klientom w głowach. Nierozliczonych sesji z poprzednich miesięcy pilnuje monit SMS i zakładka Płatności.
- Lista sesji **musi być filtrowana do wybranego miesiąca**, a `{miesiac}` w temacie **musi być w dopełniaczu**. Obie pułapki wyłapane w testach prototypu.
- `{lista}` pokazuje wszystkie sesje z miesiąca, także rozliczone — z dopiskiem „· zapłacone" albo „· z przedpłaty". `{sumaListy}` sumuje **tylko sesje należne** (`isPayable()`), każdą w części poza przedpłatą (`beyondPrepayment()`): ta sama reguła co w `Balance`, zawężona do miesiąca. Zapłacone na miejscu zostają na liście, ale nie wchodzą do kwoty do zapłaty (decyzja właściciela z 15.09.2026).
- Wysyłkę odpala trener przyciskiem. Żadnego schedulera.

**Rozdzielenie operacji — reguła niepodlegająca negocjacji.** Zapis sesji i wysyłka wiadomości to dwie osobne operacje. Jeśli SMS nie wyjdzie, **sesja zostaje zapisana**, a komunikat mówi wprost, że wiadomość nie poszła. Nigdy nie wycofuj sesji z powodu błędu dostawcy. Wysyłka idzie przez kolejkę z ponowieniami; trwałe niepowodzenie ląduje w logu jako „Wiadomość nie wyszła" i jest widoczne przy kliencie.

**Walidacja przed wysyłką**: brak `blik_number` u zalogowanego trenera blokuje wysyłkę i odsyła do Ustawień. Świeżo zaproszony trener ma to pole puste — bez tej walidacji SMS wyszedłby z tekstem „BLIK na —".

---

## 10. Zadania w tle i eksport

**Scheduler:**

| Zadanie | Częstotliwość | Co robi |
| --- | --- | --- |
| `SendReminder` | codziennie 10:00 | SMS do klientów po terminie, tylko gdy `reminders_enabled`. Maks. jeden monit na klienta na 7 dni |
| `SweepRetention` | raz w miesiącu | Klienci archiwalni starsi niż `retention_months` → anonimizacja, **nie DELETE** |
| Czyszczenie wygasłych linków | codziennie | Linki do plików żyją 14 dni |

**Kolejki**: wysyłka SMS i e-maili, generowanie plików eksportu. Nic, co blokuje zapis sesji.

**Eksport CSV** (`SessionCsvExport`) — dwa warianty: Zarobki (sesje zalogowanego trenera w zakresie) i Pulpit studia (całe studio, z kolumną Trener). Wymagania:
- separator **`;`** i **BOM UTF-8** na starcie pliku — bez tego polski Excel łamie diakrytyki;
- kwoty jako liczby bez waluty (`200`, nie `200 zł`);
- **notatki z sesji NIE wchodzą do eksportu** — to dane o zdrowiu, a księgowość ich nie potrzebuje (minimalizacja RODO);
- nagłówki kolumn po polsku: `Data; Klient; [Trener;] Usługa; Rodzaj; Kwota PLN; Status płatności`;
- nazwa pliku: `samtrening-2026-09-maciek.csv`, dla studia `samtrening-studio-2026-09.csv`.

**`AnonymizeClient`** (usunięcie danych na żądanie): kasuje kontakt, cel, przeciwwskazania, notatki, opiekuna i pliki; `name` podmienia na `Dane usunięte #XXXX`; **historia sesji zostaje z zanonimizowanymi notatkami**, bo kwoty muszą zgadzać się z rozliczeniami podatkowymi. Ustawia `archived = true`. Nieodwracalne. Zawsze do logu.

---

## 11. Czego prototyp nie pokazuje

Prototyp trzyma dane lokalnie, więc nigdy nie czeka i nigdy nie zawodzi. Trzy rodziny stanów trzeba dołożyć:

**Wczytywanie.** Tabele dostają `<x-skeleton-rows>`: 5–8 wierszy w kolorze `surface` o wysokości docelowego wiersza, **bez animacji pulsowania**. Żadnego spinnera na całym ekranie — układ ma nie skakać. Przyciski zapisu: `wire:loading.attr="disabled"` + `wire:target`, tekst „Zapisuję…".

**Błąd.** Wariant toastu z ciemnym tłem, ramką w akcencie i znakiem `!` (jest w prototypie — metoda `blad()`). Konflikt edycji (dwie osoby zmieniają kwotę tej samej sesji): wygrywa zapis późniejszy, ale **oba trafiają do logu**.

**Puste stany.** `<x-empty-state>` z treścią zależną od przyczyny: pusta kartoteka, pusty filtr, puste archiwum, klient bez sesji, brak zaległości. **Nie zwijaj ich do jednego „Brak danych".** Bez importu danych pusta kartoteka jest pierwszym ekranem, jaki zobaczy każdy trener — to nie przypadek brzegowy.

**Bezpieczeństwo danych zdrowotnych.** `contraindications` i `notes` to szczególna kategoria danych (art. 9 RODO). Szyfruj je w spoczynku (`encrypted` cast wystarczy), trzymaj pliki na prywatnym disku z linkami podpisanymi na 14 dni, rób kopie zapasowe.

**Idempotencja `LogSession`.** Podwójne kliknięcie na słabym łączu nie może utworzyć dwóch wpisów — token formularza albo klucz idempotencji na `(client_id, date, price, created_at ±5s)`.

---

## 12. Tokeny wizualne

Paleta wyciągnięta z pikseli produkcyjnej strony samtrening.com. Struktura jest zgodna z design systemem Modernist (siatka modułowa, promień 0, reguły 2 px, Archivo, wyrównanie do lewej); różni się tylko podłoże — marka SAMtrening jest ciemna.

```css
/* resources/css/app.css — Tailwind 4 */
@import 'tailwindcss';

@theme {
  --color-bg: #0a0909;
  --color-surface: #151414;
  --color-ink: #fafaf7;
  --color-muted: #babab8;
  --color-accent: #e8ff3e;
  --color-accent-hover: #d2e832;
  --color-accent-100: #23290a;  /* ciemne limonkowe wypełnienia paneli (100–300) */
  --color-accent-200: #303b0c;
  --color-accent-300: #47580f;
  --color-accent-700: #e8ff3e;
  --color-accent-800: #eeff7a;  /* jasna limonka jako TEKST na tych panelach (700–900) */
  --color-accent-900: #f6ffb4;
  /* pełne rampy accent i neutral jak w :root prototypu — resources/css/app.css */
  --color-divider: rgba(250, 250, 247, 0.22);

  --font-display: 'Anton', sans-serif;  /* h1, waga 400, UPPERCASE */
  --font-sans: 'Archivo', sans-serif;   /* wszystko inne, 400 / 800 */

  --radius-*: initial;                  /* bez skali rounded-* — promień 0 wszędzie, reguła marki */
}
```

Trzy rzeczy, które łatwo zepsuć:

1. **`line-height: 1.14` na Antonie.** Ciaśniej — obcina polskie ogonki i kreski (Ś, Ć, Ę). Nie zmniejszaj.
2. **Rampa akcentu jest odwrócona** względem klasycznej, bo tło jest ciemne: kroki 100–300 to ciemne wypełnienia, 700–900 to jasne limonki na tekst. Nie odwracaj z powrotem.
3. **Promień 0 px wszędzie.** Nic nie jest zaokrąglone. To decyzja marki, nie przeoczenie.

Reszta: skala odstępów 4/8/12/16/24/32, linie 2 px między sekcjami i 1 px między wierszami, wszystko wyrównane **do lewej** (także etykiety w szerokich przyciskach), focus `outline: 2px solid #e8ff3e; outline-offset: 2px` — nigdy domyślny niebieski. Jedyna animacja to ticker (34 s, liniowo, w pętli); nie dodawaj przejść.

Fonty: Anton (400) i Archivo (400–900) z Google Fonts. Jeśli studio ma licencję na font display ze strony (możliwe Druk albo Monument Extended) — podmień Antona.

**Widok mobilny tabel** — w prototypie `@media (max-width: 760px)`: każdy `<tr>` renderuje się jako karta, a `<td>` bierze etykietę z atrybutu `data-label`. Przenieś ten wzorzec do `<x-data-table>` i pamiętaj, że **każda kolumna musi mieć `data-label`** (puste `data-label=""` dla kolumny z przyciskami). Pola dotykowe minimum **44 px** — przyciski w wierszach mają w prototypie 32–38 px i na telefonie są za małe.

---

## 13. Rozbieżności — `SPEC-EKRANY.md` vs. stan ustaleń

`SPEC-EKRANY.md` powstawał w trakcie projektowania. Te zapisy są **nieaktualne** — obowiązuje wersja z tego pliku:

| W `SPEC-EKRANY.md` | Obowiązuje |
| --- | --- |
| Polskie nazwy pól w §Data Model (`stawka`, `cena`, `typ`, `kontuzje`…) | Identyfikatory po angielsku — §5, z mapowaniem |
| Stripe, bramka płatnicza, linki płatnicze, webhooki, „status Stripe" w Ustawieniach | **Poza zakresem.** BLIK ręcznie od trenera. Przełącznik „Automatyczne odznaczanie płatności" i ostrzeżenie o nim → usuń z Ustawień |
| „Link BLIK" jako link do zapłaty | SMS z **numerem BLIK trenera** (`{blik}`), nie z linkiem. Klient robi przelew na telefon |
| Szablon „Potwierdzenie płatności (tylko gdy `autoOdznaczanie`)" | **Nie wysyłamy** — decyzja właściciela z 11.09.2026 (SC-34) |
| E-mail podsumowania z polem `{saldo}` i „Całe nierozliczone saldo" | Tylko kwota za wybrany miesiąc. `{saldo}` usunięte z tego szablonu |
| Temat „Podsumowanie {miesiac}" | `SAMtrening — {miesiacB}: {sumaListy} do zapłaty` |
| Faktury: kolumna „Dokument", akcja „Faktura", blok „Dokument sprzedaży", integracja księgowa | **Odłożone.** `company_name` i `tax_id` zostają w bazie, bo przyjdą przy pierwszym kliencie firmowym. UI faktur nie budujemy w pierwszej wersji — zamiast tego eksport CSV |
| „Trenerzy — wrzesień", „Sesje / wrzesień", „Sesje we wrześniu" jako stałe nagłówki | Nagłówki zależne od wybranego zakresu (miesiąc albo cały rok) |
| „Zalecenie: poniżej ~720 px zamień tabele na listę kart" jako do zrobienia | Zrobione w prototypie, breakpoint **760 px**, mechanizm `data-label` — §12 |
| Sekcja „Dług RODO" z umowami powierzenia ze Stripe'em | Stripe nie występuje. Zostają: rejestr czynności, umowa z dostawcą SMS, umowy z trenerami, szyfrowanie pól zdrowotnych, czyszczenie po retencji |
| „wybierz stack odpowiedni dla projektu" | Stack ustalony — §2 |
| „Zmiany — 10.09.2026": nadawcą e-maila z podsumowaniem jest trener | Nadawca `noreply@samtrening.com` (Google Workspace), Reply-To = e-mail trenera — §9 |
| „Przypomnij trenerowi" (Zaległości studia) bez określonego kanału | Wyskakujące powiadomienie w aplikacji przy najbliższym wejściu trenera; bez e-maila i pushy (decyzja z 11.09.2026) |
| Ustawienia bez podziału na role | Zasady studia zmienia tylko właściciel, trener widzi je do odczytu; numer BLIK każdy ustawia sam (decyzja z 11.09.2026) |

Wszystko pozostałe w `SPEC-EKRANY.md` — teksty ekranów, walidacje, odstępy, stany puste, treści SMS-ów, reguły biznesowe — jest aktualne i obowiązujące.

---

## 14. Definicja gotowości

Pierwsza wersja jest gotowa do wdrożenia, gdy:

- [ ] Trener loguje się, wbija sesję w dwóch dotknięciach z domykacza tygodnia i widzi poprawne saldo klienta.
- [ ] Saldo liczy `Billing\Balance` z sesji, nie z kolumny. Testy pokrywają: odwołanie naliczone, odwołanie darmowe, nieobecność, nadpisaną kwotę, klienta archiwalnego z historią.
- [ ] Kasia nie widzi klientów Bartka — sprawdzone **przez podmianę id w URL-u**, nie tylko przez interfejs.
- [ ] Właściciel przełącza się na panel admina, widzi obrót studia w miesiącu i w całym roku, i nie da się go zablokować.
- [ ] SMS i e-mail wychodzą; błąd dostawcy nie wycofuje zapisanej sesji.
- [ ] Trener bez `blik_number` nie może wysłać wiadomości i wie, gdzie go ustawić.
- [ ] Każda akcja zmieniająca stan ma wpis w `activity_entries` z autorem i kontekstem.
- [ ] CSV otwiera się w polskim Excelu z poprawnymi diakrytykami i bez notatek z sesji.
- [ ] Wszystkie tabele działają na telefonie 390 px, pola dotykowe ≥ 44 px.
- [ ] Pusta kartoteka wygląda jak zaproszenie do działania, nie jak błąd.
- [ ] `contraindications` i `notes` szyfrowane, pliki na prywatnym disku, kopie zapasowe działają.

Po wdrożeniu: dajcie CRM Kasi i Bartkowi na tydzień równolegle z obecnym sposobem pracy i zbierzcie listę „tu się zaciąłem". Poprawki z realnego użycia będą warte więcej niż kolejne funkcje.

**Najsłabszy punkt całości, wynikający z modelu, nie z implementacji:** przy trzech osobnych numerach BLIK nikt nie zautomatyzuje odznaczania wpłat. Po każdym przelewie ktoś musi wejść i kliknąć „Zapłacone". Jeśli ten nawyk się nie utrzyma, salda zaczną kłamać w ciągu miesiąca. Warto o tym powiedzieć trenerom pierwszego dnia.
