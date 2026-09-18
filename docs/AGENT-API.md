# Agent API — read-only API dla Pulpitu Maćka

Wersja 1.0 · wdrożone 18.09.2026 · na podstawie `AGENT_API_SPEC.md`

Dwa GET-y, z których agent Claude czyta sesje i pieniądze. CRM jest jedynym miejscem, które zna
ceny — Google Calendar wie, że trening był, ale nie wie, ile kosztował.

---

## 1. Łańcuch wywołania

Tablica na claude.ai stoi w piaskownicy przeglądarki z zablokowanym ruchem wychodzącym i **sama
tego endpointu nie zawoła**. Wygląda to tak:

```
Laravel CRM  →  GET /api/agent/v1/...  →  agent Claude  →  baza artefaktu  →  tablica czyta
```

Stąd dwie decyzje, które wyglądałyby dziwnie przy zwykłym API:

- **Zwykły GET z tokenem Bearer.** Żadnego CORS, cookies, sesji ani OAuth — klientem jest skrypt.
  Trasy agenta nie dostają middleware sesji ani CSRF, na których stoi panel (`bootstrap/app.php`).
- **Świeżość rzędu godziny wystarczy.** Nic tu nie jest optymalizowane pod real-time.

Gdyby kiedyś miało działać na żywo bez pośrednika, ten sam endpoint owija się w serwer MCP. To
osobna robota.

---

## 2. Token

Bearer, a w bazie wyłącznie `hash('sha256', $token)`. Wykradziona tabela nie daje się odtworzyć do
działającego tokenu. Bez UI — token wydaje komenda i wypisuje **raz**:

```bash
php artisan agent:token "Pulpit Maćka" --scope=crm.read --days=365
```

Prefiks `samtr_` pozwala rozpoznać wyciek w logach i w repo.

| kolumna | do czego |
|---|---|
| `token_hash` | jedyny ślad po tokenie; `$hidden`, więc nie wypłynie przez `toArray()` |
| `scopes` | `["crm.read"]`; pusta lista nie otwiera niczego — nie jest wildcardem |
| `expires_at` | `--days=0` daje token bezterminowy |
| `revoked_at` | odwołanie to znacznik, nie `DELETE` — ślad zostaje |
| `last_used_at` | zapisywany przy każdym wywołaniu, **bez ruszania `updated_at`** |

Zły, odwołany i wygasły token dostają tę samą odpowiedź `401`. Rozróżnianie ich podpowiadałoby
zgadującemu, który strzał był kiedyś trafiony.

**Token otwiera wyłącznie grupę `api/agent/v1`.** Nie panel, nie resztę aplikacji. Limit:
60 wywołań na minutę — dużo powyżej odświeżania co godzinę i za mało, żeby szybko zassać studio.

Odwołanie tokenu:

```sql
UPDATE api_tokens SET revoked_at = NOW() WHERE name = 'Pulpit Maćka';
```

---

## 3. Co wychodzi, a co nie

Imię i nazwisko, liczby, kwoty. **Nic więcej.**

Bez telefonów, e-maili, adresów, przeciwwskazań zdrowotnych, notatek z treningów, danych opiekuna,
nazwy firmy i NIP-u. Powód jest praktyczny: to, co agent pobierze, ląduje w bazie artefaktu, a
artefakt kiedyś ktoś zobaczy. Czego tam nie ma, to nie wycieknie.

Egzekwuje to **biała lista pól** wypisana ręcznie w `Agent\Queries\ClientList` — żaden model nie
jest serializowany w całości. Pilnuje tego test, który porównuje klucze odpowiedzi z listą
dozwolonych i przeszukuje całe ciało JSON-a pod kątem danych wrażliwych
(`tests/Feature/Agent/AgentClientsTest.php`). Dodanie pola do karty klienta nie przecieknie do API
samo z siebie — trzeba je dopisać świadomie, a test i tak zapyta o zdanie.

Kwoty: **grosze jako `int`**, pola z sufiksem `_minor`. Daty: ISO 8601 z offsetem, strefa
`Europe/Warsaw`.

---

## 4. `GET /api/agent/v1/clients`

Cały studio, bez parametrów. Klienci zarchiwizowani też — były klient nadal liczy się w miesiącach,
w których trenował.

```json
{
  "generated_at": "2026-09-18T09:00:00+02:00",
  "clients": [
    {
      "id": 12,
      "name": "Anna Motkowicz",
      "active": true,
      "rate_minor": 12000,
      "currency": "PLN",
      "calendar_aliases": ["anna motkowicz", "motkowicz", "anna", "ania"],
      "balance_minor": -24000,
      "last_session": "2026-09-17",
      "next_session": null
    }
  ]
}
```

**`balance_minor` ma znak odwrotny niż saldo w CRM.** Ujemne = klient zalega, dodatnie = ma
nadpłatę (wpłacił z góry i jeszcze nie wytrenował). W panelu dług jest liczbą dodatnią — tu nie.

`last_session` to ostatni dzień, w którym ktoś **naprawdę trenował**: odwołania i nieobecności się
nie liczą.

### `calendar_aliases`

Po tym tablica dopasowuje wydarzenie z kalendarza do karty. Dopasowanie idzie **na całe wyrazy**,
więc `bogucka` nie złapie `bogucki`, a alias wieloczłonowy (`ula gosia`) pasuje, gdy wszystkie jego
słowa są w tytule.

Kolumna `clients.calendar_aliases` jest **nullowalna i tak ma zostać**:

- **`null`** → listę generuje `Agent\CalendarAliases` z imienia i nazwiska: nazwisko, pełne imię i
  nazwisko, samo imię, popularne zdrobnienie (Katarzyna → Kasia), a do każdego wariant bez
  polskich znaków, bo połowa kalendarza jest pisana bez ogonków.
- **lista** → wygrywa nad generowaną. Tu wpisuje się przypadki, których z nazwiska nie da się
  wyprowadzić.
- **pusta lista** → decyzja („ten klient nie ma aliasów"), a nie brak. Generator jej nie nadpisze.

**Reguła, która kosztowała osobny warunek:** alias, który mógłby wskazywać na dwie osoby, nie jest
generowany w ogóle. Przy dwóch Annach samo `anna` wypada, a `motkowicz` i `anna motkowicz`
zostają. Nietrafione wydarzenie to dziura, którą ktoś zauważy; wydarzenie przypisane do złego
klienta to pieniądze w złym miejscu, których nie zauważy nikt.

Nazwisko zostaje zawsze, nawet dzielone — bez niego karta nie miałaby się po czym dopasować.

Przypadki wymagające wpisania ręcznego (z kalendarza Maćka):

```sql
UPDATE clients SET calendar_aliases = '["ula gosia","lidacka"]' WHERE name = 'Małgorzata Lidacka';
```

| wpis w kalendarzu | klient | skąd |
|---|---|---|
| „Anna trening", „Trening Anna" | Anna Motkowicz | generowane (`anna`) |
| „Kasia Bogucka" | Katarzyna Bogucka | generowane (`bogucka`, `kasia`) |
| „Jakub Żurek - Trening" | Jakub Żurek | generowane (`żurek`, `zurek`) |
| „M.T.I.P Róg- trening" | Tomasz Róg | generowane (`róg`) |
| „Wojciech, Nina, Tymon" | Wojciech Solecki | generowane (`wojciech`) |
| **„Ula I Gosia"** | Małgorzata Lidacka | **ręcznie** — z nazwiska nie wynika |

---

## 5. `GET /api/agent/v1/summary?month=YYYY-MM`

`month` opcjonalny, domyślnie bieżący miesiąc. Zły format → `422`. Rok bez miesiąca (`2026`) też
jest błędem: odpowiedź ma kształt jednego miesiąca, z tablicą dzień po dniu.

```json
{
  "month": "2026-09",
  "generated_at": "2026-09-18T09:00:00+02:00",
  "currency": "PLN",
  "sessions":  { "done": 51, "planned": 53, "cancelled": 2 },
  "revenue_minor": { "due": 984000, "paid": 720000, "outstanding": 264000 },
  "by_client": [{ "id": 12, "done": 13, "planned": 15, "due_minor": 156000, "paid_minor": 156000 }],
  "by_day": [{ "date": "2026-09-01", "done": 3, "revenue_minor": 36000 }]
}
```

| pole | definicja |
|---|---|
| `sessions.planned` | wszystkie sesje wpisane w miesiącu **bez odwołanych** (odbyte + nieobecności) |
| `sessions.done` | z tego odbyte |
| `sessions.cancelled` | odwołane, osobno, **nie wchodzą do `planned`** |
| `revenue_minor.due` | należne za sesje **odbyte w tym miesiącu** — strumień |
| `revenue_minor.paid` | pieniądze, które **wpłynęły w tym miesiącu**, za jakikolwiek okres — strumień |
| `revenue_minor.outstanding` | zaległości całego studia **na dzień `generated_at`** — stan |

**`outstanding` to nie `due - paid`.** Klient może zapłacić z góry albo spłacić zaległość sprzed
pół roku i odejmowanie się rozjeżdża. `due` i `paid` są przepływami w obrębie miesiąca,
`outstanding` jest zdjęciem stanu na dziś i jest kumulatywne — dług nie należy do miesiąca.

Sesja „nie naliczono" (`waived`) nie jest należna za nic, więc nie wchodzi do `due`.

`paid` zlicza pieniądze z dwóch źródeł: odznaczonych płatności (`paid_at`) i wpłat z góry
(`prepayments.paid_on`). Sesja rozliczona gotówką wnosi tylko tę część, której nie pokryła
przedpłata — reszta weszła wcześniej, jako przedpłata, i liczenie całej ceny policzyłoby te
pieniądze dwa razy.

`by_day` zawiera **wszystkie dni miesiąca**, także zerowe — tablica rysuje z tego słupki i nie
zgaduje dziur.

---

## 6. Sprawdzenie po wdrożeniu

```bash
TOKEN='samtr_...'
BASE='https://samtrening-crm-hdvivquh.on-forge.com/api/agent/v1'

curl -s -o /dev/null -w '%{http_code}\n' "$BASE/clients"                                   # 401
curl -s -o /dev/null -w '%{http_code}\n' -H 'Authorization: Bearer zle' "$BASE/clients"    # 401
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/clients" | jq '.clients | length'
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/clients" \
 | jq '[.clients[] | select(.rate_minor == null or (.calendar_aliases | length) == 0)] | length'  # 0
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/summary" | jq '.sessions, .revenue_minor'
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/summary?month=2026-09" | jq '.by_day | length'   # 30
curl -s -o /dev/null -w '%{http_code}\n' -H "Authorization: Bearer $TOKEN" "$BASE/summary?month=wrzesien"  # 422
```

Wszystkie dziewięć kryteriów odbioru ze specyfikacji ma odpowiednik w testach:

```bash
./vendor/bin/pest tests/Feature/Agent
```

---

## 7. Poza zakresem

Serwer MCP, UI do zarządzania tokenami, webhooki i push do tablicy, jakiekolwiek dane osobowe
poza imieniem i nazwiskiem.

Zapis był tu do 18.09.2026 — dziś jest jeden wyjątek, opisany w §10. Poza nim nadal nic: każda
inna metoda niż `GET` na trasach z §4 i §5 zwraca `405`.

---

## 8. Gdzie CRM nie da tego, co zakłada specyfikacja

Cztery miejsca, w których specyfikacja zakładała dane, których CRM nie miał. Warto je znać przed
czytaniem liczb z tablicy.

### `next_session` zawsze `null`

**CRM nie prowadzi grafiku.** Grafik jest w Google Calendar i świadomie nie jest tu dublowany
(`README.md`, `START-TUTAJ.md` §3). `training_sessions` to fakty wbijane **po** treningu — nie ma
statusu „zaplanowana" i nie ma gdzie trzymać przyszłości.

Pole zostaje w odpowiedzi, żeby nie zmieniać kształtu JSON-a, i zawsze jest `null`. Nie jest to
błąd do naprawienia w CRM: tablica czyta kalendarz i wie o przyszłych sesjach więcej, niż CRM
mógłby jej powiedzieć.

### `sessions.planned` znaczy „wpisane", nie „zaplanowane"

Zaimplementowane dosłownie według definicji ze specyfikacji: sesje wpisane w miesiącu bez
odwołanych, czyli odbyte + nieobecności.

**Uwaga na przykład ze specyfikacji**, gdzie `planned: 82` przy `done: 51`. Taka różnica wychodzi
tylko wtedy, gdy `planned` liczy sesje z kalendarza do końca miesiąca — a tego CRM nie widzi. Przy
tej implementacji `planned` będzie o kilka większe od `done`, nie o trzydzieści. Jeśli tablica
potrzebuje liczby sesji zaplanowanych do końca miesiąca, musi ją policzyć z kalendarza sama.

### `paid` wymagało nowej kolumny

Specyfikacja chce cash flow („wpłaty zaksięgowane w tym miesiącu, niezależnie za jaki okres").
CRM nie zapisywał **daty wpłaty** — `MarkAsPaid` ustawiał tylko status, a jedyną datą przy sesji
była data treningu.

Doszła kolumna `training_sessions.paid_at`, wypełniana przez `MarkAsPaid` i `LogSession`.
Konsekwencja: **sesje rozliczone przed tym wdrożeniem mają `paid_at = null`** i wpadają do
miesiąca po dacie sesji. To przybliżenie dotyczy wyłącznie historii — każda płatność odznaczona od
teraz niesie prawdziwy moment. Liczby za miesiące sprzed wdrożenia czytaj z tą poprawką.

### `calendar_aliases` wymagało kolumny i generatora

Pola nie było w CRM w żadnej postaci (`client_tags` to tagi UI, nie aliasy). Doszła kolumna i
`Agent\CalendarAliases` — szczegóły w §4. Do czasu wpisania aliasów ręcznych działa generowanie z
nazwiska, więc kryterium „każdy klient ma co najmniej jeden alias" jest spełnione od pierwszego
wywołania.

---

## 9. Decyzja architektoniczna: dlaczego API w projekcie „bez API"

`START-TUTAJ.md` §3 odrzucał Sanctuma i API z uzasadnieniem, które **nadal jest słuszne dla
panelu**: przy trzech użytkownikach osobne API + SPA to warstwa bez zysku.

To API nie jest tą warstwą. Nie obsługuje panelu, nie ma tu zapisu i nie ma frontu, który by z
niego korzystał. Jest jednym kanałem odczytu na zewnątrz, dla jednego konsumenta, w jednym
kierunku. Panel dalej działa na Livewire i sesji, bez Sanctuma.

**Ryzyko do świadomego przyjęcia:** token daje listę klientów studia z kwotami. Wyciek tokenu to
wyciek tej listy. Stąd read-only, wąski zakres danych (§3), limit 60/min, wygasanie i `revoked_at`.
Przy agencie LLM dochodzi to, że dane przechodzą przez infrastrukturę dostawcy — do rejestru
czynności przetwarzania trafia ten kanał tak samo jak SMSAPI i Gmail.


---

## 10. `POST /api/agent/v1/payments` — jedyny zapis

SC-66. Osobny zakres `crm.write`, osobny token.

```json
{ "client_id": 12, "marked_at": "2026-09-18T14:32:11+02:00" }
```

Odpowiedź:

```json
{
  "client_id": 12,
  "settled_minor": 24000,
  "settled": "240 zł",
  "balance_minor": 0,
  "marked_at": "2026-09-18T14:32:11+02:00"
}
```

### Czego to NIE robi

**Agent nie wykrywa płatności i nigdy nie będzie.** Nic w CRM nie widzi, że ktoś zapłacił: BLIK
idzie z telefonu na telefon, gotówka nie zostawia śladu, bramki płatniczej nie ma
(`START-TUTAJ.md` §3). Zapisanie w karcie „płaci BLIKiem" tego nie zmienia — to preferencja,
nie zdarzenie.

**Źródłem prawdy jesteś Ty.** Ten endpoint przenosi Twoje kliknięcie z tablicy do CRM i nic poza
tym.

### `marked_at` — po co i dlaczego to wystarcza za idempotencję

To moment, w którym nacisnąłeś przycisk. CRM rozlicza **tylko sesje wbite przed tą chwilą**.

Agent chodzi cyklicznie, więc to samo kliknięcie dotrze nieraz dwa razy. Bez znacznika drugie
wysłanie zapłaciłoby też sesję wbitą w międzyczasie — za którą nikt jeszcze nie zapłacił.
Ze znacznikiem powtórka nie ma czego rozliczyć i zwraca `settled_minor: 0`.

`0` **nie jest błędem**: znaczy „ta prośba nie miała już czego rozliczyć".

Znacznik z przyszłości → `422`. Bez tego dałoby się rozliczyć wszystko, co dopiero przyjdzie.

### Reszta zachowania

- Rozlicza **wszystko, co klient był winien** w tamtej chwili — tak jak przy drzwiach, gdzie
  klient oddaje to, co się należy, a nie jedną sesję.
- Przedpłata zostaje tam, gdzie była: `PrepaymentPool` policzył ją wcześniej i ta część nie
  liczy się drugi raz.
- `paid_at` dostaje moment rozliczenia, więc miesięczne wpływy w §5 widzą tę płatność
  w miesiącu, w którym pieniądze przyszły.
- W `activity_entries` ląduje **nazwa tokenu**, nie „System": `Pulpit Maćka (agent)`. Pytanie
  „kto to tu wsadził" ma mieć odpowiedź.

### Token

```bash
php artisan agent:token "Pulpit Maćka — zapis" --scope=crm.write --days=365
```

**To musi być inny token niż ten do odczytu.** Token `crm.read` dostaje na tej trasie `403`
i tak ma zostać: jego wyciek nadal tylko ujawnia kartotekę, a token zapisu odwołasz osobno,
nie ruszając Pulpitu.

Limit: 30 wywołań na minutę — płatność to świadome kliknięcie, nie odpytywanie.
