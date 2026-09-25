# Claude connector — CRM w aplikacji Claude na telefonie

Wersja 1.0 · SC-68 · etap 1: odczyt

Właściciel studia pyta Claude'a zwykłym zdaniem — „kto zalega?”, „ile zarobiliśmy we wrześniu?”,
„daj raport dla księgowej” — a Claude odpowiada z danych CRM. Działa w aplikacji Claude na
telefonie, na claude.ai i w Claude Desktop, także w jednej rozmowie z Google Calendar.

---

## 1. Łańcuch wywołania

```
telefon (aplikacja Claude)  →  chmura Anthropic  →  POST https://<CRM>/mcp  →  zapytania CRM
```

Telefon nie łączy się z CRM sam. Wywołania przychodzą z chmury Anthropic (adresy
`160.79.104.0/21`), protokołem MCP (Streamable HTTP), z tokenem OAuth w nagłówku.

Kod: `routes/ai.php` (trasy), `app/Mcp/Servers/StudioServer.php` (serwer i instrukcje dla
Claude'a), `app/Mcp/Tools/*` (narzędzia), pakiety `laravel/mcp` i `laravel/passport`.

---

## 2. Logowanie — OAuth przez Passport

Claude nie dostaje hasła ani wklejanego tokenu. Przy dodawaniu connectora:

1. claude.ai pyta `/mcp` bez tokenu, dostaje `401` z adresem dokumentu
   `/.well-known/oauth-protected-resource/mcp`, a z niego `/.well-known/oauth-authorization-server`.
2. Rejestruje się jako klient OAuth (`POST /oauth/register`, RFC 7591). Rejestracja jest otwarta —
   tak działa claude.ai — więc ma limit 20/min, a adres powrotu może wskazywać **tylko**
   `https://claude.ai` (`config/mcp.php`). Obca aplikacja nie odbierze kodu.
3. Otwiera w przeglądarce `/oauth/authorize`: logowanie do CRM (jeśli trzeba), potem ekran zgody
   `resources/views/auth/connect-claude.blade.php` — „Połączyć?”.
4. Po „Połącz z Claude →” wymienia kod na token (PKCE S256).

| co | ile |
|---|---|
| token dostępu | 1 godzina — Claude odświeża go sam |
| token odświeżania | 30 dni, rotowany — telefon nieużywany przez miesiąc przechodzi przez zgodę od nowa |
| zakres | jeden: `mcp:use`, nadawany każdemu tokenowi |
| limit wywołań | 60 na minutę na konto |

### Tylko właściciel

Ekran zgody pokazuje trenerowi „Nie tym kontem.” bez przycisku. To uprzejmość, nie zabezpieczenie:
**zamek jest na `/mcp`** (`EnsureConnectorOwner`) — przy każdym wywołaniu token musi należeć do
aktywnego konta właściciela. Zablokowanie konta odcina Claude'a przy następnym wywołaniu.

Sesja panelu (ciasteczko) nie otwiera `/mcp` — tylko token Bearer.

### CRM nie ma 2FA

Dostęp Claude'a chroni hasło właściciela. Mocne i nieużywane nigdzie indziej.

### Odłączenie

```bash
php artisan samtrening:odlacz-claude
```

Unieważnia wszystkie tokeny naraz; następne wywołanie z claude.ai dostaje `401`. **Usunięcie
connectora na claude.ai tego nie robi** — tam zapominają token, u nas on dalej żyje do wygaśnięcia.

Wygasłe i unieważnione tokeny czyści `passport:purge` co noc o 3:15.

---

## 3. Narzędzia (etap 1 — tylko odczyt)

Każde narzędzie woła zapytanie, którego używa panel, więc liczba w rozmowie i liczba na ekranie
pochodzą z tego samego kodu. Wszystkie mają `readOnlyHint`.

| narzędzie | co zwraca | skąd |
|---|---|---|
| `find_clients` | klienci po imieniu, nazwisku albo aliasie z kalendarza (bez polskich znaków też), z trenerem, stawką i saldem | `Agent\Queries\ClientList` |
| `get_client` | karta: trener, stawka, telefon, e-mail, płatnik faktury, zaległość i od kiedy, niewykorzystana wpłata z góry, 10 ostatnich sesji | `Agent\Queries\ClientCard` |
| `list_outstanding` | zaległości studia na dziś, od największej, z sumą | `Billing\Queries\Outstanding` |
| `get_month_summary` | miesiąc: sesje, należne, wpłacone, zaległości, rozbicie na klientów | `Agent\Queries\MonthSummary` (§5 AGENT-API.md) |
| `get_earnings` | zarobki za miesiąc albo rok, per trener i całe studio | `Billing\Earnings` |
| `get_session_report` | link do CSV dla księgowej, ważny 15 minut | `Billing\Export\SessionCsvExport` |

Kwoty: tekstem („1 250 zł”) i w groszach (`*_minor`). Znaki sald opisują instrukcje serwera —
`find_clients` bierze saldo z API Pulpitu (ujemne = zaległość), karta i zaległości mówią `owed`
(dodatnie = klient jest winien).

### Co nie wychodzi

Przeciwwskazania, notatki trenera i notatki sesji, cel, stan wyjściowy, plan na następny raz,
dane opiekuna. Pola są wypisane ręcznie (`ClientCard`, `ClientList`), żaden model nie jest
serializowany w całości — pole dodane do karty później nie wyjdzie samo. Test
`get_client gives the card without health data, notes or the guardian` sprawdza treść odpowiedzi.

Telefon, e-mail i płatnik faktury **wychodzą** — o numer do klienta pyta się najczęściej.

Wszystko, o co zapytasz, przechodzi przez infrastrukturę Anthropic. Do rejestru czynności
przetwarzania trafia ten kanał tak samo jak SMSAPI i Gmail (SC-50).

### Raport CSV

Claude nie ma jak dać pliku na telefon, więc daje link: `GET /raport/sesje?period=2026-09&by=<id>`
podpisany na 15 minut. Plik powstaje przy otwarciu (nic nie leży na dysku), eksport ląduje w logu
aktywności pod nazwiskiem właściciela, jak po kliknięciu w panelu. Link przestaje działać po
czasie, po zmianie parametru albo gdy konto `by` nie jest już aktywnym właścicielem.

---

## 4. Wdrożenie

Jednorazowo na serwerze (Forge → Commands), **przed** pierwszym połączeniem:

```bash
php artisan passport:keys
```

Tworzy `storage/oauth-private.key` i `storage/oauth-public.key` (w `.gitignore`). Bez nich
logowanie Claude'a kończy się błędem 500. Klucze zostają między wdrożeniami. Zamiast plików
można podać `PASSPORT_PRIVATE_KEY` i `PASSPORT_PUBLIC_KEY` w zmiennych środowiskowych.

Migracje (`oauth_*`) przechodzą zwykłym `migrate --force` ze skryptu wdrożenia.

Nginx z Forge przepuszcza `/.well-known/*` do Laravela (blokuje tylko inne ścieżki z kropką).
Po wdrożeniu:

```bash
BASE='https://samtrening-crm-hdvivquh.on-forge.com'
curl -s -o /dev/null -w '%{http_code}\n' -X POST "$BASE/mcp"               # 401
curl -s "$BASE/.well-known/oauth-protected-resource/mcp"                  # JSON z "resource"
curl -s "$BASE/.well-known/oauth-authorization-server" | jq .token_endpoint
```

## 5. Podłączenie w Claude

1. claude.ai (w przeglądarce albo Claude Desktop) → **Customize → Connectors → Add custom connector**.
2. Nazwa: `SAMTRENING CRM`, adres: `https://<CRM>/mcp`. Pola „Advanced settings” zostaw puste.
3. „Connect” → logowanie do CRM → „Połącz z Claude →”.
4. W aplikacji na telefonie connector pojawia się sam; włączasz go w rozmowie przez „+” → Connectors.

Na telefonie nie da się connectora **dodać** — tylko używać dodanego na claude.ai.

---

## 6. Poza zakresem etapu 1

Zapis (nowy klient, zmiana stawki, rozliczenie zaległości, wpłata z góry) to etap 2 — SC-70.
Trenerzy, każdy do swoich klientów — decyzja właściciela na później.
