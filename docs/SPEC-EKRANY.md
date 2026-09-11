# Handoff: SAMtrening CRM

> **Zacznij od `START-TUTAJ.md`** — tam jest stack, architektura modułowa, kolejność budowy, schemat bazy i granice zakresu.
> Ten plik jest szczegółową specyfikacją wizualną i behawioralną ekranów. Powstawał w trakcie projektowania,
> więc część zapisów o płatnościach i fakturach jest nieaktualna — listę rozbieżności zawiera §13 w `START-TUTAJ.md`.
>
> **Nazwy pól w §Data Model poniżej są z okresu projektowania i polskie.** Kanoniczne, angielskie
> identyfikatory (tabele, kolumny, klasy) są w `START-TUTAJ.md` §5 wraz z mapowaniem. Polski zostaje
> wyłącznie w treści widzianej przez użytkownika — a tej ten plik jest źródłem prawdy.

## Overview

CRM dla kameralnego studia treningu personalnego SAMtrening (Plac Na Groblach 23, Kraków; 3 trenerów, formuła 1:1, studio otwarte 5:30–23:00 siedem dni w tygodniu).

Model biznesowy studia determinuje całą architekturę i trzeba go zrozumieć przed implementacją:

- **Brak pakietów i abonamentów.** Klient płaci za odbyte sesje. Należność powstaje w momencie, w którym trener wbije zrealizowaną usługę — nigdy wcześniej.
- **Brak kalendarza w systemie.** Grafik zostaje w Google Calendar. CRM rejestruje wyłącznie fakty po treningu. To była świadoma decyzja właściciela, nie brak funkcji — nie dodawaj modułu rezerwacji.
- **100% stawki idzie do trenera.** Studio nie pobiera prowizji, nie ma rozliczeń studio–trener. Trener widzi tylko, ile zarobił w danym miesiącu.
- **Stawkę ustala trener,** indywidualnie dla każdego klienta. Kwotę każdej pojedynczej sesji można nadpisać (np. sesja skrócona do 45 min).
- **Jedno konto administratora** — właściciel (Maciej Samborski), który jest jednocześnie trenerem i przełącza się między dwoma widokami.
- **Klient nie ma konta.** Nie loguje się nigdzie. Dostaje wyłącznie linki: do płatności i do plików.

## About the Design Files

Plik `SAMtrening CRM.dc.html` w tym pakiecie jest **referencją projektową wykonaną w HTML** — prototypem pokazującym docelowy wygląd i zachowanie, a nie kodem produkcyjnym do skopiowania. Cała logika działa na stanie w pamięci przeglądarki i danych demonstracyjnych.

Zadanie polega na **odtworzeniu tych ekranów w docelowym stacku**: Laravel 11 + Blade/Livewire 3 + Tailwind, w podziale na moduły domenowe — `START-TUTAJ.md` §2 i §4.

Prototyp nie zawiera i wymaga dopisania po stronie implementacji: prawdziwej autentykacji, bazy danych, bramki SMS, poczty transakcyjnej i uploadu plików.

## Fidelity

**High-fidelity.** Kolory, typografia, odstępy, stany interakcji i cała treść (polskojęzyczna) są finalne. Paleta została wyciągnięta z pikseli produkcyjnej strony samtrening.com — odtwarzaj ją dokładnie.

Jedyny element o niższej dojrzałości: **widok mobilny**. Layout jest płynny (grid `auto-fit`, `flex-wrap`) i poprawnie zwęża się do ~390 px we wszystkim poza gęstymi tabelami, które na telefonie wymagają przeprojektowania na listę kart. Patrz sekcja „Responsive behavior".

---

## Design Tokens

### Kolory

| Token | Hex | Zastosowanie |
| --- | --- | --- |
| `bg` | `#0a0909` | Tło całej aplikacji |
| `surface` | `#151414` | Karty, pola formularzy, panele podniesione |
| `text` | `#fafaf7` | Tekst podstawowy |
| `text-muted` | `#babab8` | Tekst pomocniczy, opisy |
| `accent` | `#e8ff3e` | Limonka — akcje główne, akcenty, pasek górny |
| `divider` | `rgba(250,250,247,0.22)` | Linie i obramowania |

Rampa neutralna (100 → 900): `#151414`, `#1d1c1c`, `#2a2929`, `#3d3b3b`, `#6e6d6c`, `#9d9c9b`, `#babab8`, `#dedcd8`, `#fafaf7`

Rampa akcentu — **odwrócona względem klasycznej**, bo tło jest ciemne (100 → 900): `#23290a`, `#303b0c`, `#47580f`, `#eeff6b`, `#e8ff3e`, `#d2e832`, `#e8ff3e`, `#eeff7a`, `#f6ffb4`

Zasada: kroki 100–300 to ciemne, limonkowo podbarwione wypełnienia paneli; 500 to akcent bazowy; 700–900 to jasne limonki używane jako **tekst** na tych ciemnych panelach. Nie odwracaj tego z powrotem — na czarnym tle jasny tekst musi być w wysokich krokach.

Hover akcji głównej: `#d2e832`. Focus klawiaturowy: `outline: 2px solid #e8ff3e; outline-offset: 2px` — nigdy domyślny niebieski.

### Typografia

- **Display** (`h1`, wielkie nagłówki ekranów): **Anton**, waga 400, `text-transform: uppercase`, `line-height: 1.14`, `letter-spacing: 0.005em`. Rozmiary `clamp(30px, 4–5vw, 42–54px)`.
  - `line-height: 1.14` jest krytyczne — Anton przy ciaśniejszej interlinii obcina polskie znaki diakrytyczne (Ś, Ć, Ę, ogonki). Nie zmniejszaj.
  - Jeśli studio ma licencję na font display ze strony (możliwe Druk lub Monument Extended), Anton jest zamiennikiem — podmień na licencjonowany.
- **Heading** (`h2`–`h6`, etykiety UI, liczby, nazwiska w tabelach, etykiety przycisków): **Archivo**, waga 800, `letter-spacing: -0.015em`.
- **Body**: **Archivo**, waga 400, `font-size: 15px`, `line-height: 1.55`.
- Małe etykiety sekcji: 10–11 px, `letter-spacing: 0.14–0.16em`, `text-transform: uppercase`, kolor akcentu lub `opacity: 0.55`.

Skala nagłówków: h1 42px, h2 32px, h3 25px, h4 20px, h5 16px, h6 13px.

### Odstępy, promienie, cienie

- Skala odstępów: 4, 8, 12, 16, 24, 32 px.
- **Promień zaokrąglenia: 0 px wszędzie.** Nic nie jest zaokrąglone — to reguła marki, nie przeoczenie.
- Cienie (tylko dla modali i kart unoszonych): `sm` `0 1px 2px rgba(0,0,0,.6)`, `md` `0 3px 12px rgba(0,0,0,.66)`, `lg` `0 16px 44px rgba(0,0,0,.78)`.
- Linie: **2 px** między sekcjami głównymi (`divider`), **1 px** między wierszami list i tabel.
- Komórki siatek statystyk mają `border-left: 1px solid divider`, a cały pasek `border-top`/`border-bottom: 2px`.

### Reguły layoutu przeniesione ze strony

- Wszystko wyrównane **do lewej** — nagłówki, treść, a także etykiety wewnątrz szerokich przycisków (`justify-content: flex-start`).
- Siatka jest widoczna: równe komórki, mocne poziome linie, brak „pływających" elementów.
- Akcent stosowany oszczędnie: akcja główna, mała emfaza, jeden pełnoekranowy panel na widok (kwota zarobku, suma zaległości).
- Pasek górny aplikacji jest **limonkowy z czarnym tekstem** — dokładnie jak pasek kontaktowy na samtrening.com.

---

## Roles & Permissions

| | Trener (Kasia, Bartek) | Właściciel (Maciek) |
| --- | --- | --- |
| Panel trenera | tak, tylko swoi klienci | tak, tylko swoi klienci |
| Przełącznik Trener/Admin | **nie widzi go wcale** | tak |
| Panel admina | nie | tak |
| Kartoteka całego studia | nie | tak, z notatkami i danymi zdrowotnymi |
| Zakładanie i blokowanie kont | nie | tak |
| Reset hasła innego trenera | nie | tak |
| Log zmian | nie | tak |
| Możliwość zablokowania | tak (przez właściciela) | **nie** — konto właściciela jest nieblokowalne |

Właściciel w widoku trenera jest funkcjonalnie nieodróżnialny od pozostałych trenerów. Uprawnienie admina to osobny widok, nie rozszerzenie panelu trenera.

Decyzja właściciela: **admin widzi wszystko**, w tym prywatne notatki trenerów i dane o zdrowiu klientów innych trenerów.

---

## Data Model

```
Trener
  id, name, email, spec (specjalizacja, tekst)
  status: 'aktywny' | 'zaproszony' | 'zablokowany'
  wlasciciel: boolean            // dokładnie jeden rekord true

Klient
  id, name, tel, email
  trenerId                       // właściciel kartoteki
  stawka: int                    // zł za sesję, ustalana przez trenera, krok 5 zł
  tagi: [{ label, wariant }]     // wariant: accent | accent-2 | neutral | outline
  cel: text                      // cel i kontekst
  start: text                    // punkt startowy
  kontuzje: text                 // kontuzje i przeciwwskazania — DANE ZDROWOTNE
  rodo: text                     // status zgody; brak zgody → prefiks "BRAK ZGODY"
  opiekun: text | null           // wymagany dla osób < 18 lat
  notatki: text                  // notatka trenera
  archiwalny: boolean
  sesje: [Sesja]
  pliki: [Plik]

Sesja
  data: date
  usluga: string
  cena: int                      // nadpisywalna niezależnie od stawki klienta
  typ: 'odbyta' | 'odwolana' | 'nieobecnosc'
  status: 'zaplacone' | 'saldo' | 'link' | 'nieplatne'
  notatka: text

Plik
  nazwa, ext, meta               // plan treningowy, dokumenty od klienta

WpisLogu
  kiedy: datetime, kto: string, akcja: string, cel: string

Ustawienia (globalne)
  smsDostawca: string, monitAktywny: bool
  progMonitu: int (dni), bezplatneOdwolanie: int (godziny), retencja: int (miesiące)

SzablonyWiadomosci
  link, monit, potwierdzenie, plik,
  podsumowanieTemat, podsumowanieBody
```

### Reguły wyliczeniowe

Te wzory są sercem systemu — zaimplementuj je dokładnie:

```
doZaplaty(sesja)  = sesja.status ∉ { 'zaplacone', 'nieplatne' }
saldo(klient)     = Σ sesja.cena  dla sesji spełniających doZaplaty
odbyta(sesja)     = sesja.typ ∈ { 'odbyta', null }

zarobek(trener, miesiąc)       = Σ sesja.cena  po wszystkich sesjach trenera w miesiącu
                                 (odwołania naliczone WCHODZĄ do zarobku)
liczbaOdbytych(trener, mies.)  = |{ sesje w miesiącu : odbyta }|
                                 (odwołania i nieobecności NIE liczą się jako sesje)
poTerminie(należność)          = dni(najstarsza nierozliczona sesja) > ustawienia.progMonitu
```

Klienci archiwalni: wypadają ze statystyk, list i zaległości, ale **ich przeszłe sesje nadal liczą się do zarobków** trenera. Nie filtruj ich z agregacji finansowych.

### Reguły biznesowe

1. **Archiwizacja jest zablokowana, dopóki saldo > 0.** Inaczej dług znika z widoku. Przycisk nieaktywny + komunikat „Najpierw rozlicz saldo".
2. **Ostrzeżenie o duplikacie.** Przy wybraniu klienta i daty, na którą istnieje już wpis, formularz pokazuje ostrzeżenie, a etykieta przycisku zmienia się na „Zapisz mimo to". Zapis nie jest blokowany — dwie sesje w jednym dniu się zdarzają.
3. **Odwołania.** `typ ≠ 'odbyta'` zmienia opcje rozliczenia na: „Nie naliczam" (`status: 'nieplatne'`, cena 0), „Naliczam — na saldo", „Naliczam — zapłacone". Domyślnie nie naliczamy, zgodnie z obietnicą darmowego odwołania na `bezplatneOdwolanie` godzin przed sesją (domyślnie 24 h, ustawialne).
5. **Usunięcie danych na żądanie (RODO).** Kasuje kontakt, cel, przeciwwskazania, notatki, opiekuna i pliki; nazwisko podmienia na `Dane usunięte #XXXX`; **historia sesji zostaje z zanonimizowanymi notatkami**, bo kwoty muszą zgadzać się z rozliczeniami podatkowymi. Ustawia `archiwalny: true`. Nieodwracalne. Zawsze do logu.
6. **Log zmian jest obowiązkowy** przy: wbiciu sesji, edycji kwoty sesji, zmianie stawki, dodaniu i edycji klienta, wysłaniu linku, odznaczeniu gotówki, wystawieniu faktury, archiwizacji, usunięciu danych, zaproszeniu i blokadzie trenera, zmianie ustawień, resecie hasła, aktywacji konta. Przy ręcznie ustalanych stawkach i nadpisywalnych kwotach bez logu nie da się rozstrzygnąć sporu „ja tego nie zmieniałem".

---

## Screens / Views

Aplikacja ma trzy warstwy: ekrany dostępu (bez nawigacji), panel trenera (7 zakładek) i panel admina (6 zakładek). Przełącznik roli siedzi w limonkowym pasku górnym i widzi go tylko właściciel.

### Chrome (wspólne dla warstwy aplikacji)

**Pasek górny** — `background: #e8ff3e`, `color: #0a0909`, `padding: 7px 16px`, `font-size: 11px`, `letter-spacing: .06em`, uppercase, `display:flex` z `gap:16px` i `flex-wrap`. Zawartość od lewej: „STUDIO OTWARTE" (waga 800), adres i godziny (`opacity: .6`), a po `margin-left:auto` — „WIDOK" + przyciski roli + nazwisko + „WYLOGUJ". Aktywna rola ma pod spodem pasek `3px` w kolorze `#0a0909` (nie limonkowy — byłby niewidoczny).

**Nawigacja** — `display:flex`, `flex-wrap: wrap`, `padding: 12px 16px`, `border-bottom: 2px solid divider`, `position: sticky; top: 0`, tło `#0a0909`. Marka po lewej: „SAM·TRENING" (Archivo 800, 18px, kropka w akcencie) z podtytułem 9 px `letter-spacing:.22em` — **treść podtytułu zależy od roli**: „CRM · PANEL TRENERA" albo „CRM · PANEL ADMINA". Pozycje menu to przyciski bez tła, Archivo 800, 13 px, `padding: 10px 12px 12px`; aktywna ma `aria-current="page"`, kolor akcentu i limonkowy pasek `3px` u dołu. Po prawej akcja główna: „＋ Wbij sesję" (trener) albo „＋ Zaproś trenera" (admin).

**Ticker** — pasek pod nawigacją, `background: surface`, `border-bottom: 2px solid divider`, tekst 11 px `letter-spacing:.16em` uppercase `opacity:.62`, przewijany animacją `translateX(0 → -50%)` w 34 s liniowo, treść zduplikowana dwa razy dla ciągłości: „Trening personalny ● Zdrowa ciąża ● E-trening online ● Studio 1:1 ● Plac Na Groblach ● Płacisz za odbyte sesje ●". Wyłączalny.

**Kontener treści** — `max-width: 1240px`, `margin: 0 auto`, `padding: 32px 16px 80px`.

**Toast** — `position: fixed; left:16px; bottom:16px`, `background: accent`, `color: bg`, `padding: 14px 18px`, `box-shadow: lg`, kwadratowa kropka 8×8 w `bg` po lewej. Auto-znika po 3,8 s.

**Pasek statystyk** (powtarzany wzorzec) — `display:grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr))`, `border-top`/`border-bottom: 2px solid divider`, każda komórka `padding: 20px 18px; border-left: 1px solid divider`. W komórce: etykieta 10 px uppercase `letter-spacing:.14em` `opacity:.55`, liczba Archivo 800 `38px` `line-height:1` `letter-spacing:-.03em`, opis 12 px `opacity:.6`.

---

### Ekrany dostępu

#### 1. Logowanie

Dwie kolumny `repeat(auto-fit, minmax(320px, 1fr))`, `min-height: 100vh`.

Lewa: `background: surface`, `border-right: 2px solid divider`, `padding: 48px 40px`. Marka 26 px, kicker „CRM STUDIA" w `accent-400`, nagłówek display „WBIJASZ SESJĘ. RESZTA LICZY SIĘ SAMA.", akapit `opacity:.65`, na dole adres i godziny 11 px uppercase `opacity:.45`.

Prawa: formularz `max-width: 380px`. h1 „ZALOGUJ SIĘ.", podtytuł „Konto zakłada właściciel studia.", pola E-mail i Hasło, przycisk główny pełnej szerokości „Zaloguj się →", link „Nie pamiętam hasła", a pod nim kontakt do właściciela. Sekcja „PROTOTYP — WEJDŹ NA SKRÓTY" z przyciskami kont istnieje tylko w prototypie — **nie przenoś jej do produkcji**.

**Walidacja logowania** — komunikaty są różne, bo prowadzą do różnych działań:
- puste pole → „Podaj adres e-mail." / „Podaj hasło."
- nieznany adres → „Nie znamy tego adresu. Reset hasła tu nie pomoże — konto musi założyć właściciel studia."
- konto zablokowane → „Konto zablokowane. Reset hasła tego nie zmieni — odblokować może tylko właściciel studia."
- konto ze statusem `zaproszony` → **przekierowanie na ustawianie hasła**, nie komunikat błędu.

**W produkcji** hasło ustawia się wyłącznie z linku wysłanego na adres konta, więc zaproszony trafia na ekran 2 w trybie aktywacji („USTAW HASŁO.", przycisk „Wyślij link →"). Gdyby hasło dało się ustawić zaraz po wpisaniu samego adresu, konto przejąłby każdy, kto ten adres zna.

Błąd renderuje się jako panel `background: accent-100`, `border-left: 3px solid accent`, tekst `accent-700`, 12 px, waga 600.

#### 2. Reset hasła

Ten sam podział dwukolumnowy. Lewa kolumna nosi tezę: „WŁAŚCICIEL TEŻ O TYM ZAPOMINA" — nad kontem właściciela nie ma nikogo, kto by je odblokował, więc reset mailem musi działać dla każdego bez proszenia kogokolwiek.

Dwa stany prawej kolumny:
- **formularz** — pole e-mail, „Wyślij link →", powrót do logowania, kontakt do właściciela jako droga awaryjna;
- **potwierdzenie** — „SPRAWDŹ SKRZYNKĘ.", informacja o linku ważnym **60 minut, jednorazowym**, i jawne wyjaśnienie: nie mówimy, czy adres istnieje w systemie, bo inaczej dałoby się sprawdzać, kto w studiu pracuje. Zachowaj tę neutralność odpowiedzi w implementacji.

#### 3. Ustawianie hasła (zaproszenie / reset)

Jeden ekran, dwa tryby. Lewa kolumna: `background: accent`, `color: bg` — pełnoekranowy limonkowy panel.

| | tryb `zaproszenie` | tryb `reset` |
| --- | --- | --- |
| kicker | ZAPROSZENIE DO ZESPOŁU | NOWE HASŁO |
| nagłówek | WITAJ W STUDIO, {imię}. | WRACAMY DO GRY, {imię}. |
| ważność | Link ważny 7 dni | Link ważny 60 minut · jednorazowy |
| tytuł formularza | USTAW HASŁO. | NOWE HASŁO. |
| checkbox RODO | wymagany | ukryty |
| przycisk | Aktywuj konto → | Zapisz nowe hasło → |

Formularz: karta z danymi konta (`background: surface`, nazwisko, e-mail, specjalizacja), hasło, powtórz hasło, checkbox „Zobowiązuję się do ochrony danych klientów" z wyjaśnieniem, że kontuzje i ciąża to kategoria szczególna.

Walidacja: minimum 8 znaków → „Hasło musi mieć minimum 8 znaków."; niezgodność → „Hasła się nie zgadzają."; brak zgody (tylko tryb zaproszenia) → „Zaznacz zobowiązanie do ochrony danych klientów."

Po sukcesie: status trenera → `aktywny`, użytkownik zalogowany, wpis do logu, toast. W trybie resetu komunikat mówi wprost, że pozostałe urządzenia zostały wylogowane — zaimplementuj unieważnienie sesji.

---

### Panel trenera

#### 4. Pulpit

h1 „CZEŚĆ, {IMIĘ}." + druga linia zależna od stanu: „DO WBICIA I DO ODZYSKANIA." gdy są zaległości, „WSZYSTKO ROZLICZONE." gdy ich nie ma. Akapit wyjaśniający model: grafik zostaje w Google Calendar, tutaj wbijasz fakty, stawkę ustalasz sam, całość idzie do Ciebie.

Pasek statystyk: Sesje we wrześniu (liczba odbytych) · Zarobek — wrzesień („100% Twoje") · Nierozliczone (kwota + liczba klientów z saldem) · Aktywni klienci.

Poniżej dwie kolumny `minmax(320px, 1fr)`, `gap: 40px`:
- **Do odzyskania** — wiersze `border-bottom: 1px`: nazwisko jako przycisk (hover → akcent) prowadzący do karty, pod nim liczba nierozliczonych sesji i data ostatniej, po prawej kwota (Archivo 800, 19 px, `accent-700`) i przycisk „Monit SMS". Pusty stan: „Zero zaległości. Wszyscy zapłacili."
- **Ostatnio wbite** — 6 ostatnich sesji: data (12 px, `opacity:.5`), nazwisko, kwota, tag statusu. Nagłówek ma link „Cała historia →". Pusty stan: „Nic jeszcze nie wbite. Pierwsza sesja pojawi się tutaj."

#### 5. Klienci

h1 „TWOI KLIENCI. NIE BAZA KONTAKTÓW." + akcja „＋ Dodaj klienta" po prawej.

Pasek filtrów: pole szukania (`max-width: 320px`, przeszukuje nazwisko, telefon i tagi), kontrolka segmentowa Aktywni / Z saldem / Online / Archiwum, licznik „X z Y" po prawej.

Tabela: Klient (nazwisko Archivo 800 15 px + telefon 12 px `opacity:.55`) · Charakterystyka (tagi, `flex-wrap`, `gap: 4px`) · Ostatnia sesja · Stawka · Saldo (Archivo 800, `accent-700` gdy > 0, w przeciwnym razie „—") · akcja „Karta →".

Pusty stan w ramce `2px dashed`, z komunikatem zależnym od kontekstu: puste archiwum / pusta kartoteka / brak wyników filtra.

#### 6. Karta klienta

Powrót „← Klienci" (prowadzi do listy właściwej dla roli).

Nagłówek: h1 z nazwiskiem, tagi pod nim, po prawej saldo (Archivo 800, `40px`, akcent gdy > 0), akcje: „＋ Wbij sesję", „Edytuj kartę", „Wyślij link BLIK" (tylko gdy saldo > 0).

**Pasek stawki** — `background: surface`, `padding: 18px`, `border-bottom: 2px`: etykieta „STAWKA TEGO KLIENTA" w akcencie, wyjaśnienie, po prawej pole liczbowe (krok 5 zł, szerokość 110 px, Archivo 800 17 px) i „zł / sesja".

**Pasek akcji RODO** — `padding: 12px 0`, `border-bottom: 2px`: „Eksport danych klienta" · „Archiwizuj klienta" / „Przywróć z archiwum" (nieaktywne przy saldzie > 0, obok komunikat „Najpierw rozlicz saldo") · po prawej „Usuń dane na żądanie" w `accent-700`.

**Cztery bloki informacyjne** w siatce `minmax(260px, 1fr)`, każdy `padding: 22px 18px; border-left: 1px solid divider`, etykieta w akcencie 10 px uppercase:
1. Kontakt — telefon, e-mail, trener, opiekun
2. Cel i punkt startowy — wiersz „Start" + tekst celu
3. Kontuzje i przeciwwskazania — sam tekst
4. Zgody RODO — status + notatka trenera
5. Dokument sprzedaży — nazwa firmy i NIP, albo „Paragon" z podpowiedzią, że dane firmy dopisuje się w edycji karty

**Historia treningów** — nie tabela, lista wierszy `flex` z `flex-wrap`: data, nazwa usługi + notatka, **edytowalne pole kwoty** (krok 5 zł, wyrównane do prawej), tag statusu. Nagłówek sekcji ma podpowiedź „Kwotę każdej sesji możesz nadpisać", ukrywaną przy pustej liście. Pusty stan: ramka `2px dashed` z „Jeszcze żadnej wbitej sesji" i akcją „＋ Wbij pierwszą sesję".

**Pliki i plany** — wiersze z kwadratowym znacznikiem rozszerzenia 34×34 na `surface`, nazwą, metadanymi i akcją „Wyślij →" (link wygasa po 14 dniach). Pod listą strefa uploadu w ramce `2px dashed`.

#### 7. Sesje

h1 „CO SIĘ ODBYŁO." + akapit: jedno źródło prawdy, wbicie sesji to jedyny moment powstania należności.

Tabela: Data · Klient · Usługa · Notatka trenera (12 px `opacity:.65`) · Kwota (Archivo 800) · Status (tag). Sortowanie malejąco po dacie.

#### 8. Płatności

h1 „KTO ZAPŁACIŁ. KTO JESZCZE NIE."

Pasek statystyk: Nierozliczone łącznie · Opłacone we wrześniu · Linki w obiegu.

**Ostrzeżenie** (tylko gdy `autoOdznaczanie == false`): panel `accent-100` z `border-left: 3px solid accent` — „Automatyczne odznaczanie płatności jest wyłączone. Klient zapłaci z linku, a saldo dalej pokaże dług."

**Panel zbiorczy** — `background: surface`, `border-top: 2px solid accent`, `padding: 20px`: tytuł „Zbiorcze podsumowanie — {miesiąc}", wyjaśnienie i przycisk „Wyślij N podsumowań" z **poprawną polską odmianą** liczebnika.

Tabela: Klient · Nierozliczone sesje · Najstarsza (z dopiskiem „po terminie · N dni" w `accent-700`, gdy przekroczony próg) · Dokument („Faktura · NIP …" albo „Paragon", z adnotacją „wystawiona") · Kwota · Status · akcje „Faktura" (warunkowo) / „Link BLIK" / „Gotówka".

#### 9. Zarobki

h1 „ILE ZAROBIŁEŚ." + akapit: cała kwota od klienta jest Twoja, żadnych prowizji.

Przełącznik miesięcy (Wrzesień / Sierpień / Lipiec 2026) — aktywny jako przycisk główny, pozostałe drugorzędne.

**Panel zarobku** — `background: accent`, `color: bg`, `padding: 28px`: etykieta „ZAROBEK — {MIESIĄC}", kwota Archivo 800 `clamp(44px, 7vw, 72px)`, pod nią liczba odbytych sesji i „100% stawki klienta".

Pasek statystyk: Odbyte sesje · Już na koncie · Jeszcze do zapłaty · Odwołania i no-show (z naliczoną kwotą).

Tabela: Data · Klient · Usługa · Kwota · Status. Pusty stan: „Żadnej wbitej sesji w tym miesiącu."

#### 10. Wiadomości

h1 „CO DOSTAJE KLIENT." Przełącznik klientów — szablony wypełniają się **prawdziwymi danymi** wybranej osoby.

Siatka `minmax(340px, 1fr)`, `gap: 32px`. Każda karta: `border-top: 2px solid divider`, `padding-top: 18px`, tag kanału (SMS = `tag-accent`, E-mail = `tag-outline`), nazwa, moment wysyłki, podgląd, metadane, edytowalny szablon.

Podgląd SMS: `background: surface`, `border: 1px solid divider`, `border-left: 3px solid accent`, `max-width: 320px`, `white-space: pre-wrap`. Podgląd e-maila: karta `surface` z wydzielonym paskiem tematu.

**Licznik segmentów SMS** — pod każdym SMS-em: liczba znaków, liczba wiadomości i kodowanie. Polskie znaki diakrytyczne wymuszają UCS-2, czyli **70 znaków w pojedynczej wiadomości i 67 w sklejanej** (nagłówek UDH), zamiast 160/153 dla GSM-7. To realny mnożnik kosztu — zachowaj ten licznik.

```
ucs2  = /[ąćęłńóśźżĄĆĘŁŃÓŚŹŻ]/.test(tekst)
limit = ucs2 ? 70 : 160
multi = ucs2 ? 67 : 153
segmenty = dlugosc <= limit ? 1 : ceil(dlugosc / multi)
```

Szablony i pola podstawiane — `{imie}`, `{trener}`, `{data}`, `{kwota}`, `{saldo}`, `{link}`, `{linkPliku}`, `{miesiac}` (dopełniacz: „września"), `{miesiacW}` (miejscownik: „wrześniu"), `{lista}`, `{sumaListy}`:

1. **Link do płatności po sesji** (SMS, ręcznie) — `Cześć {imie}! Sesja {data} — {kwota}. Płatność BLIK: {link}. Do zobaczenia, {trener} · SAMtrening`
2. **Monit o zaległej płatności** (SMS, automat po `progMonitu` dniach) — `Cześć {imie}, przypominamy o nierozliczonych sesjach: {saldo}. BLIK: {link}. Jeśli już zapłaciłeś/aś — zignoruj. SAMtrening`
3. **Potwierdzenie płatności** (SMS, tylko gdy `autoOdznaczanie`) — `Dzięki {imie}! {kwota} zaksięgowane. Twoje saldo: {saldo}. Do zobaczenia na treningu. SAMtrening`
4. **Nowy plan do pobrania** (SMS, ręcznie) — `{imie}, Twój plan jest gotowy: {linkPliku} (link wygasa po 14 dniach). {trener} · SAMtrening`
5. **Zbiorcze podsumowanie** (e-mail) — temat `SAMtrening — podsumowanie {miesiac}`; treść wymienia sesje **wyłącznie z wybranego miesiąca**, każda z dopiskiem „· odwołanie po terminie" lub „· nieobecność" gdy `typ ≠ 'odbyta'`, potem „Suma za {miesiac}" i osobno „Całe nierozliczone saldo", link, przypomnienie że płaci się za odbyte sesje, podpis z adresem i telefonem.

Dwie pułapki wyłapane w testach — nie powtarzaj ich: `{miesiac}` w temacie **musi** być w dopełniaczu („podsumowanie września", nie „wrześniu"), a lista sesji **musi** być filtrowana do miesiąca, inaczej wiadomość sama sobie zaprzecza.

#### 11. Ustawienia

h1 „JAK TO MA DZIAŁAĆ." Dwie kolumny `minmax(300px, 1fr)` w ramce `2px` górnej i dolnej, każda `padding: 24px 20px; border-left: 1px`.

**Płatności**: status Stripe (tag + opis) i trzy przełączniki — *Automatyczne odznaczanie płatności* (bez niego saldo kłamie), *BLIK w linku płatniczym*, *Monit o zaległej płatności*.

**Zasady studia**: trzy pola liczbowe — *Bezpłatne odwołanie* (godziny, wpływa na opis w oknie wbijania sesji), *Monit po* (dni, wpływa na oznaczenie „po terminie" w Płatnościach), *Retencja danych* (miesiące, krok 12, wpływa na treść okna usuwania danych).

Każdy przełącznik i każda zmiana liczby idzie do logu zmian.

Dla właściciela dodatkowy panel na `surface` z jawnie wypisanym długiem RODO: brak rejestru czynności przetwarzania, brak umów powierzenia z dostawcą SMS i Stripe'em, brak automatycznego czyszczenia kartotek po okresie retencji.

---

### Panel admina

#### 12. Pulpit studia

h1 „STUDIO W LICZBACH." + akapit: trenerzy rozliczają się bezpośrednio z klientami, Ty pilnujesz kont, kartoteki i tego, żeby nic nie wisiało nieopłacone.

Pasek statystyk: Sesje we wrześniu (całe studio, tylko odbyte) · Klienci studia (bez archiwalnych) · Nieopłacone · Trenerzy (aktywne konta).

Dwie kolumny:
- **Trenerzy — wrzesień**: wiersz na trenera z imieniem, specjalizacją, liczbą sesji, **poziomym słupkiem** (`height: 10px`, tło `surface`, wypełnienie `accent`, szerokość jako procent maksimum) oraz liczbą klientów i obrotem pod spodem.
- **Ostatnie zmiany**: 5 najnowszych wpisów logu (czas, akcja, autor) + „Cały log →".

#### 13. Trenerzy

h1 „TRZY OSOBY. NIE STATYŚCI." + „＋ Zaproś trenera". Akapit: zaproszenie idzie mailem, trener sam ustawia hasło, do aktywacji konto nie widzi żadnych danych klientów.

Tabela: Trener (nazwisko + e-mail) · Specjalizacja · Status (tag: Aktywny `neutral` / Zaproszenie wysłane `outline` / Zablokowany `accent`) · Klienci · Sesje / wrzesień · Obrót · akcje.

Akcje warunkowe: „Reset hasła" (konta aktywne) · „Zablokuj"/„Aktywuj" (nie dla właściciela, nie dla zaproszonych) · „Podgląd linku" i „Ponów zaproszenie" (tylko zaproszeni). „Podgląd linku" pokazuje adminowi dokładnie ten ekran, który zobaczy zaproszony trener.

#### 14. Kartoteka studia

h1 „WSZYSCY KLIENCI." Filtr segmentowy po trenerach (Wszyscy / Maciej / Katarzyna / Bartosz) + wspólne pole szukania.

Tabela jak w widoku trenera, z dodatkową kolumną **Trener**; klienci archiwalni dostają dodatkowy tag „Archiwum". Wejście prowadzi do tej samej karty klienta z pełnym dostępem.

#### 15. Zaległości studia

h1 „CO WISI NIEOPŁACONE." + panel `accent` z sumą (`clamp(40px, 6.5vw, 64px)`) i podpisem „rozliczenie prowadzi trener, Ty tylko pilnujesz".

Tabela: Klient · Trener · Nierozliczone · Najstarsza · Kwota · Status · akcja **„Przypomnij trenerowi"** — nie monit do klienta, bo to trener prowadzi rozliczenie.

#### 16. Log zmian

h1 „KTO CO ZMIENIŁ." + akapit uzasadniający istnienie logu. Tabela: Kiedy · Kto · Co się stało · Kontekst. Najnowsze na górze.

---

## Dialogs

Wszystkie: `dialog-backdrop` `rgba(4,4,3,.8)`, wyrównanie do góry z `padding-top: 5–6vh`, `overflow: auto`; `dialog` na `surface`, `border: 1px solid divider`, `box-shadow: lg`, `padding: 16px`, `gap: 12px`, promień 0.

### Wbij sesję

Kicker „ZREALIZOWANA USŁUGA" + tytuł „Wbij sesję" + zamknięcie „✕".

1. **Co się stało** — kontrolka segmentowa `grid` `minmax(96px, 1fr)`: Odbyta / Odwołana / Nie przyszedł. Zmiana typu resetuje kwotę (stawka klienta ⇄ 0) i tryb rozliczenia.
2. **Klient** — select.
3. Rząd `minmax(140px, 1fr)`: **Usługa** (select; wybór usługi o stałej cenie nadpisuje kwotę, pozostałe biorą stawkę klienta), **Data**, **Kwota (zł)** z podpowiedzią „Stawka klienta: X zł" *pod polem* — etykieta musi być jednoliniowa, inaczej pola w rzędzie przestają się wyrównywać.
4. **Notatka z sesji** — textarea.
5. **Rozliczenie** / **Czy naliczasz?** — etykieta i opcje zależne od typu; radio jako karty `padding: 9px 11px`, `border: 1px solid divider`, tło `bg`, z tytułem Archivo 800 13 px i wyjaśnieniem 12 px `opacity:.6`.
6. Ostrzeżenie o duplikacie (warunkowo) + akcje „Anuluj" / „Zapisz sesję" lub „Zapisz mimo to".

Usługi i ceny: Trening personalny 1:1 (stawka klienta) · Zdrowa ciąża 1:1 (stawka klienta) · E-trening — konsultacja (150) · E-trening — plan miesięczny (300) · Konsultacja i korekta planu (80) · Konsultacja wstępna (0).

### Dodaj / edytuj klienta

Jedno okno, dwa tryby — kicker, tytuł i etykieta przycisku zależne od trybu („Nowa karta / Dodaj klienta / Zapisz klienta" ⇄ „Edycja karty / {nazwisko} / Zapisz zmiany").

Pola: rząd `minmax(200px, 1fr)` z Imię i nazwisko (wymagane), Telefon, E-mail, Stawka (krok 5); Cel i punkt startowy; Kontuzje i przeciwwskazania (textarea); Opiekun z etykietą „wymagany dla osób poniżej 18 lat" — wypełnienie **dodaje tag „Zgoda rodzica"**; Notatka trenera; checkbox **Faktura na firmę** odsłaniający Nazwę firmy i NIP; checkbox **Zgoda RODO odebrana**.

Przycisk zapisu nieaktywny przy pustym nazwisku. Nowa karta bez zgody RODO zapisuje się ze statusem „BRAK ZGODY — uzupełnij przed pierwszą sesją" i taki wpis idzie do logu. Log przy edycji wymienia **konkretne zmienione pola** wraz z wartością przed i po dla stawki.

### Zaproś trenera

Imię i nazwisko, E-mail (oba wymagane), Specjalizacja. Nota na `bg` z `border-left: 3px solid accent`: link do ustawienia hasła ważny 7 dni, konto zobaczy dane klientów dopiero po aktywacji. Po wysłaniu trener pojawia się w tabeli ze statusem `zaproszony`.

### Usunięcie danych (RODO)

Kicker „ŻĄDANIE USUNIĘCIA DANYCH" + nazwisko. Treść wymienia dokładnie, co znika i co zostaje (historia sesji zanonimizowana, wymóg retencji), panel `accent-100` z ostrzeżeniem o nieodwracalności i zapisie w logu, a przy saldzie > 0 dodatkowe ostrzeżenie, że po usunięciu nie da się już wysłać linku.

---

## Interactions & Behavior

- **Nawigacja** — przełączanie widoków bez przeładowania; `aria-current="page"` na aktywnej pozycji. Karta klienta jest podświetlana jako „Klienci" / „Klienci studia" zależnie od roli.
- **Przełączenie roli** resetuje widok na pulpit właściwy dla roli.
- **Wylogowanie** czyści użytkownika, rolę, wybranego klienta i zamyka wszystkie okna.
- **Hover** — pozycje nawigacji i nazwiska w listach zmieniają kolor na akcent; `.btn-secondary` dostaje `rgba(250,250,247,.07)`, `.btn-primary` przechodzi na `#d2e832`; wiersze tabel `rgba(250,250,247,.05)`.
- **Focus** — `outline: 2px solid #e8ff3e; outline-offset: 2px` na wszystkim interaktywnym.
- **Toasty** potwierdzają każdą akcję zmieniającą stan i znikają po 3,8 s; nowy anuluje poprzedni.
- **Walidacja formularzy** — blokowanie przycisku przy brakujących polach wymaganych, komunikaty inline w panelu akcentowym (nie `alert`).
- **Animacje** — jedyna w projekcie to przewijany ticker (34 s, liniowo, w pętli). Reszta interfejsu jest statyczna; nie dodawaj przejść.

### Responsive behavior

Layout jest płynny i nie używa breakpointów: wszystkie siatki to `repeat(auto-fit, minmax(…, 1fr))`, paski akcji i nawigacja mają `flex-wrap`, nagłówki skalują się przez `clamp()`. Do ~390 px poprawnie zwęża się wszystko poza jednym elementem.

**Do zrobienia w implementacji:** gęste tabele (6–7 kolumn) nie mieszczą się na telefonie. Trener pracuje na telefonie w studio i na laptopie po pracy — oba przypadki są równorzędne. Zalecenie: poniżej ~720 px zamień tabele na listę kart (nazwisko + kwota + status + akcja), a wiersze tabel admina na karty z kolumną trenera jako podtytułem. Zadbaj o **minimum 44 px wysokości pól dotykowych** — obecne przyciski akcji w wierszach mają ~32–38 px.

---

## State Management

Stan globalny (w prototypie jeden obiekt komponentu — w produkcji rozbij na store i zapytania do API):

```
auth: 'login' | 'reset' | 'resetWyslany' | 'onboarding' | 'app'
uzytkownik: trenerId | null
rola: 'trener' | 'admin'          // wymuszane na 'trener' gdy użytkownik nie jest właścicielem
view: nazwa widoku
klientId, szablonKlientId          // aktualnie otwarty / podglądany klient
szukaj, filtr, filtrTrenera, miesiac
dialog, dialogKlienta, dialogTrenera, dialogUsuniecia
form, nowy, nowyTrener             // stany formularzy
logowanie: { email, haslo, blad }
onboardingId, onboardingTryb, onboardingForm
clients[], trenerzy[], dziennik[], faktury{}, ustawienia{}, szablony{}
toast
```

Uprawnienie admina wyliczaj z danych, nie ze stanu: `rola = jestWlascicielem ? state.rola : 'trener'`. Dzięki temu podmiana stanu w kliencie nie daje dostępu do panelu admina — ale **autoryzację i tak wymuś po stronie serwera**, prototyp nie ma żadnej.

### Wymagania integracyjne

- ~~**Stripe**~~ — **poza zakresem.** BLIK idzie ręcznie z telefonu trenera, wpłatę odznacza człowiek. Bez bramki, subkont i webhooków.
- **Bramka SMS** (w prototypie SMSAPI) — wysyłka monitów i linków, z liczeniem segmentów po stronie serwera.
- **Poczta transakcyjna** — zaproszenia, resety hasła, zbiorcze podsumowania miesiąca.
- **Storage plików** — plany treningowe i dokumenty, z **linkami wygasającymi po 14 dniach** (klient nie ma konta, więc link jest jedyną drogą dostępu).
- **Faktury** — integracja z systemem księgowym albo generowanie PDF; numeracja i dane sprzedawcy po stronie serwera.
- **Autentykacja** — hasła (min. 8 znaków), tokeny zaproszeń ważne 7 dni, tokeny resetu ważne 60 minut i jednorazowe, unieważnianie sesji po zmianie hasła, neutralna odpowiedź na żądanie resetu.

### Dług RODO do domknięcia poza interfejsem

Kontuzje, przebieg ciąży i dane o zdrowiu dziecka to **szczególna kategoria danych osobowych** — nie zwykłe dane kontaktowe. Interfejs ma zgody, eksport i usunięcie na żądanie. Brakuje: rejestru czynności przetwarzania, umów powierzenia z dostawcą SMS i Stripe'em, automatycznego czyszczenia kartotek po okresie retencji oraz szyfrowania pól zdrowotnych w spoczynku.

---

## Assets

Brak plików graficznych. Wszystko jest typografią, kolorem i liniami.

- Fonty: **Anton** (400) i **Archivo** (400–900) z Google Fonts.
- Ikonografia: znaki tekstowe (`＋`, `→`, `←`, `✕`, `●`, `·`) zamiast biblioteki ikon. Jeśli codebase ma zestaw ikon, podmień je, zachowując wagę kreski zgodną z tekstem.
- Znak marki: „SAM·TRENING" jako tekst (Archivo 800, kropka w kolorze akcentu). Jeśli studio ma plik logo, podmień.
- Nie ma fotografii. Strona źródłowa używa zdjęć w czerni i bieli — jeśli dodasz je do CRM-u, trzymaj tę konwencję.

## Files

- `SAMtrening CRM.dc.html` — kompletny prototyp: wszystkie 16 ekranów, oba panele, okna dialogowe, dane demonstracyjne (3 trenerów, 9 klientów, historia sesji z odwołaniami i nieobecnościami, log zmian). Otwiera się bezpośrednio w przeglądarce. Ekran logowania ma skróty „wejdź jako", żeby przejść między rolami bez wpisywania haseł.
- `styles.css` — arkusz systemu wizualnego z klasami komponentów (`.btn`, `.tag`, `.card`, `.table`, `.input`, `.seg`, `.radio`, `.dialog`, `.nav`, `.hr`). Prototyp nadpisuje jego tokeny na paletę marki w bloku `:root` w nagłówku pliku HTML — **wartości z tego handoffu mają pierwszeństwo nad wartościami w `styles.css`**.


## Zmiany — 10.09.2026

- **Numer BLIK per trener** (`Trener.blik`). Wszystkie szablony (`{blik}`) i akcje płatnicze podstawiają numer *zalogowanego* trenera. Wpłaty idą wprost na konto trenera — studio ich nie widzi, więc automatyczne odznaczanie wpłat wymagałoby integracji z bankiem każdego trenera osobno. Odznaczenie pozostaje ręczne.
- **E-mail z podsumowaniem miesiąca**: podaje wyłącznie kwotę za bieżący miesiąc (`sumaListy`), podpis `{trenerPelny}` (imię i nazwisko trenera), nadawca = e-mail trenera. Pole `{saldo}` usunięte z tego szablonu. Uwaga: nierozliczone sesje z poprzednich miesięcy nie pojawiają się w tym e-mailu — pilnuje ich monit SMS i zakładka Płatności.
- **Kafel „Cisza w kalendarzu"** na Pulpicie trenera: klienci, dla których `dni(max(sesje.data)) >= 21`, posortowani malejąco. Akcja „Zaczep SMS-em" korzysta z nowego szablonu `zaczepka`.
- **Tabele na telefonie**: poniżej 760 px każdy `<tr>` renderuje się jako karta, a `<td>` bierze etykietę z atrybutu `data-label`. Każda nowa kolumna musi mieć `data-label` (puste `data-label=""` dla kolumny z przyciskami).
- **Eksport CSV**: w Zarobkach (sesje zalogowanego trenera z wybranego zakresu) i na Pulpicie studia (całe studio, z kolumną Trener). Separator `;`, BOM UTF-8 na starcie pliku — inaczej polskie Excele psują znaki. Kwoty jako liczby bez waluty. **Notatki z sesji nie wchodzą do eksportu** — to dane o zdrowiu, a księgowość ich nie potrzebuje (minimalizacja RODO). Nazwa pliku: `samtrening-2026-09-maciek.csv`.
- **Domykacz tygodnia** (Pulpit trenera): siatka Pn–Nd bieżącego tygodnia ISO × aktywni klienci (bez tych z kafla „Cisza w kalendarzu"). Pole wypełnione = sesja odbyta, `×` = odwołanie/nieobecność, puste = brak wpisu. Wiersz bez żadnego wpisu dostaje przycisk „Wbij sesję" otwierający dialog z tym klientem i dzisiejszą datą. W prototypie tydzień jest zahardkodowany (`2026-09-07`…`13`) — docelowo liczony z ISO week bieżącej daty.
- **Notatka „na następny raz"** (`Klient.plan`): pole w dialogu wbijania sesji, prefill z aktualnej wartości klienta, zapis nadpisuje pole na kliencie. Widoczna jako sekcja na Pulpicie i blok na karcie klienta. Nigdy nie idzie do klienta — nie podstawia się w żadnym szablonie wiadomości.
- **Zakres w widoku admina**: Pulpit studia i Trenerzy liczą sesje oraz obrót w wybranym zakresie (miesiąc albo cały rok) — filtr to prefiks daty (`2026-09` / `2026`), wspólny ze stanem `miesiac` w Zarobkach trenera. Salda i zaległości pozostają narastające, bo dług nie należy do miesiąca.
- **Usuwanie sesji**: przycisk „Usuń" w wierszu historii na karcie klienta. Bez dialogu potwierdzenia — zamiast tego toast z akcją „Cofnij" (8 s) przywracającą wpis na tę samą pozycję. Usunięcie i cofnięcie zapisują się w logu zmian razem z kwotą i informacją, czy wpis był na saldzie. Backend: soft delete + wpis w audit logu, nie DELETE.
- **Dane demo**: doszli Rafał Kubiak (27 dni ciszy) i Dorota Malec (61 dni) — bez nich kafel retencyjny nie miał czego pokazać.


## Stany interfejsu — czego prototyp nie pokazuje, a aplikacja musi mieć

Prototyp trzyma dane lokalnie, więc nigdy nie czeka i nigdy nie zawodzi. W Laravelu trzeba dołożyć trzy rodziny stanów:

**Wczytywanie.** Tabele (Klienci, Sesje, Płatności, Zarobki, ekrany admina) dostają szkielet: 5–8 wierszy w kolorze `--color-surface` o wysokości docelowego wiersza, bez animacji pulsowania. Nie pokazujemy spinnera na całym ekranie — układ ma nie skakać. Przyciski zapisu (`Zapisz sesję`, `Zapisz klienta`, `Aktywuj konto`) na czas żądania: `disabled` + tekst zmieniony na `Zapisuję…`. Livewire: `wire:loading.attr="disabled"` i `wire:target`.

**Błąd.** Wariant komunikatu z ciemnym tłem, ramką w akcencie i znakiem `!` (jest w prototypie — patrz metoda `blad()`). Zasady:
- Zapis sesji i wysyłka wiadomości to **dwie osobne operacje**. Jeśli SMS albo e-mail nie wyjdzie, sesja zostaje zapisana, a komunikat mówi wprost, że wiadomość nie poszła. Nigdy nie wycofujemy sesji z powodu błędu dostawcy SMS.
- Wysyłka idzie przez kolejkę z ponowieniami; trwałe niepowodzenie ląduje w logu zmian jako wpis „Wiadomość nie wyszła" i jest widoczne przy kliencie.
- Brak numeru BLIK trenera blokuje wysyłkę przed jej rozpoczęciem (walidacja `brakBliku()`), z odesłaniem do Ustawień. Świeżo zaproszony trener ma to pole puste.
- Konflikt edycji (dwie osoby zmieniają kwotę tej samej sesji): wygrywa zapis późniejszy, ale oba trafiają do logu.

**Puste stany.** Są w prototypie i są celowo różne dla różnych przyczyn: pusta kartoteka, pusty filtr, puste archiwum, klient bez sesji, brak zaległości. Nie zwijać ich do jednego „Brak danych".

## Decyzje podjęte

- **Bez importu danych.** Kartoteka zaczyna od zera, klienci wpisywani ręcznie. Nie budujemy importera CSV. Konsekwencja dla implementacji: puste stany („Kartoteka jest pusta. Dodaj pierwszego klienta…") są pierwszym ekranem, jaki zobaczy każdy trener — muszą być dopracowane, a nie potraktowane jako przypadek brzegowy.
- **BLIK ręcznie od trenera.** Żadnej bramki płatniczej, żadnych subkont, żadnego webhooka. Trener wysyła prośbę SMS-em ze swoim numerem, klient robi przelew BLIK na telefon, trener odznacza wpłatę w CRM. Konsekwencja: `Sesja.status` zmienia wyłącznie człowiek, nie system — i nie ma czego automatyzować po stronie płatności.
- **Klient nie ma konta ani dostępu do CRM.** Cała komunikacja to SMS i e-mail wychodzący.

## Zanim programista zacznie

- **Dostawca SMS i domena e-mail** z SPF/DKIM — bez tego wiadomości nie działają w produkcji.
- **Treści prawne**: zgoda na przetwarzanie danych o zdrowiu (art. 9 RODO) i informacja o przetwarzaniu. CRM **rejestruje tylko fakt i datę zgody** — treść odbierana jest poza systemem (papier na pierwszej sesji albo formularz na samtrening.com). Brak dostępu klienta do systemu nie znosi tego obowiązku: podstawą jest przetwarzanie danych o zdrowiu (kontuzje, ciąża, nadciśnienie), nie logowanie.
- **Umowa powierzenia przetwarzania** z trenerami, jeśli pracują na własnych działalnościach — każdy ma dostęp do danych zdrowotnych swoich klientów.
- **Numeracja rachunków**, jeśli dojdą faktury dla firm.
- **Kopie zapasowe i eksport całości** — przy danych o zdrowiu i pieniądzach to nie jest opcja.
