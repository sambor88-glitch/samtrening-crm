# SAMtrening CRM

CRM dla studia treningu personalnego **SAMtrening** — Plac Na Groblach 23, Kraków.

Trzech trenerów, formuła 1:1, rozliczenie za odbyte sesje. Trener wbija fakt po treningu, system liczy saldo klienta i zarobek trenera. Grafik zostaje w Google Calendar — CRM go nie dubluje, ale go czyta: podpowiada sesje do
wbicia, których jeszcze nie ma w bazie (`docs/KALENDARZ.md`).

---

## Start

```bash
# 1. przeczytaj dokumentację — w tej kolejności
docs/START-TUTAJ.md      # stack, zakres, architektura, baza, kolejność budowy
docs/SPEC-EKRANY.md      # specyfikacja każdego ekranu: teksty, stany, walidacje
docs/WDROZENIE.md        # serwer, kolejka, scheduler, deploy i czego brakuje do produkcji
docs/RODO-TEKSTY.md      # projekt zgody i klauzuli informacyjnej — do sprawdzenia przez prawnika
docs/AGENT-API.md        # read-only API dla Pulpitu Maćka: token, pola, definicje liczb
docs/KALENDARZ.md        # sesje z Google Calendar: lista do zatwierdzenia zamiast wbijania po jednej

# 2. otwórz prototyp w przeglądarce
prototype/SAMtrening\ CRM.dc.html
```

Prototyp ma dane demo i skróty logowania — przechodzi się między rolami bez haseł. To referencja projektowa, **nie kod produkcyjny do skopiowania**.

## Testy

```bash
./vendor/bin/pest          # 490 testów, baza w pamięci — nie potrzebuje Dockera
./vendor/bin/pint --test   # formatowanie, bez poprawiania
```

Oba chodzą też w GitHub Actions przy każdym wejściu na `main` i przy każdym pull requeście
(`.github/workflows/testy.yml`). Lista gotowości z §14 jest osobnym testem:
`./vendor/bin/pest --filter=DefinitionOfDone`.

## Uruchomienie lokalne

Wymagania: PHP 8.3+, Composer, Node 22+, Docker (lokalna baza MySQL).

```bash
cp .env.example .env    # ustaw DB_PASSWORD — dowolne hasło do lokalnej bazy
docker compose up -d    # MySQL 8.4, dane logowania z .env
composer run setup      # zależności, klucz aplikacji, migracje, build frontu
composer run dev        # serwer, kolejka, logi i Vite naraz
```

Testy: `composer test` (Pest). Formatowanie kodu: `vendor/bin/pint`.

## Stack

Laravel 13 · Blade + Livewire 4 · Tailwind 4 · MySQL 8 · hosting na Laravel Forge

Bez SPA, bez bramki płatniczej. Panel stoi na Livewire i sesji — bez API i bez Sanctuma.
Jedyny wyjątek to read-only `/api/agent/v1` dla Pulpitu Maćka (`docs/AGENT-API.md`): dwa GET-y
z tokenem Bearer, nic poza odczytem. Strefa `Europe/Warsaw`, tygodnie ISO, locale `pl`.

## Konwencje

- **Identyfikatory w kodzie po angielsku** — tabele, kolumny, modele, klasy, metody, zmienne, nazwy gałęzi i commitów. Polski zostaje wyłącznie w treści widzianej przez użytkownika: teksty UI, szablony SMS i e-maili, komunikaty walidacji, nagłówki eksportu CSV.
- **Budowa modułowa** — logika biznesowa w klasach akcji (`app/Domain/<Module>/Actions`, jedna publiczna metoda `handle()`), komponenty Livewire tylko orkiestrują i walidują, zapytania w `Queries/`, powtarzalne UI jako komponenty Blade. Drzewo katalogów i siedem zasad: `docs/START-TUTAJ.md` §4.
- **Kwoty w groszach jako `integer`.** Nigdy `float`.
- **Daty liczy PHP w strefie `Europe/Warsaw`.** „Dziś", tydzień i miesiąc wyliczamy w PHP i przekazujemy do zapytań jako parametry — bez `NOW()` i `CURDATE()` w SQL.
- Tabela sesji treningowych to `training_sessions` — `sessions` jest zajęte przez sterownik sesji Laravela.
- Saldo i zarobek liczy dokładnie jedna klasa (`Domain\Billing\Balance`, `Domain\Billing\Earnings`). Żadnej kolumny z saldem w bazie. Które sesje opłaciła wpłata z góry, rozstrzyga wyłącznie `Domain\Billing\PrepaymentPool` — po każdej zmianie liczy klienta od zera.

## Model biznesowy — z niego wynika architektura

1. Brak pakietów i abonamentów. Należność powstaje w chwili wbicia odbytej sesji. Klient może za to zapłacić z góry (od 15.09.2026): wpłata to pula pieniędzy na karcie, z której schodzą kolejne sesje — nie karnet na liczbę wejść.
2. Brak kalendarza w systemie. CRM rejestruje fakty po treningu.
3. 100% stawki idzie do trenera. Studio nie pobiera prowizji.
4. Klient nie ma konta. Dostaje SMS-y, e-maile i linki do plików.

## Czego nie budujemy — decyzje, nie pominięcia

Kalendarza i rezerwacji · bramki płatniczej (BLIK ręcznie od trenera na jego numer) · automatycznego odznaczania wpłat · importu danych · konta dla klienta · automatycznej wysyłki podsumowań · trybu offline · pakietów na liczbę wejść i prowizji · multi-tenancy.

Pełne uzasadnienie każdej pozycji: `docs/START-TUTAJ.md` §3.

## Struktura repozytorium

```
app/, resources/, …     aplikacja Laravel (katalog główny)
compose.yaml            lokalna baza MySQL w Dockerze
docs/START-TUTAJ.md     punkt wejścia dla programisty
docs/SPEC-EKRANY.md     specyfikacja 16 ekranów i 5 dialogów
docs/AGENT-API.md       read-only API dla Pulpitu Maćka
docs/KALENDARZ.md       sesje z Google Calendar do zatwierdzenia
prototype/              działający prototyp HTML + arkusz systemu wizualnego
```

`docs/` i `prototype/` zostają jako źródło prawdy dla wyglądu i zachowania.

## Definicja gotowości

Checklista wdrożeniowa: `docs/START-TUTAJ.md` §14.

**Najsłabszy punkt, wynikający z modelu, nie z implementacji:** przy trzech osobnych numerach BLIK nikt nie zautomatyzuje odznaczania wpłat. Po każdym przelewie ktoś musi kliknąć „Zapłacone". Jeśli ten nawyk się nie utrzyma, salda zaczną kłamać w ciągu miesiąca.
