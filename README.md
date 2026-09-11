# SAMtrening CRM

CRM dla studia treningu personalnego **SAMtrening** — Plac Na Groblach 23, Kraków.

Trzech trenerów, formuła 1:1, rozliczenie za odbyte sesje. Trener wbija fakt po treningu, system liczy saldo klienta i zarobek trenera. Grafik zostaje w Google Calendar — CRM go nie dubluje.

---

## Start

```bash
# 1. przeczytaj dokumentację — w tej kolejności
docs/START-TUTAJ.md      # stack, zakres, architektura, baza, kolejność budowy
docs/SPEC-EKRANY.md      # specyfikacja każdego ekranu: teksty, stany, walidacje

# 2. otwórz prototyp w przeglądarce
prototype/SAMtrening\ CRM.dc.html
```

Prototyp ma dane demo i skróty logowania — przechodzi się między rolami bez haseł. To referencja projektowa, **nie kod produkcyjny do skopiowania**.

## Stack

Laravel 11 · Blade + Livewire 3 · Tailwind · MySQL 8 lub PostgreSQL 15

Bez SPA, bez API, bez bramki płatniczej. Strefa `Europe/Warsaw`, tygodnie ISO, locale `pl`.

## Konwencje

- **Identyfikatory w kodzie po angielsku** — tabele, kolumny, modele, klasy, metody, zmienne, nazwy gałęzi i commitów. Polski zostaje wyłącznie w treści widzianej przez użytkownika: teksty UI, szablony SMS i e-maili, komunikaty walidacji, nagłówki eksportu CSV.
- **Budowa modułowa** — logika biznesowa w klasach akcji (`app/Domain/<Module>/Actions`, jedna publiczna metoda `handle()`), komponenty Livewire tylko orkiestrują i walidują, zapytania w `Queries/`, powtarzalne UI jako komponenty Blade. Drzewo katalogów i siedem zasad: `docs/START-TUTAJ.md` §4.
- **Kwoty w groszach jako `integer`.** Nigdy `float`.
- Tabela sesji treningowych to `training_sessions` — `sessions` jest zajęte przez sterownik sesji Laravela.
- Saldo i zarobek liczy dokładnie jedna klasa (`Domain\Billing\Balance`, `Domain\Billing\Earnings`). Żadnej kolumny z saldem w bazie.

## Model biznesowy — z niego wynika architektura

1. Brak pakietów i abonamentów. Należność powstaje w chwili wbicia odbytej sesji.
2. Brak kalendarza w systemie. CRM rejestruje fakty po treningu.
3. 100% stawki idzie do trenera. Studio nie pobiera prowizji.
4. Klient nie ma konta. Dostaje SMS-y, e-maile i linki do plików.

## Czego nie budujemy — decyzje, nie pominięcia

Kalendarza i rezerwacji · bramki płatniczej (BLIK ręcznie od trenera na jego numer) · automatycznego odznaczania wpłat · importu danych · konta dla klienta · automatycznej wysyłki podsumowań · trybu offline · pakietów i prowizji · multi-tenancy.

Pełne uzasadnienie każdej pozycji: `docs/START-TUTAJ.md` §3.

## Struktura repozytorium

```
docs/START-TUTAJ.md     punkt wejścia dla programisty
docs/SPEC-EKRANY.md     specyfikacja 16 ekranów i 5 dialogów
prototype/              działający prototyp HTML + arkusz systemu wizualnego
```

Kod aplikacji trafi do katalogu głównego po `laravel new`. `docs/` i `prototype/` zostają jako źródło prawdy dla wyglądu i zachowania.

## Definicja gotowości

Checklista wdrożeniowa: `docs/START-TUTAJ.md` §14.

**Najsłabszy punkt, wynikający z modelu, nie z implementacji:** przy trzech osobnych numerach BLIK nikt nie zautomatyzuje odznaczania wpłat. Po każdym przelewie ktoś musi kliknąć „Zapłacone". Jeśli ten nawyk się nie utrzyma, salda zaczną kłamać w ciągu miesiąca.
