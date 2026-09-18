# Sesje z kalendarza Google

SC-65 · wdrożone 18.09.2026

CRM czyta grafik z Google Calendar i pokazuje treningi, których nie ma jeszcze w bazie.
Nic nie wbija sam — lista jest propozycją, klikasz Ty.

---

## 1. Dlaczego lista, a nie automat

Kalendarz wie, że wydarzenie było wpisane. Nie wie, że klient odwołał pół godziny wcześniej,
że trening został skrócony ani że zapłacił gotówką przy drzwiach. **Ty wiesz.**

`START-TUTAJ.md` §6 mówi, że wbicie sesji to jedyny moment, w którym powstaje należność.
Gdyby robił to automat, pomyłka o jedną sesję zmieniłaby saldo klienta i nikt by tego nie
wyłapał — nie ma z czym porównać. Stąd checkboxy: odznaczasz to, czego nie było.

To nadal jest **dziesięć sekund tygodniowo zamiast wbijania po jednej**.

## 2. Osobny token, celowo

Poczta i kalendarz mają **własne tokeny odświeżania**, choć chodzą na tych samych danych
klienta OAuth (ten sam projekt Google Cloud).

Jeden token na oba znaczyłby, że cofnięta zgoda na kalendarz zatrzymuje monity.
`START-TUTAJ.md` §9 nie pozwala, żeby awaria jednego kanału pociągała drugi.

```bash
php artisan calendar:authorize                 # wypisze adres zgody
php artisan calendar:authorize --code=KOD      # wymieni kod na token
```

Drugi przebieg zwraca `GOOGLE_CALENDAR_REFRESH_TOKEN` do wklejenia w Forge.
**`GMAIL_REFRESH_TOKEN` zostaw bez zmian** — poczta chodzi na swoim.

Prosimy wyłącznie o `calendar.readonly`. CRM nigdy nie pisze do grafiku.

| zmienna | do czego |
|---|---|
| `GOOGLE_CALENDAR_REFRESH_TOKEN` | wynik `calendar:authorize` |
| `GOOGLE_CALENDAR_ID` | `primary` albo identyfikator kalendarza studia |
| `GOOGLE_CALENDAR_LOOKBACK_DAYS` | jak daleko wstecz szukać (domyślnie 30) |
| `GOOGLE_CALENDAR_CACHE_SECONDS` | jak długo trzymać odpowiedź (domyślnie 300) |
| `GOOGLE_CALENDAR_CLIENT_ID/SECRET` | opcjonalne; puste = te same co dla poczty |

Bez tokenu ekran mówi „Kalendarz niepodłączony" i **nie woła nigdzie** — reszta CRM działa
bez zmian.

## 3. Co trafia na listę, a co nie

| wpis w kalendarzu | wynik |
|---|---|
| Trening o konkretnej godzinie, dopasowany do karty | ✅ na liście, zaznaczony |
| Trening cykliczny | ✅ każde wystąpienie osobno (`singleEvents`) |
| Wpis odwołany w Google | ❌ pomijany |
| Wpis całodniowy („Urlop") | ❌ pomijany — to nie sesja |
| Wpis bez tytułu | ❌ nie ma po czym dopasować |
| Sesja już wbita tego dnia u tego klienta | ❌ nie proponowana drugi raz |
| Klient innego trenera | ❌ nie na tym ekranie |
| Tytuł pasujący do dwóch kart | ⚠️ „nierozpoznane", z nazwami obu |
| Tytuł niepasujący do nikogo | ⚠️ „nierozpoznane" |

Dopasowanie idzie po `calendar_aliases` — te same, których używa Pulpit (`AGENT-API.md` §4).
Reguła jest ta sama: **alias, który mógłby wskazać dwie osoby, nie jest generowany.**
Nietrafiony wpis to dziura, którą widać; wpis przypisany do złej karty to pieniądze w złym
miejscu, których nie widzi nikt.

Jeśli coś regularnie ląduje w „nierozpoznanych", dopisz klientowi alias ręcznie — instrukcja
w `AGENT-API.md` §4.

## 4. Zakres dat i odświeżanie

Okno zaczyna się przy **ostatniej wbitej przez Ciebie sesji**, nie dalej niż
`GOOGLE_CALENDAR_LOOKBACK_DAYS` wstecz. Kto wbija na bieżąco, pyta Google o dwa dni zamiast
o miesiąc. Trener, który nie wbił jeszcze nic, dostaje całe okno.

Odpowiedź jest **cache'owana na pięć minut**: ekran jest w Livewire, więc każde kliknięcie
checkboxa to nowy request, a round trip do Google przy każdym psułby wrażenie. Przycisk
„Odśwież z kalendarza" pyta na nowo. Po wbiciu cache czyści się sam, żeby wbite sesje
zniknęły z listy.

## 5. Cena

Domyślnie stawka z karty klienta, **pole zostaje edytowalne**. `training_sessions.price`
bywa inne niż `clients.rate` — krótsza sesja, jednorazowa cena. Wpisana wartość wygrywa;
nieczytelna wraca do stawki, zamiast wbić zero.

Wszystko wchodzi jako `Trening personalny 1:1`, sesja odbyta, na saldzie. Zapłacone
odznaczasz jak zwykle, z karty klienta.

## 6. Czego to nie robi

- **Nie pisze do kalendarza.** Zakres to `calendar.readonly`.
- **Nie wbija samo.** Nigdy, pod żadnym warunkiem — patrz §1.
- **Nie zna przyszłości w rozumieniu Pulpitu.** To osobna sprawa: `AGENT-API.md` §8 wyjaśnia,
  czemu `next_session` w API agenta jest zawsze puste.
- **Nie wykrywa płatności.** SC-66, osobne zadanie — i tam też źródłem prawdy jesteś Ty.

## 7. Gdy kalendarz nie odpowie

Ekran pokazuje „Kalendarz nie odpowiedział" i wraca do normalnej pracy. Sesje wbijasz
ręcznie, jak dotąd. Błąd ląduje w logu aplikacji.

To ta sama zasada co przy SMS-ach (`START-TUTAJ.md` §9): awaria dostawcy nie może zatrzymać
tego, co i tak działa bez niego.
