# Task Manager API

API RESTful per la gestione di task personali, sviluppata con Laravel 12 come progetto didattico per approfondire autenticazione JWT, relazioni Eloquent, autorizzazione tramite Policy, API Resources e query building.

> Progetto **API-only**: nessuna view/frontend incluso. Pensato per essere consumato da un client esterno (SPA, app mobile, Postman, ecc.).

---

## Indice

- [Stack tecnico](#stack-tecnico)
- [Requisiti](#requisiti)
- [Installazione](#installazione)
- [Configurazione ambiente](#configurazione-ambiente)
- [Autenticazione (JWT)](#autenticazione-jwt)
- [Modello dati](#modello-dati)
- [Autorizzazione (Policy)](#autorizzazione-policy)
- [API Resources](#api-resources)
- [Endpoints](#endpoints)
- [Filtri e paginazione](#filtri-e-paginazione)
- [Seeder e dati di prova](#seeder-e-dati-di-prova)
- [Gestione errori](#gestione-errori)
- [Struttura del progetto](#struttura-del-progetto)
- [Note tecniche e cose imparate](#note-tecniche-e-cose-imparate)
- [Possibili sviluppi futuri](#possibili-sviluppi-futuri)

---

## Stack tecnico

| Componente | Tecnologia |
|---|---|
| Framework | Laravel 12 |
| Autenticazione | JWT via `tymon/jwt-auth` |
| Database | SQLite |
| ORM | Eloquent |
| Autorizzazione | Laravel Policy |
| Formattazione output | Laravel API Resources |
| Test manuale | Postman |
| Versionamento | Git |

---

## Requisiti

- PHP >= 8.2
- Composer
- Estensione SQLite abilitata in PHP

---

## Installazione

```bash
git clone <url-repo>
cd task-manager-api
composer install
copy .env.example .env      # Windows (PowerShell)
# cp .env.example .env      # Linux/Mac
php artisan key:generate
```

Crea il file del database SQLite:

```bash
New-Item database/database.sqlite   # Windows PowerShell
# touch database/database.sqlite    # Linux/Mac
```

Esegui le migration:

```bash
php artisan migrate
```

Genera la secret key per JWT:

```bash
php artisan jwt:secret
```

(Facoltativo) Popola il database con dati di prova:

```bash
php artisan db:seed --class=TaskSeeder
```

Avvia il server:

```bash
php artisan serve
```

L'API sarà disponibile su `http://localhost:8000/api`.

---

## Configurazione ambiente

Nel file `.env`, assicurati di avere:

```dotenv
DB_CONNECTION=sqlite
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=laravel
# DB_USERNAME=root
# DB_PASSWORD=
```

Le righe relative a host/porta/username/password del DB vanno commentate o rimosse: con SQLite non servono, il "database" è semplicemente il file `database/database.sqlite`.

La chiave `JWT_SECRET` viene aggiunta automaticamente al `.env` dal comando `php artisan jwt:secret` e serve a firmare i token. **È specifica per ogni ambiente**: un token generato in un ambiente non è valido in un altro con secret diversa (es. desktop vs laptop, se configurati separatamente).

---

## Autenticazione (JWT)

L'autenticazione è gestita tramite **JWT (JSON Web Token)**, non tramite Sanctum. Le differenze principali rispetto a Sanctum:

- Il token è **stateless**: non viene salvato in nessuna tabella del database (a differenza di Sanctum, che usa `personal_access_tokens`).
- Il token contiene già, firmato al suo interno, l'identificativo dell'utente (claim `sub`) e una scadenza (claim `exp`).
- L'invalidazione (`logout`) blacklista il token invece di eliminare una riga da una tabella.

### Guard configurato

In `config/auth.php`:

```php
'guards' => [
    'api' => [
        'driver' => 'jwt',
        'provider' => 'users',
    ],
],
```

### Model User

Il model `User` implementa `Tymon\JWTAuth\Contracts\JWTSubject`:

```php
public function getJWTIdentifier()
{
    return $this->getKey();
}

public function getJWTCustomClaims()
{
    return [];
}
```

### Endpoint di autenticazione

| Metodo | Endpoint | Autenticazione richiesta | Descrizione |
|---|---|---|---|
| POST | `/api/register` | No | Registra un nuovo utente e ritorna un token |
| POST | `/api/login` | No | Autentica l'utente e ritorna un token |
| POST | `/api/logout` | Sì (`Bearer <token>`) | Invalida il token corrente |

**Esempio — Register**

Richiesta:
```json
POST /api/register
{
    "name": "Mario Rossi",
    "email": "mario@test.com",
    "password": "password123"
}
```

Risposta (201):
```json
{
    "status": "success",
    "message": "Registrazione effettuata con successo",
    "data": {
        "user": { "id": 1, "name": "Mario Rossi", "email": "mario@test.com", ... },
        "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
    }
}
```

**Esempio — Login**

Richiesta:
```json
POST /api/login
{
    "email": "mario@test.com",
    "password": "password123"
}
```

**Come usare il token**

Ogni richiesta autenticata deve includere l'header:

```
Authorization: Bearer <token>
```

---

## Modello dati

### Relazione User ↔ Task

Un utente può avere più task, un task appartiene a un solo utente (relazione 1:N).

```php
// User.php
public function tasks()
{
    return $this->hasMany(Task::class);
}

// Task.php
public function user()
{
    return $this->belongsTo(User::class);
}
```

### Schema tabella `tasks`

| Colonna | Tipo | Note |
|---|---|---|
| `id` | bigint | Primary key |
| `title` | string | Obbligatorio |
| `description` | text | Opzionale (`nullable`) |
| `status` | boolean | Default `false`. Esposto come `completed` nell'output JSON |
| `user_id` | foreign id | Riferimento a `users.id`, `onDelete('cascade')` |
| `created_at` / `updated_at` | timestamp | Gestiti automaticamente da Eloquent |

> Nota: se un utente viene eliminato, tutti i suoi task vengono eliminati automaticamente grazie a `onDelete('cascade')`.

---

## Autorizzazione (Policy)

L'accesso ai singoli task è regolato da `App\Policies\TaskPolicy`, che garantisce che **ogni utente possa vedere, modificare ed eliminare solo i propri task**.

```php
class TaskPolicy
{
    public function view(User $user, Task $task): bool
    {
        return $task->user_id === $user->id;
    }

    public function update(User $user, Task $task): bool
    {
        return $task->user_id === $user->id;
    }

    public function delete(User $user, Task $task): bool
    {
        return $task->user_id === $user->id;
    }
}
```

Nel controller, la Policy viene richiamata così:

```php
public function show(Task $task)
{
    $this->authorize('view', $task);
    // ...
}
```

Se l'utente autenticato non è il proprietario del task, Laravel lancia automaticamente un'eccezione che viene tradotta in una risposta **403 Forbidden**.

Il metodo `authorize()` è disponibile grazie al trait `AuthorizesRequests`, incluso nel controller base:

```php
// app/Http/Controllers/Controller.php
abstract class Controller
{
    use AuthorizesRequests;
}
```

---

## API Resources

L'output JSON dei task non espone direttamente il model Eloquent grezzo, ma passa attraverso `App\Http\Resources\TaskResource`, che definisce esplicitamente la struttura esposta al client:

```php
class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'completed' => (bool) $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'user_id_creator' => $this->user_id,
        ];
    }
}
```

Vantaggi di questo approccio:
- **Disaccoppiamento** tra struttura del database e struttura esposta all'esterno (es. `status` nel DB diventa `completed` nel JSON).
- **Cast espliciti** (es. `0`/`1` di SQLite convertiti in booleano `true`/`false`).
- **Whitelist esplicita** dei campi: se un campo non è elencato in `toArray()`, semplicemente non compare nella risposta, anche se esiste nel database.

---

## Endpoints

Tutti gli endpoint sotto `/api/tasks` richiedono autenticazione (`Authorization: Bearer <token>`).

| Metodo | Endpoint | Descrizione |
|---|---|---|
| GET | `/api/tasks` | Lista paginata dei task dell'utente autenticato (con filtri opzionali) |
| POST | `/api/tasks` | Crea un nuovo task |
| GET | `/api/tasks/{id}` | Dettaglio di un task specifico |
| PUT/PATCH | `/api/tasks/{id}` | Aggiorna un task |
| DELETE | `/api/tasks/{id}` | Elimina un task |

**Esempio — Store**

```json
POST /api/tasks
{
    "title": "Comprare il latte",
    "description": "Anche le uova, se ci sono"
}
```

Risposta (201):
```json
{
    "status": "success",
    "message": "Task creato con successo",
    "data": {
        "id": 5,
        "title": "Comprare il latte",
        "description": "Anche le uova, se ci sono",
        "completed": false,
        "created_at": "2026-07-13T17:44:03.000000Z",
        "updated_at": "2026-07-13T17:44:03.000000Z",
        "user_id_creator": 1
    }
}
```

**Esempio — Update**

```json
PUT /api/tasks/5
{
    "title": "Comprare il latte e il pane"
}
```

> Nota: la validazione in `update` usa la regola `sometimes` invece di `required`, così l'utente può aggiornare anche un solo campo senza dover rimandare l'intero oggetto.

**Esempio — Accesso non autorizzato**

Se un utente prova ad accedere a un task che non gli appartiene:

```json
{
    "status": "error",
    "message": "Non autorizzato"
}
```
Status HTTP: `403`

---

## Filtri e paginazione

L'endpoint `GET /api/tasks` supporta query string opzionali:

| Parametro | Esempio | Effetto |
|---|---|---|
| `completed` | `?completed=true` | Filtra solo i task completati (o non completati con `false`) |
| `search` | `?search=spesa` | Cerca la stringa nel titolo del task (`LIKE %spesa%`) |
| `page` | `?page=2` | Naviga tra le pagine dei risultati |

I risultati sono paginati (10 per pagina). La risposta include un blocco `meta` con i dati di paginazione:

```json
{
    "status": "success",
    "data": [ /* array di task */ ],
    "meta": {
        "current_page": 1,
        "last_page": 2,
        "per_page": 10,
        "total": 12
    }
}
```

Esempio di combinazione filtri:
```
GET /api/tasks?completed=true&search=spesa&page=1
```

---

## Seeder e dati di prova

Il progetto include una **Factory** (`database/factories/TaskFactory.php`) e un **Seeder** (`database/seeders/TaskSeeder.php`) per generare rapidamente dati di test.

```php
// TaskFactory.php
public function definition(): array
{
    return [
        'title' => $this->faker->sentence(),
        'description' => $this->faker->paragraph(),
        'status' => $this->faker->boolean(),
    ];
}
```

```php
// TaskSeeder.php
public function run(): void
{
    $user = User::firstOrCreate(
        ['email' => 'test@test.com'],
        ['name' => 'Test User', 'password' => bcrypt('password123')]
    );

    Task::factory()->count(10)->for($user)->create();
}
```

Per lanciare il seeder:

```bash
php artisan db:seed --class=TaskSeeder
```

Questo crea (o riusa, senza duplicare) l'utente `test@test.com` / `password123` e gli associa 10 task con titoli e descrizioni generati casualmente da Faker.

> Nota: perché la Factory funzioni, il model `Task` deve includere il trait `HasFactory`:
> ```php
> class Task extends Model
> {
>     use HasFactory;
>     // ...
> }
> ```

---

## Gestione errori

Le eccezioni più comuni sono intercettate in `bootstrap/app.php` e trasformate in risposte JSON pulite (invece dello stack trace di debug di default), quando la richiesta è verso `/api/*`:

| Eccezione | Status HTTP | Risposta |
|---|---|---|
| `AccessDeniedHttpException` (autorizzazione Policy fallita) | 403 | `{"status": "error", "message": "Non autorizzato"}` |
| `AuthenticationException` (token mancante/non valido) | 401 | `{"status": "error", "message": "Non autenticato"}` |
| `ValidationException` (validazione input fallita) | 422 | `{"status": "error", "message": "Errore di validazione", "errors": {...}}` |

```php
->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->shouldRenderJsonWhen(
        fn (Request $request) => $request->is('api/*'),
    );

    $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
        if ($request->is('api/*')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Non autorizzato',
            ], 403);
        }
    });

    // ... altri render per AuthenticationException e ValidationException
})
```

> Nota tecnica: Laravel converte internamente `AuthorizationException` (lanciata da `$this->authorize()`) in `AccessDeniedHttpException` prima che arrivi al gestore delle eccezioni. Per questo il render personalizzato va scritto su `AccessDeniedHttpException`, non su `AuthorizationException`.

---

## Struttura del progetto

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Controller.php              # Classe base, include AuthorizesRequests
│   │   ├── TaskController.php          # CRUD completo per i task
│   │   └── Auth/
│   │       └── AuthController.php      # register, login, logout
│   └── Resources/
│       └── TaskResource.php            # Formattazione output JSON dei task
├── Models/
│   ├── User.php                        # Implementa JWTSubject, relazione tasks()
│   └── Task.php                        # HasFactory, relazione user()
└── Policies/
    └── TaskPolicy.php                  # Autorizzazione view/update/delete

database/
├── factories/
│   └── TaskFactory.php                 # Generazione dati fake per Task
├── migrations/
│   └── ..._create_tasks_table.php
└── seeders/
    └── TaskSeeder.php                  # Popola il DB con utente + 10 task

routes/
└── api.php                             # Route pubbliche (register/login) e protette (logout, tasks)

bootstrap/
└── app.php                             # Configurazione guard eccezioni JSON
```

---

## Note tecniche e cose imparate

Raccolta di problemi concreti incontrati durante lo sviluppo e relative soluzioni — utile come riferimento futuro.

- **Laravel 12 e API routes**: a differenza delle versioni precedenti, `routes/api.php` non è attivo di default. Va abilitato con `php artisan install:api` oppure aggiungendo manualmente la riga `api: __DIR__.'/../routes/api.php'` dentro `withRouting()` in `bootstrap/app.php`.

- **`AuthorizesRequests` non incluso di default**: nel Controller base generato da Laravel 12, il trait va aggiunto manualmente per poter usare `$this->authorize()`.

- **JWT vs Sanctum — attenzione alle abitudini**: sintassi come `$user->createToken()->plainTextToken` o `$request->validated()` (quest'ultima disponibile solo con le Form Request, non con `Request` semplice) sono facili da riportare per errore da un progetto con Sanctum, ma non si applicano a JWT.

- **Falsi positivi di Intelephense**: metodi come `attempt()`, `logout()`, `tasks()` chiamati su `auth('api')->user()` vengono segnalati come "undefined" perché l'IDE vede solo il tipo generico `Authenticatable|Guard`, non l'istanza concreta (`JWTGuard`, `User`) usata realmente a runtime. Si risolve (esteticamente, non è un errore bloccante) con un docblock esplicito:
  ```php
  /** @var \App\Models\User $user */
  $user = auth('api')->user();
  ```

- **`AuthorizationException` vs `AccessDeniedHttpException`**: Laravel converte internamente la prima nella seconda prima che arrivi al gestore custom delle eccezioni in `bootstrap/app.php` — il render personalizzato va scritto intercettando `AccessDeniedHttpException`.

- **Password reset in API-only**: le route di reset password di default di Laravel assumono un contesto web (con redirect a view). In un progetto API-only serve una `ResetPasswordNotification` personalizzata per evitare l'errore `Route [password.reset] not defined`.

- **Proprietà vs metodo nelle relazioni Eloquent**: `$user->tasks` (senza parentesi) esegue subito la relazione e ritorna la Collection; `$user->tasks()` (con parentesi) ritorna il query builder, necessario quando si vogliono aggiungere condizioni (`where`, `paginate`, `create`, ecc.) prima di eseguire la query.

---

## Possibili sviluppi futuri

Idee non ancora implementate, valutabili come estensione del progetto:

- **OTP a 6 cifre** come alternativa ai lunghi token per il reset password (più semplice da inserire manualmente, utile in flussi mobile-first).
- **Refresh token JWT**: attualmente il token ha una scadenza fissa senza meccanismo di rinnovo automatico.
- **Test automatici** (PHPUnit/Pest) per coprire i flussi principali (auth, CRUD, autorizzazione), invece del solo test manuale via Postman.
- **Rate limiting** sugli endpoint di autenticazione, per mitigare tentativi di brute-force su login.
- **Priorità e scadenza (`due_date`)** come campi aggiuntivi del model Task.
- **Collection Postman esportata** con tutte le richieste pronte, per velocizzare i test futuri senza doverle ricostruire a mano.

---

## Autore

Progetto di apprendimento personale, sviluppato passo passo per approfondire lo sviluppo backend con Laravel — in particolare autenticazione API, relazioni Eloquent, autorizzazione e API design.