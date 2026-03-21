# IEEE ZSB Backend Phase 2 — PHP Project Documentation

---

## Section 0: PHP Fundamentals

> **Objective:** Establish a foundational understanding of core PHP constructs prior to engaging with the project architecture. Topics covered include variables, output mechanisms, string operations, boolean logic, conditional statements, arrays, iteration, and function definitions.

---

## 1. Variables & Output

PHP variables are prefixed with a `$` sign and are conventionally named using camelCase. Output to the client is produced via either `echo` or `print`.

**Behavioral distinctions:**

- `echo` accepts multiple comma-separated arguments, produces no return value, and has a marginal performance advantage.
- `print` accepts exactly one argument and returns the integer `1`, making it usable within expression contexts.

```php
<?php
$message = "Hello, World!";

echo $message;         // Output via echo
print $message;        // Output via print

echo "Hello", ", ", "World!";  // echo with multiple arguments
?>

<?= $message ?>        // Shorthand — functionally equivalent to <?php echo $message; ?>
```

> The `<?= ?>` shorthand construct is the preferred method for embedding output within HTML templates, as it eliminates the verbosity of the full `echo` syntax and improves template readability.

---

## 2. String Concatenation

String values in PHP are joined using the dot (`.`) operator.

```php
<?php
$name     = "Abbas";
$greeting = "Hello, " . $name . "!" . "<br>"; // <br> for visual separation

echo $greeting; // Hello, Abbas!
?>
```

---

## 3. Booleans & Conditionals

PHP natively supports `true` and `false` boolean literals and provides the standard conditional control structures available in most procedural languages.

```php
<?php
$isLoggedIn = true;

if ($isLoggedIn) {
    echo "Welcome back!";
} else {
    echo "Please log in.";
}

// Ternary operator shorthand
$message = $isLoggedIn ? "Welcome back!" : "Please log in.";
echo $message;
?>
```

---

## 4. Arrays

### 4.1 Indexed Arrays

Elements are stored sequentially and are retrieved using a zero-based numeric index.

```php
<?php
$movies = [
    ["The Godfather 1", 1972, "Francis Ford Coppola"],
    ["The Dark Knight", 2008, "Christopher Nolan"],
    ["Django Unchained", 2012, "Quinten Tarantino"],
];

echo $movies[0][0]; // The Godfather 1
echo $movies[0][1]; // 1972
echo $movies[0][2]; // Francis Ford Coppola
?>
```

### 4.2 Associative Arrays

Elements are addressed by named string keys rather than numeric indices — semantically equivalent to a map or dictionary structure in other languages.

```php
<?php
$movies = [
    [
        "title"       => "The Godfather 1",
        "releaseYear" => 1972,
        "director"    => "Francis Ford Coppola",
    ],
    [
        "title"       => "The Dark Knight",
        "releaseYear" => 2008,
        "director"    => "Christopher Nolan",
    ],
    [
        "title"       => "Django Unchained",
        "releaseYear" => 2012,
        "director"    => "Quinten Tarantino",
    ],
];

echo $movies[0]["title"];    // The Godfather 1
echo $movies[1]["director"]; // Christopher Nolan
?>
```

---

## 5. Loops

### 5.1 `foreach` Loop

The `foreach` construct is the idiomatic mechanism for iterating over arrays in PHP.

```php
<?php
foreach ($movies as $movie) {
    echo $movie["title"] . " by " . $movie["director"] . " (" . $movie["releaseYear"] . ")\n";
}
?>
```

**Expected output:**

```
The Godfather 1 (1972)
The Dark Knight (2008)
Django Unchained (2012)
```

### 5.2 `foreach` in HTML Templates

The alternative colon-syntax for `foreach` integrates cleanly with `<?= ?>` shorthand, producing template files that maintain a clear boundary between control logic and markup.

```php
<?php foreach ($movies as $movie) : ?>
    <li><?= $movie["title"] ?> — <?= $movie["director"] ?></li>
<?php endforeach; ?>
```

> This syntax is strongly preferred in view templates, as it maximises the visual separation between PHP logic and HTML structure, thereby improving maintainability.

---

## 6. Functions

### 6.1 Named Function

Declared with the `function` keyword; callable from any point within the enclosing scope.

```php
<?php
function filterByDirector(array $movies, string $director): array
{
    $result = [];

    foreach ($movies as $movie) {
        if ($movie["director"] === $director) {
            $result[] = $movie;
        }
    }

    return $result;
}

$simpsonMovies = filterByDirector($movies, "Kyle Simpson");
echo $simpsonMovies[0]["title"]; // You Don't Know JS
?>
```

### 6.2 Anonymous Function

Defined without a name and assigned to a variable. Appropriate for inline use cases or single-purpose callbacks where a named declaration would be unnecessarily verbose.

```php
<?php
$filterByYear = function (array $movies, int $year): array {
    $result = [];

    foreach ($movies as $movie) {
        if ($movie["releaseYear"] > $year) {
            $result[] = $movie;
        }
    }

    return $result;
};

$recentMovies = $filterByYear($movies, 2000);
?>
```

### 6.3 `array_filter` with a Callback

`array_filter` accepts an array and a callable, returning a subset containing only the elements for which the callback evaluates to `true`. This enables the construction of concise, composable filter operations.

```php
<?php
$filteredMovies = array_filter($movies, function($movie) {
    return $movie['releaseYear'] > 2000;
});

foreach ($filteredMovies as $movie) {
    echo $movie["title"] . "\n";
}
?>
```

---

## Section 1: Project Structure — Views, Partials & Routing Helpers

> **Objective:** Establish a disciplined separation between page-level business logic and HTML rendering templates.

---

## 1. The Problem This Solves

In the absence of a defined structure, every page file would contain duplicated HTML boilerplate — `<head>`, navigation, and footer markup — interleaved with its own application logic. Any modification to shared elements such as the navigation bar would necessitate changes across every individual file.

This structure addresses the problem through two architectural principles:

1. **Logic vs. template separation** — each page is represented by a logic file (`index.php`) and a corresponding view file (`index.view.php`).
2. **Shared vs. unique content** — HTML fragments common to all pages are extracted into `partials/` and included at the point of use.

---

## 2. Project File Structure (Initial)

```
project/
│
├── index.php           ← Assigns $heading, requires view
├── about.php
├── contact.php
│
├── functions.php       ← Application-wide helpers (e.g. urlIs)
│
└── views/
    ├── index.view.php  ← Composes partials and page-specific content
    ├── about.view.php
    ├── contact.view.php
    │
    └── partials/
        ├── header.php  ← <head>, meta tags, CSS references
        ├── nav.php     ← Navigation bar
        ├── banner.php  ← Page heading banner
        └── footer.php  ← Closing scripts, </body>, </html>
```

---

## 3. Page Logic File — `index.php`

Each logic file carries a single responsibility: prepare the data required by its associated view, then delegate rendering via `require`.

```php
<?php

$heading = 'Home';

require 'views/index.view.php';
```

- `$heading` is declared at this level and remains accessible within the view, as `require` operates within the same variable scope.
- The identical pattern is applied in `about.php` and `contact.php`, keeping each logic file minimal and focused.

> `require` vs `include`: both insert a file at the call site, but `require` raises a fatal error if the target file is absent, whereas `include` issues only a warning and continues execution. For all non-optional files, `require` is the correct choice.

---

## 4. View File — `views/index.view.php`

The view file functions exclusively as a rendering template. Its responsibility is to compose the page from shared partials and supply the content unique to that route.

```php
<?php require('partials/header.php'); ?>
<?php require('partials/nav.php'); ?>
<?php require('partials/banner.php'); ?>

<main>
    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <p>Welcome to the Homepage</p>
    </div>
</main>

<?php require('partials/footer.php'); ?>
```

- No business logic resides in view files — only structural composition.
- The variable `$heading`, assigned in `index.php`, is already in scope by the time `banner.php` is executed.

---

## 5. Partials

### 5.1 `banner.php` — Page Heading

Renders the page title dynamically using `$heading`, ensuring consistent heading output across all pages without repetition.

```php
<header class="relative bg-white shadow-sm">
    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-bold tracking-tight text-gray-900">
            <?= $heading ?>
        </h1>
    </div>
</header>
```

### 5.2 `nav.php` — Active State via `urlIs()`

The navigation partial invokes `urlIs()` to conditionally apply an active CSS class to the anchor element corresponding to the currently active route.

```php
<a href='/'
    class="rounded-md px-3 py-2 text-sm font-medium
        <?= urlIs('/') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-white/5 hover:text-white' ?>">
    Home
</a>

<a href='/about'
    class="rounded-md px-3 py-2 text-sm font-medium
        <?= urlIs('/about') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-white/5 hover:text-white' ?>">
    About
</a>

<a href='/contact'
    class="rounded-md px-3 py-2 text-sm font-medium
        <?= urlIs('/contact') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-white/5 hover:text-white' ?>">
    Contact
</a>
```

The ternary expression embedded within `<?= ?>` resolves to one of two Tailwind class strings depending on whether the current URI matches the expected path.

---

## 6. The `$_SERVER` Superglobal & `urlIs()`

### 6.1 What is `$_SERVER`?

`$_SERVER` is a **superglobal** — a PHP-native associative array populated automatically by the runtime with server and HTTP request metadata. It is accessible from any scope without requiring explicit declaration or injection.

Relevant entries:

| Key                          | Description                                          | Example value    |
| ---------------------------- | ---------------------------------------------------- | ---------------- |
| `$_SERVER['REQUEST_URI']`    | Full request path including any query string         | `/about?ref=nav` |
| `$_SERVER['PHP_SELF']`       | Filesystem path of the currently executing script    | `/index.php`     |
| `$_SERVER['HTTP_HOST']`      | Host name extracted from the incoming HTTP request   | `localhost`      |
| `$_SERVER['REQUEST_METHOD']` | HTTP method used for the current request             | `GET`, `POST`    |

For active navigation link detection, `REQUEST_URI` is the appropriate key, as it directly reflects the URI the client navigated to.

### 6.2 `urlIs()` — `functions.php`

```php
<?php

function urlIs(string $value): bool
{
    return $_SERVER['REQUEST_URI'] === $value;
}
```

Performs a strict equality comparison between the current request URI and a provided expected path. Returns `true` on an exact match; `false` otherwise. The return value is consumed directly within the ternary expressions in `nav.php`.

### 6.3 Query String Consideration

Because `REQUEST_URI` includes the full query string, the URI `/about?ref=email` will **not** satisfy `urlIs('/about')`. For the purposes of this project, clean URLs are assumed and this edge case does not arise. For a more robust implementation, the path component should be isolated explicitly:

```php
function urlIs(string $value): bool
{
    return parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === $value;
}
```

---

## 7. How the Pieces Connect

```
Browser requests /about
        │
        ▼
about.php
  └─ sets $heading = 'About'
  └─ require 'views/about.view.php'
            │
            ├─ require 'partials/header.php'
            ├─ require 'partials/nav.php'    ← urlIs('/about') → true → active class applied
            ├─ require 'partials/banner.php' ← outputs $heading = 'About'
            ├─ <main> ... unique content ... </main>
            └─ require 'partials/footer.php'
```

---

## Section 2: Building a Router

> **Objective:** Replace direct file-based URL access with a centralised routing system backed by a single application entry point.

---

## 1. The Problem This Solves

Under the previous structure, pages were accessed by navigating directly to PHP files — `/about.php`, `/contact.php`, and so forth. This approach presents several concrete deficiencies:

- The internal file system layout is exposed to the client.
- There is no centralised interception point for validating or transforming requests.
- Unmatched URLs yield uncontrolled server-level error responses.

A router resolves these issues by directing **all incoming requests through a single entry point**, which assumes responsibility for determining what to load and execute.

---

## 2. Project File Structure (Updated)

```
project/
│
├── index.php           ← Sole entry point — bootstraps router.php
├── router.php          ← URI-to-controller mapping and 404 handling
├── functions.php
│
├── controllers/        ← Page logic files (relocated from project root)
│   ├── index.php
│   ├── about.php
│   └── contact.php
│
└── views/
    ├── index.view.php
    ├── about.view.php
    ├── contact.view.php
    ├── 404.php         ← Error view for unregistered routes
    │
    └── partials/
        ├── header.php
        ├── nav.php
        ├── banner.php
        └── footer.php
```

---

## 3. Entry Point — Root `index.php`

The root file now fulfils a single function: bootstrapping the application by loading shared utilities and the router.

```php
<?php
require 'functions.php';
require 'router.php';
```

Every request is directed to this file first, after which the router assumes full control of the dispatch process.

---

## 4. The Router — `router.php`

```php
<?php

$uri = parse_url($_SERVER['REQUEST_URI'])['path'];

$routes = [
    '/'        => 'controllers/index.php',
    '/about'   => 'controllers/about.php',
    '/contact' => 'controllers/contact.php',
];

function routeToController($uri, $routes) {
    if (array_key_exists($uri, $routes))
        require $routes[$uri];
    else
        abort();
}

function abort($code = 404) {
    http_response_code($code);
    require "views/{$code}.php";
    die();
}

routeToController($uri, $routes);
```

### 4.1 Extracting the Clean Path with `parse_url()`

```php
$uri = parse_url($_SERVER['REQUEST_URI'])['path'];
```

`parse_url()` decomposes a URL into its constituent components. Extracting only the `'path'` key discards the query string, yielding a normalised URI such as `/about` regardless of any appended parameters.

| `parse_url()` key | Value for `/about?ref=email` |
| ----------------- | ---------------------------- |
| `'path'`          | `/about`                     |
| `'query'`         | `ref=email`                  |

This also definitively resolves the edge case identified in the `urlIs()` implementation in Section 1.

### 4.2 The Routes Table

```php
$routes = [
    '/'        => 'controllers/index.php',
    '/about'   => 'controllers/about.php',
    '/contact' => 'controllers/contact.php',
];
```

A flat associative array serving as the routing table, mapping URI path strings to their corresponding controller file paths. Registering a new route requires only a single entry here and the creation of the associated controller — no further modifications are necessary.

### 4.3 `routeToController()` — Request Dispatch

```php
function routeToController($uri, $routes) {
    if (array_key_exists($uri, $routes))
        require $routes[$uri];
    else
        abort();
}
```

`array_key_exists()` determines whether the requested URI has a registered entry in the routes table. If so, the corresponding controller is loaded via `require`. If no match is found, `abort()` is invoked with the default status code of `404`.

### 4.4 `abort()` — Controlled Error Responses

```php
function abort($code = 404) {
    http_response_code($code);
    require "views/{$code}.php";
    die();
}
```

Three operations are executed in sequence:

1. **`http_response_code($code)`** — Assigns the appropriate HTTP status code to the response. Omitting this would result in error pages being transmitted with a `200 OK` status, which is semantically incorrect and has adverse implications for search engine indexing and API consumers.
2. **`require "views/{$code}.php"`** — Loads the error view corresponding to the given code. Double-quoted strings are used deliberately here — PHP performs variable interpolation within `{}` in double-quoted strings, so `$code = 404` resolves to `"views/404.php"`. A single-quoted string would treat `{$code}` as a literal sequence, resulting in a file not found error.
3. **`die()`** — Terminates execution immediately, preventing any further code from running after the error view has been rendered.

> `$code = 404` is a **default parameter**. An invocation of `abort()` produces a 404 response; `abort(500)` produces a 500. This makes the function applicable to any HTTP error code, provided the corresponding view file exists.

---

## 5. Request Flow Through the Router

```
Browser requests /about
        │
        ▼
index.php (entry point)
  └─ require 'router.php'
            │
            ├─ parse_url($_SERVER['REQUEST_URI'])['path']  →  '/about'
            ├─ array_key_exists('/about', $routes)  →  true
            └─ require 'controllers/about.php'
                        ├─ $heading = 'About'
                        └─ require 'views/about.view.php'
                                    └─ (assembles partials + content)


Browser requests /unknown
        │
        ▼
index.php → router.php
  └─ array_key_exists('/unknown', $routes)  →  false
  └─ abort()
        ├─ http_response_code(404)
        ├─ require 'views/404.php'
        └─ die()
```

---

## Section 3: Database Connection with PDO

> **Objective:** Implement a `Database` class encapsulating PDO, with configuration isolated in a dedicated file, dynamic DSN construction, and prepared statements enforced as the exclusive query mechanism to eliminate SQL injection vulnerabilities.

---

## 1. Project File Structure (Updated)

```
project/
│
├── index.php       ← Now requires Database.php and config.php
├── router.php
├── functions.php
├── Database.php    ← NEW: PDO wrapper class
├── config.php      ← NEW: Application configuration
│
├── controllers/
└── views/
```

---

## 2. PDO

**PDO (PHP Data Objects)** is PHP's built-in database abstraction layer. It exposes a unified, database-agnostic API compatible with MySQL, PostgreSQL, SQLite, and other supported engines — changing the underlying database requires only a modification to the connection string, not to the query code.

| Feature               | Description                                                         |
| --------------------- | ------------------------------------------------------------------- |
| Database-agnostic     | Single API surface for MySQL, PostgreSQL, SQLite, and others        |
| Prepared statements   | Native protection against SQL injection attacks                     |
| Configurable fetch modes | Controls the structure of returned result sets                  |
| Exception-based errors | Enables structured, predictable error handling                    |

---

## 3. Configuration File — `config.php`

```php
<?php

return [
    'database' => [
        'host'    => 'localhost',
        'port'    => 3306,
        'dbname'  => 'myapp',
        'charset' => 'utf8mb4',
    ]

    // Additional service configurations may be added here
];
```

`config.php` uses a `return` statement to expose its contents as a plain PHP array — no output is produced and no global variables are assigned. The caller receives the value directly upon inclusion:

```php
$config = require 'config.php';
// $config['database'] is the database-specific configuration slice
```

Centralising all configuration parameters here ensures that host, database name, or charset changes are confined to a single location.

---

## 4. The `Database` Class — `Database.php`

```php
<?php

class Database
{
    public $connection;

    public function __construct($config, $username = 'root', $password = '')
    {
        $dsn = 'mysql:' . http_build_query($config, '', ';');

        $this->connection = new PDO($dsn, $username, $password, [
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    }

    public function query($query, $params = [])
    {
        $statement = $this->connection->prepare($query);

        $statement->execute($params);

        return $statement;
    }
}
```

### 4.1 DSN Construction with `http_build_query()`

A **DSN (Data Source Name)** is the connection string passed to PDO to identify and locate the target database:

```
mysql:host=localhost;port=3306;dbname=myapp;charset=utf8mb4
```

Rather than constructing this string statically, `http_build_query()` assembles it dynamically from the configuration array:

```php
$dsn = 'mysql:' . http_build_query($config, '', ';');
```

Introducing or modifying a DSN parameter (e.g., `unix_socket`) requires only an update to `config.php` — the `Database` class itself requires no modification.

### 4.2 PDO Constructor Options

```php
$this->connection = new PDO($dsn, $username, $password, [
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);
```

The fourth argument accepts a driver options array. `PDO::FETCH_ASSOC` configures all result rows to be returned as associative arrays keyed by column name, rather than the default behaviour which returns both numeric and associative indices simultaneously — an approach that doubles memory consumption with no practical benefit.

### 4.3 The `query()` Method & Prepared Statements

```php
public function query($query, $params = [])
{
    $statement = $this->connection->prepare($query);

    $statement->execute($params);

    return $statement;
}
```

Rather than executing a raw query string, this method enforces the **prepare → execute** pattern:

1. **`prepare($query)`** — Transmits the query template to the database server, which parses and compiles it. Placeholders (`?`) denote positions where parameter values will be bound.
2. **`execute($params)`** — Transmits the parameter values separately. The server substitutes them into the pre-compiled query structure.

The method returns a `PDOStatement` object, allowing the caller to retrieve results via `->fetchAll()` for the complete result set or `->fetch()` for a single row.

---

## 5. SQL Injection

### 5.1 The Vulnerability

SQL injection occurs when user-supplied input is directly interpolated into a query string:

```php
// VULNERABLE — never construct queries this way
$id = $_GET['id'];
$posts = $db->connection->query("SELECT * FROM posts WHERE id = $id");
```

A well-formed request to `/posts?id=1` produces a syntactically valid query. However, the payload `/posts?id=1 OR 1=1` transforms the query into:

```sql
SELECT * FROM posts WHERE id = 1 OR 1=1
```

This returns every row in the table. A more destructive payload such as `1; DROP TABLE posts--` could permanently destroy the table. Because user input is concatenated directly into the query string prior to parsing, the database engine cannot distinguish legitimate SQL from injected SQL.

### 5.2 Mitigation via Prepared Statements

```php
// SECURE — using parameterised prepared statements
$id = $_GET['id'];
$posts = $db->query('SELECT * FROM posts WHERE id = ?', [$id])->fetchAll();
```

The `?` placeholder is never subject to string concatenation. The query template and its parameter values are transmitted to the database server as **discrete, separate operations**. By the time the parameter value is received, the server has already completed parsing the query structure — the `?` position is treated as a typed data slot, not as executable SQL. Consequently, regardless of what the user submits as input, it cannot alter the structure or semantics of the query.

> The governing principle is unambiguous: **user input must never be concatenated into a SQL string**. Parameters must invariably be passed through `execute()` via `?` placeholders.

---

## 6. Application Bootstrap — Root `index.php`

```php
<?php

require 'functions.php';
require 'Database.php';
require 'router.php';

$config = require 'config.php';

$db = new Database($config['database']);
```

The load sequence is intentional and must be preserved:

1. `functions.php` — application-wide utility functions required throughout.
2. `Database.php` — the class definition must be loaded before instantiation can occur.
3. `router.php` — the central routing logic that governs request dispatch.
4. `config.php` — returns the configuration array into `$config`.
5. `new Database($config['database'])` — only the `'database'` configuration slice is passed to the constructor; all other configuration sections remain encapsulated from this component.

The resulting `$db` instance is accessible to any controller subsequently loaded by the router, as `require` shares the calling scope.

---