# Wdrożenie — SAMtrening CRM

Stack ustalony w SC-9: **Laravel Forge + MySQL 8**. Ten plik opisuje, co zrobić raz przy
stawianiu serwera i co dzieje się przy każdym wdrożeniu. Rzeczy wymagające Twoich kont i
podpisów są wypisane osobno na końcu — bez nich aplikacja działa, ale **nie wyśle ani jednej
wiadomości**.

---

## 1. Serwer

Forge, PHP **8.3+**, MySQL 8, HTTPS z Let's Encrypt (w Forge jeden przycisk). Strefa czasowa
serwera dowolna — aplikacja liczy wszystko w `Europe/Warsaw` sama, bo połączenie z bazą pracuje
w strefie serwera i `NOW()` odpowiedziałoby na inne pytanie.

**Repozytorium:** gałąź `main`. Wdrażamy tylko to, co jest na `main`.

**Poczta wychodzi przez Gmail API, nie przez SMTP.** DigitalOcean blokuje wychodzące porty
25, 465 i 587 na wszystkich dropletach, więc `smtp-relay.gmail.com` jest z tego serwera
nieosiągalny — żądanie kończy się timeoutem, nie błędem uwierzytelnienia, co myli przy
diagnozie. Rozmawiamy z Gmailem po HTTPS (`app/Mail/Gmail`), a uwierzytelniamy się tokenem
odświeżania jednej skrzynki Workspace. Świadomie bez konta usługowego i bez delegacji
domenowej: token upoważnia do wysyłki wyłącznie jako ta jedna skrzynka, a nie jako dowolny
użytkownik domeny. Poza tym Google domyślnie blokuje tworzenie kluczy kont usługowych
(`iam.disableServiceAccountKeyCreation`) i tej zasady nie warto wyłączać.

Token zdobywa się raz, po wdrożeniu, w dwóch przebiegach — komenda o nic nie pyta, bo
runner komend w Forge nie ma interaktywnego terminala:

```
php artisan gmail:authorize                 # wypisze adres zgody
php artisan gmail:authorize --code=KOD      # wymieni kod na token
```

Drugi przebieg zwróci `GMAIL_REFRESH_TOKEN` do wklejenia w Forge. Kod z paska adresu jest
jednorazowy i ważny kilka minut. Wymiana kodu dzieje się po stronie serwera, żeby `GMAIL_CLIENT_SECRET`
nie przewinął się przez historię przeglądarki ani przez log komend.

W DNS domeny muszą stać SPF z `include:_spf.google.com` i klucz DKIM z konsoli Workspace
(Gmail → Uwierzytelnianie poczty e-mail). Bez nich Gmail i Outlook wrzucą wiadomości do spamu.

## 2. Zmienne środowiskowe

Skopiuj `.env.production.example` do panelu Forge (Site → Environment) i uzupełnij wszystko
oznaczone `→ WPISZ`. Trzy rzeczy, na których łatwo się przewrócić:

- **`APP_KEY`** — bez niego zaszyfrowane pola (przeciwwskazania, notatki z sesji) są nie do
  odczytania. Kopia klucza leży **osobno od kopii bazy**, inaczej jedno włamanie daje oba (SC-52).
- **`APP_DEBUG=false`** — przy `true` strona błędu pokazuje zawartość zmiennych, czyli także dane
  klientów.
- **`FILESYSTEM_DISK=local`** — plany klientów to dane o zdrowiu. Dysk publiczny nie jest opcją.

## 3. Kolejka i scheduler

**Worker kolejki** (Forge → Queue):

```
Connection: database
Queue: default
Processes: 1
Timeout: 60
Sleep: 3
Tries: 3
```

Forge trzyma go pod nadzorem, więc po restarcie serwera wstaje sam. Kolejka niesie wysyłkę SMS-ów
i e-maili — **nic, co blokuje zapis sesji**.

**Scheduler** (Forge → Scheduler), co minutę:

```
php /home/forge/crm.samtrening.com/artisan schedule:run
```

Zarejestrowane zadania (`routes/console.php`):

| Zadanie | Kiedy | Co robi |
| --- | --- | --- |
| `samtrening:monity` | codziennie 10:00 | SMS do klientów po terminie, tylko gdy `reminders_enabled`; najwyżej jeden monit na klienta na 7 dni |
| `samtrening:retencja` | 1. dnia miesiąca, 3:30 | archiwalne kartoteki starsze niż retencja → anonimizacja, **nie DELETE** |
| `passport:purge` | codziennie 3:15 | wygasłe i unieważnione tokeny connectora Claude'a, tydzień po wygaśnięciu |

Godzina monitów jest celowa: rano ktoś jeszcze odbierze telefon, jeśli klient oddzwoni.

## 4. Wdrożenie

Dwie wersje tego samego. [`deploy.sh`](../deploy.sh) uruchamia się ręcznie na serwerze; poniżej
wariant do wklejenia w **Forge → Site → Apps → Deploy Script**, bo Forge sam robi `git pull`
i podstawia własne zmienne (`$FORGE_PHP`, `$FORGE_COMPOSER`, `$FORGE_PHP_FPM`):

```bash
cd /home/forge/crm.samtrening.com

$FORGE_PHP artisan down --render="errors::503" --retry=15 || true

git pull origin $FORGE_SITE_BRANCH

$FORGE_COMPOSER install --no-interaction --prefer-dist --optimize-autoloader --no-dev

npm ci
npm run build

$FORGE_PHP artisan migrate --force

$FORGE_PHP artisan config:cache
$FORGE_PHP artisan route:cache
$FORGE_PHP artisan view:cache
$FORGE_PHP artisan event:cache

( flock -w 10 9 || exit 1
    echo 'Restarting FPM...'; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock

$FORGE_PHP artisan queue:restart

$FORGE_PHP artisan up
```

Kolejność nie jest przypadkowa: migracje **przed** przebudową cache'u, restart workera **na
końcu** — inaczej stary worker wykonuje zadania starym kodem na nowej bazie.

**Krok `npm run build` nie jest opcjonalny.** `public/build` nie jest w repozytorium, a layouty
wołają `@vite` — bez niego każda strona kończy się błędem o brakującym manifeście.

## 5. Pierwsze uruchomienie

```bash
php artisan migrate --force
php artisan db:seed --force
```

`DatabaseSeeder` zakłada konto właściciela (z `.env`), zasady studia i szablony wiadomości —
i **nic poza tym**: żadnych danych demo. Każdy z tych seedów pomija to, co już istnieje, więc
powtórne uruchomienie niczego nie nadpisze.

Potem **usuń `OWNER_PASSWORD` z Forge** — konto już istnieje, a hasło nie ma po co tam leżeć.
Trenerów zapraszasz z panelu (`/admin/trenerzy`); żadne konto nie powstaje z pliku.

## 6. Kontrola po wdrożeniu

```bash
php artisan about --only=environment   # APP_ENV=production, APP_DEBUG=false
php artisan schedule:list              # trzy zadania, z godzinami
php artisan queue:monitor default      # kolejka nie rośnie
```

I jedna rzecz, której nie sprawdzi żaden skrypt: **zaloguj się i wbij sesję**. Reszta listy
gotowości chodzi jako test — `php artisan test --filter=DefinitionOfDone` (SC-53).

## 7. Czego brakuje do pełnej produkcji

Aplikacja postawi się i będzie działać bez tych rzeczy, ale **nie wyśle wiadomości**:

| Co | Gdzie | Bez tego |
| --- | --- | --- |
| Token SMSAPI (`SMS_API_TOKEN`) i zatwierdzona nazwa nadawcy | **SC-16** | bez nich wysyłka rzuca wyjątkiem i monit nie wychodzi; przy `SMS_PROVIDER=log` trafia wyłącznie do logu |
| Token OAuth skrzynki `maciej.samborski@samtrening.com` (`gmail:authorize`) | **SC-17** | bez `GMAIL_REFRESH_TOKEN` wysyłka rzuca wyjątkiem i nie wychodzi ani jeden e-mail |
| Kopie zapasowe z próbą odtworzenia | **SC-52** | dane o zdrowiu i pieniądzach bez kopii |
| Treść zgody RODO i klauzula informacyjna | **SC-49** | zbieramy zgody, nie mając czego pokazać |
| Umowy powierzenia i rejestr czynności | **SC-50** | otwarty dług RODO, widoczny w Ustawieniach |
| Token kalendarza (`php artisan calendar:authorize`) | **KALENDARZ.md** | ekran „Z kalendarza" mówi „Kalendarz niepodłączony" i nie woła nigdzie; sesje wbijasz ręcznie, jak dotąd. **`GMAIL_REFRESH_TOKEN` zostaw bez zmian** — to osobna zgoda i osobny token |
| Klucze OAuth (`php artisan passport:keys`, raz) | **CLAUDE-CONNECTOR.md §4** | podłączenie Claude'a kończy się błędem 500; reszta CRM działa bez zmian |
| Token zapisu dla Pulpitu (`php artisan agent:token "Pulpit Maćka — zapis" --scope=crm.write`) | **AGENT-API.md §10** | bez niego odznaczenie płatności na tablicy nie dojdzie do CRM (403). **Musi być osobny od tokenu odczytu** — ten do czytania celowo nie umie ruszyć pieniędzy |
| Token agenta (`php artisan agent:token "Pulpit Maćka" --scope=crm.read`) | **AGENT-API.md** | Pulpit Maćka dostaje 401 i nie widzi ani cen, ani płatności. Token wypisuje się **raz** — wklej go od razu do konfiguracji agenta. Kanał idzie do rejestru czynności przetwarzania jak SMSAPI i Gmail |

Panel **Dług RODO** w Ustawieniach wypisuje to samo, żeby patrzyło Ci w oczy przy każdym wejściu
na ten ekran — i żeby dało się je odhaczyć, gdy przestanie być długiem.
