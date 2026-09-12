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

Godzina monitów jest celowa: rano ktoś jeszcze odbierze telefon, jeśli klient oddzwoni.

## 4. Wdrożenie

Skrypt: [`deploy.sh`](../deploy.sh) — ta sama treść, którą wkleja się w Forge (Site → Apps →
Deploy Script). Kolejność w nim nie jest przypadkowa: migracje przed przebudową cache'u, restart
workera na końcu — inaczej stary worker wykonuje zadania starym kodem.

Po wdrożeniu skrypt sam wypisuje środowisko i listę zadań cyklicznych. Jeśli któreś się nie
pokaże, wdrożenie było nieudane.

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
php artisan schedule:list              # dwa zadania, z godzinami
php artisan queue:monitor default      # kolejka nie rośnie
```

I jedna rzecz, której nie sprawdzi żaden skrypt: **zaloguj się i wbij sesję**. Reszta listy
gotowości chodzi jako test — `php artisan test --filter=DefinitionOfDone` (SC-53).

## 7. Czego brakuje do pełnej produkcji

Aplikacja postawi się i będzie działać bez tych rzeczy, ale **nie wyśle wiadomości**:

| Co | Gdzie | Bez tego |
| --- | --- | --- |
| Dostawca SMS + zatwierdzona nazwa nadawcy | **SC-16** | `SMS_PROVIDER=log` — SMS-y tylko do logu, nikt ich nie dostaje |
| Skrzynka `noreply@samtrening.com`, SPF/DKIM/DMARC | **SC-17** | e-maile lądują w spamie albo nie wychodzą wcale |
| Kopie zapasowe z próbą odtworzenia | **SC-52** | dane o zdrowiu i pieniądzach bez kopii |
| Treść zgody RODO i klauzula informacyjna | **SC-49** | zbieramy zgody, nie mając czego pokazać |
| Umowy powierzenia i rejestr czynności | **SC-50** | otwarty dług RODO, widoczny w Ustawieniach |

Panel **Dług RODO** w Ustawieniach wypisuje to samo, żeby patrzyło Ci w oczy przy każdym wejściu
na ten ekran — i żeby dało się je odhaczyć, gdy przestanie być długiem.
