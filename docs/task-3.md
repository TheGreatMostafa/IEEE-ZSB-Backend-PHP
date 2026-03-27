# Task 3 — What I Learned: Namespaces, Autoloading, Routing, Container & Auth

---

## how my project is actually structured (not the generic one)

before i get into anything, my project ended up looking like this:

```
IEEE-ZSB-Backend-PHP/
├── public/
│   └── index.php               ← only entry point
├── Core/
│   ├── App.php
│   ├── Authenticator.php
│   ├── Container.php
│   ├── Database.php
│   ├── functions.php
│   ├── Response.php
│   ├── Router.php
│   ├── Validator.php
│   └── Middleware/
│       ├── Authenticated.php   ← (my version calls it Authenticated, not Auth)
│       ├── Guest.php
│       └── Middleware.php
├── Http/
│   ├── Forms/
│   │   └── LoginForm.php
│   └── controllers/
│       ├── about.php
│       ├── contact.php
│       ├── index.php
│       ├── notes/
│       │   ├── create.php
│       │   ├── destroy.php
│       │   ├── edit.php
│       │   ├── index.php
│       │   ├── show.php
│       │   ├── store.php
│       │   └── update.php
│       ├── registration/
│       │   ├── create.php
│       │   └── store.php
│       └── session/
│           ├── create.php
│           ├── destroy.php
│           └── store.php
├── views/
├── bootstrap.php
├── config.php
└── routes.php
```

two big differences from the generic example the docs showed:
1. my controllers live in `Http/controllers/` not just `controllers/`
2. my auth middleware is called `Authenticated.php`, not `Auth.php`
3. i have a `Core/Authenticator.php` and `Http/Forms/LoginForm.php` that i added myself

important: the browser can only reach `public/`. everything else is invisible to it.

---

## 1. why we moved everything into `public/`

before this, the server ran from the root folder:
```bash
php -S localhost:8888
```

that meant anyone could visit:
```
http://localhost:8888/config.php       ← database password sitting there
http://localhost:8888/Core/Database.php  ← connection logic exposed
```

the fix was to only expose `public/` and nothing else:
```bash
php -S localhost:8888 -t public/
```

`-t public/` is the flag that matters. it tells PHP: "treat `public/` as the web root, not where you launched from."

now if someone visits `http://localhost:8888/Core/Database.php`, the server looks for that path inside `public/`, finds nothing, returns 404. the file still exists on disk but the web has no idea.

---

## 2. `public/index.php` — line by line

this is literally the only file the browser ever hits. every request — `/notes`, `/login`, `/about` — starts here.

```php
// public/index.php

<?php

session_start();

define('BASE_PATH', __DIR__ . '/../');

require BASE_PATH . 'Core/functions.php';

spl_autoload_register(function ($class) {
    $class = str_replace('\\', DIRECTORY_SEPARATOR, $class);
    require base_path($class . '.php');
});

require base_path('bootstrap.php');
require base_path('Core/Router.php');
```

---

### `session_start();`

has to be the first thing. before any output, before anything else.

`session_start()` does two things depending on the situation:
- if the browser is sending a `PHPSESSID` cookie: PHP finds the matching session file on the server and loads it into `$_SESSION`
- if there's no cookie: PHP creates a new session file and tells the browser to store an ID in a cookie

`$_SESSION` is then available everywhere — any file, any function, without passing it around.

---

### `define('BASE_PATH', __DIR__ . '/../');`

i used `define()` here instead of `const`. both work for constants, but `define()` can go anywhere in code while `const` has to be at the top level outside functions. just a personal choice.

`__DIR__` is a PHP magic constant — PHP fills it in automatically with the absolute path of the folder the current file lives in. since this file is in `public/`, `__DIR__` gives us something like:
```
/home/me/IEEE-ZSB-Backend-PHP/public
```

adding `'/../'` goes one level up:
```
/home/me/IEEE-ZSB-Backend-PHP/public  +  /../  =  /home/me/IEEE-ZSB-Backend-PHP/
```

so `BASE_PATH` now points to the project root. any file anywhere in the project can use `BASE_PATH` to build absolute paths that always work.

---

### `require BASE_PATH . 'Core/functions.php';`

`require` loads and runs a PHP file. if the file doesn't exist, PHP stops with a fatal error (unlike `include` which just warns and continues).

we load `functions.php` before anything else because it defines `base_path()` — the helper we use on literally every line after this. can't call it before it's defined.

---

### `spl_autoload_register(function ($class) { ... });`

this is the autoloader. understanding this was a big moment for me.

**the problem it solves:**
every file that used `Database` had to manually write:
```php
require 'Core/Database.php';
```
forget one and you get "class not found". add a new class and you have to add a require everywhere that uses it.

**how `spl_autoload_register()` works:**
you give it a function. PHP will call that function automatically whenever code tries to use a class that hasn't been loaded yet. you get one chance to load the file before PHP gives up.

the `$class` parameter PHP passes in is the full class name — for example `Core\Database`.

---

### `$class = str_replace('\\', DIRECTORY_SEPARATOR, $class);`

`$class` at this point is `'Core\Database'` (with a backslash — the namespace separator).

but on the file system, paths use `/` on Mac/Linux or `\` on Windows. we need to turn the namespace into a valid file path.

`str_replace(what_to_find, what_to_replace_with, where_to_look)`:
- `'\\'` — one backslash (in PHP strings `\\` means a literal `\`)
- `DIRECTORY_SEPARATOR` — PHP constant, automatically `/` on Mac/Linux and `\` on Windows
- `$class` — the string we're transforming

result: `'Core\Database'` becomes `'Core/Database'` on Mac/Linux.

---

### `require base_path($class . '.php');`

appends `.php` to the path: `'Core/Database'` → `'Core/Database.php'`

then `base_path()` sticks `BASE_PATH` in front: → `/home/me/IEEE-ZSB-Backend-PHP/Core/Database.php`

`require` loads it. the class is now available.

the reason this works is that **my namespace structure matches my folder structure**. `Core\Database` lives in `Core/Database.php`. `Core\Middleware\Authenticated` lives in `Core/Middleware/Authenticated.php`. the autoloader can always figure out the path just from the class name.

---

### `require base_path('bootstrap.php');`

`bootstrap.php` is where the service container gets set up. it runs once at startup, wires everything together. more on this later.

---

## 3. `Core/functions.php` — the helpers i use everywhere

### `base_path()`

```php
// Core/functions.php

function base_path(string $path): string
{
    return BASE_PATH . $path;
}
```

**`string $path`** — the type hint before `$path` means PHP will throw an error if you accidentally pass something that isn't a string. catches mistakes early.

**`: string`** after the closing `)` — the return type. this function will always return a string. same idea — makes the contract explicit.

this one function is why reorganizing folders doesn't break paths. any controller, any view, any file can call `base_path('Core/Database.php')` and always get the right absolute path.

---

### `view()`

```php
// Core/functions.php

function view(string $path, array $attributes = []): void
{
    extract($attributes);
    require base_path("views/{$path}");
}
```

**`array $attributes = []`**
- `array` type hint — must be an array
- `= []` default value — if you call `view('about.view.php')` with no second argument, `$attributes` is just an empty array. makes the second argument optional.

**`: void`** — this function returns nothing. it just loads a file. declaring `void` is honest about that.

**`extract($attributes)`**
this is the clever part. `extract()` takes every key in an associative array and creates a local variable with that name.

```php
// if you call:
view('notes/index.view.php', ['heading' => 'My Notes', 'notes' => $notesArray]);

// inside the function, extract() creates:
// $heading = 'My Notes'
// $notes = $notesArray
```

then the `require` runs the view file inside this function's scope. so `$heading` and `$notes` are just... there, in the view.

**`"views/{$path}"`** — double-quoted string with `{$path}` interpolated. curly braces around the variable make it clear where the variable name starts and ends when it's right next to other text.

---

### `authorize()`

```php
// Core/functions.php

function authorize($condition, $status = Response::FORBIDDEN)
{
    if (!$condition) {
        abort($status);
    }
}
```

same function from task 2, still being used. pass it a boolean. if it's false, abort.

`Response::FORBIDDEN` is the class constant `403` from `Core/Response.php`:
```php
// Core/Response.php

class Response {
    const FORBIDDEN = 403;
    const NOT_FOUND = 404;
}
```

named constants instead of magic numbers. `Response::FORBIDDEN` is clearer than just writing `403` everywhere.

---

## 4. namespaces — giving classes a "last name"

all my Core classes have `namespace Core;` at the top:

```php
// Core/Database.php

<?php

namespace Core;

class Database {
    public $connection;
    public $statement;

    public function __construct($config, $user='root', $password='password123') {
        $dsn = 'mysql:' . http_build_query($config, "", ';');
        $this->connection = new PDO($dsn, $user, $password, [
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    }

    public function query($query, $params = []) {
        $this->statement = $this->connection->prepare($query);
        $this->statement->execute($params);
        return $this;
    }

    public function get() {
        return $this->statement->fetchAll();
    }

    public function find() {
        return $this->statement->fetch();
    }

    public function findOrFail() {
        $result = $this->find();
        if (! $result) {
            abort(Response::NOT_FOUND);
        }
        return $result;
    }
}
```

**`namespace Core;`** — has to be the very first line after `<?php`. declares that `Database` now has the full name `Core\Database`.

why does this matter? if i install any package that also has a `Database` class, they'd collide without namespaces. with namespaces they're `Core\Database` and `VendorPackage\Database` — completely different things to PHP.

**using it from a controller:**

```php
// option 1: write the full name
$db = new Core\Database($config);

// option 2: import once at the top, use short name
use Core\Database;
$db = new Database($config);
```

`use Core\Database;` doesn't load anything — the autoloader handles loading. `use` just sets up an alias so i can write `Database` and PHP knows i mean `Core\Database`.

---

## 5. `Core/Router.php` — matching URL and HTTP method both

```php
// Core/Router.php

<?php

namespace Core;

class Router
{
    protected $routes = [];

    public function add($method, $uri, $controller)
    {
        $this->routes[] = [
            'uri'        => $uri,
            'controller' => $controller,
            'method'     => $method,
        ];
    }

    public function get($uri, $controller)    { $this->add('GET',    $uri, $controller); }
    public function post($uri, $controller)   { $this->add('POST',   $uri, $controller); }
    public function delete($uri, $controller) { $this->add('DELETE', $uri, $controller); }
    public function patch($uri, $controller)  { $this->add('PATCH',  $uri, $controller); }
    public function put($uri, $controller)    { $this->add('PUT',    $uri, $controller); }

    public function route($uri, $method)
    {
        foreach ($this->routes as $route) {
            if ($route['uri'] === $uri && $route['method'] === strtoupper($method)) {
                Middleware::resolve($route['middleware'] ?? null);
                return require base_path($route['controller']);
            }
        }
        $this->abort();
    }

    protected function abort($code = 404)
    {
        http_response_code($code);
        require base_path("views/{$code}.php");
        die();
    }

    public function only($key)
    {
        $this->routes[array_key_last($this->routes)]['middleware'] = $key;
        return $this;
    }
}
```

---

### `protected $routes = []`

`protected` — only this class and classes extending it can access it. external code can't mess with the routes array directly.

`= []` — starts as an empty array. routes get added one by one when `routes.php` runs.

---

### `$this->routes[] = [...]`

`$this` — the current Router object. `->routes` accesses its `$routes` property. `[]` with no index appended means "add this as the next element". PHP figures out the next index automatically.

each stored route is an associative array with three keys. using named keys (`'uri'`, `'method'`, `'controller'`) instead of numeric ones (`[0]`, `[1]`, `[2]`) makes the code self-documenting.

---

### the shortcut methods

```php
// Core/Router.php

public function get($uri, $controller)    { $this->add('GET',    $uri, $controller); }
public function post($uri, $controller)   { $this->add('POST',   $uri, $controller); }
```

they just call `add()` with the method string hardcoded. the reason to have them is readability:
```php
// without shortcuts:
$router->add('GET', '/notes', 'Http/controllers/notes/index.php');

// with shortcuts:
$router->get('/notes', 'Http/controllers/notes/index.php');
```
the second line reads like English. you scan `routes.php` and instantly know what each line does.

---

### `route()` — finding and running the right controller
// Core/Router.php


```php
public function route($uri, $method)
{
    foreach ($this->routes as $route) {
        if ($route['uri'] === $uri && $route['method'] === strtoupper($method)) {
            Middleware::resolve($route['middleware'] ?? null);
            return require base_path($route['controller']);
        }
    }
    $this->abort();
}
```

**`foreach ($this->routes as $route)`** — loops through every registered route. `$route` is one entry at a time.

**`$route['uri'] === $uri`** — `===` is strict equality. checks both value AND type. in PHP `==` has weird type juggling behavior (like `0 == 'anything'` is true). `===` avoids that.

**`&&`** — both conditions must be true. URL must match AND method must match. this is the key improvement over the old router — GET `/note` and DELETE `/note` are now separate things.

**`strtoupper($method)`** — converts whatever case the method string is in to uppercase. the method comes from `$_POST['_method']` which someone typed. `'patch'` and `'PATCH'` and `'Patch'` all become `'PATCH'` and match correctly.

**`$route['middleware'] ?? null`** — if no middleware key exists on this route (routes without `->only()`), we pass `null`. `Middleware::resolve(null)` just returns immediately. routes without middleware are unaffected.

**`return require base_path($route['controller'])`** — loads and runs the controller. `return` here exits the `foreach` loop immediately once a match is found. no need to check the rest of the routes.

---

### `abort()` — when nothing matches
// Core/Router.php


```php
protected function abort($code = 404)
{
    http_response_code($code);
    require base_path("views/{$code}.php");
    die();
}
```

**`$code = 404`** — default parameter. `$this->abort()` gives 404. `$this->abort(403)` gives 403.

**`http_response_code($code)`** — sets the actual HTTP status code of the response. without this PHP sends `200 OK` even when showing a 404 page — which is wrong. browsers and search engines rely on these codes.

**`protected`** — only the Router itself can call this. not public because external code shouldn't be triggering error pages directly.

**`die()`** — stops execution entirely. nothing after the error page runs.

---

### method spoofing — faking DELETE and PATCH from a form

HTML forms only support `GET` and `POST`. `method="DELETE"` literally doesn't exist in HTML.

the trick: submit as POST but include a hidden field telling the server the real intended method:
!-- views/notes/show.view.php (example) -->
<
```html
<form method="POST" action="/note">
    <input type="hidden" name="_method" value="DELETE">
    <input type="hidden" name="id" value="<?= $note['id'] ?>">
    <button>Delete</button>
</form>
```

**`type="hidden"`** — the field exists in the form data but doesn't show visually.

**`<?= $note['id'] ?>`** — shorthand for `<?php echo $note['id']; ?>`. outputs the note's ID so the controller knows which one to delete.

in `public/index.php`:
```php
// public/index.php

$method = $_POST['_method'] ?? $_SERVER['REQUEST_METHOD'];
```

**`$_POST['_method']`** — reads the hidden field.

**`??`** — null coalescing operator. if `$_POST['_method']` doesn't exist (it won't on GET requests), use the right side instead.

**`$_SERVER['REQUEST_METHOD']`** — the actual HTTP method. `GET`, `POST`, etc.

so the router sees `DELETE` and dispatches to `Http/controllers/notes/destroy.php` correctly.

---

### `only()` — attaching middleware to routes

```php
// Core/Router.php

public function only($key)
{
    $this->routes[array_key_last($this->routes)]['middleware'] = $key;
    return $this;
}
```

**`array_key_last($this->routes)`** — returns the last key in the array. since routes are always appended, the last key is always the one we just registered.

**`$this->routes[...]['middleware'] = $key`** — adds a `middleware` key to that route's array.

**`return $this`** — returns the Router object itself. this is what makes chaining possible:
```php
$router->get('/notes', 'Http/controllers/notes/index.php')->only('auth');
```
`->get()` returns `$this` (the Router), then `->only('auth')` is called on that same Router. without `return $this`, chaining would fail.

---

## 6. `Core/Container.php` and `Core/App.php`

### the problem

before, every controller that needed the database did this:
```php
$config = require base_path('config.php');
$db = new Database($config['database']);
```

copy-pasted everywhere. if the `Database` constructor ever changed, i'd have to update every single controller.

### `Container.php`

```php
// Core/Container.php

<?php

namespace Core;

use Exception;

class Container
{
    protected $bindings = [];

    public function bind($key, $resolver)
    {
        $this->bindings[$key] = $resolver;
    }

    public function resolve($key)
    {
        if (!array_key_exists($key, $this->bindings)) {
            throw new Exception("No matching binding found for {$key}");
        }

        $resolver = $this->bindings[$key];
        return call_user_func($resolver);
    }
}
```

**`protected $bindings = []`** — associative array. keys are service names (like `'Core\Database'`), values are factory closures.

**`bind($key, $resolver)`** — stores a factory function under a key. the factory is NOT called here. we're just saving the recipe.

**`array_key_exists($key, $this->bindings)`** — checks if `$key` is registered. safer than `isset()` here because `isset()` returns false if the value is `null`, which would mask bugs.

**`throw new Exception(...)`** — if someone asks for something that was never registered, crash loudly with a useful message. better than silently returning `null` and confusing yourself later.

**`call_user_func($resolver)`** — calls the stored closure. this is where the object actually gets built — not when registered, but when asked for. called **lazy instantiation** — if nothing ever asks for `Database`, the database connection is never opened.

---

### `App.php` — the global door

```php
// Core/App.php

<?php

namespace Core;

class App
{
    protected static $container;

    public static function setContainer($container)
    {
        static::$container = $container;
    }

    public static function container()
    {
        return static::$container;
    }

    public static function bind($key, $resolver)
    {
        static::container()->bind($key, $resolver);
    }

    public static function resolve($key)
    {
        return static::container()->resolve($key);
    }
}
```

**`protected static $container`** — `static` means this belongs to the class itself, not any instance. there's no `new App()` anywhere. the container is stored on the class.

**`static::$container`** vs `self::$container` — both would work here. `static::` is "late static binding", meaning if a subclass extends `App` it refers to the subclass. more future-proof habit.

**why make `App` static at all?** because we want to access the container from any file, anywhere. if it were a regular object we'd have to pass it into every function. static means any controller just calls:
```php
$db = App::resolve(Database::class);
```

**`Database::class`** — magic constant. returns the fully qualified class name as a string: `'Core\Database'`. safer than typing the string manually because if the class is renamed, `Database::class` updates automatically.

---

### `bootstrap.php` — one place to configure everything

```php
// bootstrap.php

<?php

use Core\App;
use Core\Container;
use Core\Database;

$container = new Container();

$container->bind('Core\Database', function () {
    $config = require base_path('config.php');
    return new Database($config['database']);
});

App::setContainer($container);
```

this runs once at startup (required from `public/index.php`).

the closure doesn't capture any outer variables. it reads `config.php` fresh every time it's called. self-contained.

after this file runs, every controller in the entire project just calls `App::resolve(Database::class)` — no more copy-pasted config setup.

---

## 7. `Core/Middleware/` — my three middleware files

### `Authenticated.php` (mine is called this, not `Auth.php`)

```php
// Core/Middleware/Authenticated.php

<?php

namespace Core\Middleware;

class Authenticated
{
    public function handle()
    {
        if (!($_SESSION['user'] ?? false)) {
            header('location: /');
            exit();
        }
    }
}
```

**`namespace Core\Middleware`** — nested namespace. the file lives at `Core/Middleware/Authenticated.php`. the autoloader handles this — `Core\Middleware\Authenticated` → `Core/Middleware/Authenticated.php`.

**`$_SESSION['user'] ?? false`** — if `$_SESSION['user']` was never set (user never logged in), accessing it directly throws an "undefined index" notice. `?? false` safely returns `false` if the key doesn't exist.

**`!(...)`** — negation. "if there is NO user in session → redirect". the controller only runs if the condition fails (user IS logged in).

---

### `Guest.php`
// Core/Middleware/Guest.php


```php
<?php

namespace Core\Middleware;

class Guest
{
    public function handle()
    {
        if ($_SESSION['user'] ?? false) {
            header('location: /');
            exit();
        }
    }
}
```

no `!` this time. "if there IS a user in session → redirect." logged-in users shouldn't visit `/login` or `/register`.

---

### `Middleware.php` — the resolver

```php
// Core/Middleware/Middleware.php

<?php

namespace Core\Middleware;

class Middleware
{
    public const MAP = [
        'guest'         => Guest::class,
        'authenticated' => Authenticated::class,
    ];

    public static function resolve($key)
    {
        if (!$key) {
            return;
        }

        $middleware = static::MAP[$key] ?? false;

        if (!$middleware) {
            throw new \Exception("No matching middleware found for key '{$key}'.");
        }

        (new $middleware)->handle();
    }
}
```

**`public const MAP`** — a class constant (not a property). `public` so other code can read it. `const` so it can never be changed at runtime.

**`Guest::class` and `Authenticated::class`** — magic constants again. returns the full class name string. if i rename a class it automatically updates here.

**`if (!$key) { return; }`** — if `$key` is `null` (route has no middleware), just return. routes without `->only(...)` are silently skipped.

**`static::MAP[$key] ?? false`** — looks up the key. if someone typed `->only('typo')`, `$middleware` becomes `false`.

**`throw new \Exception(...)`** — the `\` before `Exception` matters here. we're inside the `Core\Middleware` namespace. without `\`, PHP would look for `Core\Middleware\Exception` which doesn't exist. `\Exception` means "the global PHP Exception class".

**`(new $middleware)->handle()`** — `$middleware` is a string like `'Core\Middleware\Authenticated'`. `new $middleware` creates an instance of that class from a string — PHP supports this. `->handle()` calls the method. all in one line without storing the instance.

---

## 8. sessions and auth

### how sessions work

HTTP is stateless. each request is independent. the server doesn't know if request #47 is the same person as request #46.

sessions solve this. `session_start()` (called at the top of `index.php`) either creates a new session file on the server or finds an existing one using a cookie the browser sends back called `PHPSESSID`. the browser only ever stores the ID. all the actual data lives on the server.

```
Browser                              Server
  |── GET /notes ──────────────────>|
  |   Cookie: PHPSESSID=abc123      |── finds session file for abc123
  |                                 |── $_SESSION['user'] = ['id' => 4, ...]
  |<── 200 OK ──────────────────────|
```

`$_SESSION` is a superglobal — available everywhere, no passing around needed.

---

### `login()` in `Core/functions.php`
// Core/functions.php


```php
function login($user)
{
    $_SESSION['user'] = [
        'email' => $user['email'],
        'id'    => (int) $user['id'],
    ];

    session_regenerate_id(true);
}
```

**`$_SESSION['user'] = [...]`** — stores the logged-in user. from this point forward, any file can read `$_SESSION['user']` to know who's logged in.

**only `email` and `id`** — we store the minimum needed. never the password. never the whole row.

**`(int) $user['id']`** — type cast. database drivers sometimes return numbers as strings. casting to `int` guarantees it's always an integer so comparisons like `$note['user_id'] === $_SESSION['user']['id']` work correctly with `===` strict comparison (which checks type too).

**`session_regenerate_id(true)`** — generates a new session ID immediately after login and deletes the old one (`true` does the deletion). this blocks session fixation attacks — where an attacker plants a known session ID in the victim's browser before login, then uses it once the victim authenticates.

---

### `logout()` in `Core/functions.php`

```php
// Core/functions.php

function logout()
{
    $_SESSION = [];
    session_destroy();

    $params = session_get_cookie_params();
    setcookie('PHPSESSID', '', time() - 3600, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
```

three steps. all three are needed:

**`$_SESSION = []`** — clears the session data from memory for this current request. if anything below tries to read `$_SESSION['user']`, it gets nothing.

**`session_destroy()`** — deletes the session file from the server. the ID in the browser cookie is now invalid — there's no matching file anymore.

**`session_get_cookie_params()`** — gets the cookie settings (path, domain, secure flag, httponly flag). we need these to expire the cookie with the exact same parameters it was created with. if we use different params, the browser treats it as a different cookie and the original stays.

**`setcookie('PHPSESSID', '', time() - 3600, ...)`** — sends a `Set-Cookie` header telling the browser to delete the cookie. `time() - 3600` is the current Unix timestamp minus 3600 seconds (one hour). setting expiry in the past tells the browser to immediately discard the cookie.

skip step 1: `$_SESSION` still has data for this request. skip step 2: the cookie still matches a valid server file. skip step 3: the browser keeps sending the old ID on every request. you need all three.

---

### password hashing

```php
// Core/Validator.php

public static function email($value)
{
    filter_var($value, FILTER_VALIDATE_EMAIL);
}
```

wait — i noticed my `Validator::email()` is missing the `return` keyword. it calls `filter_var()` but doesn't return the result. that's a bug. i should fix it to:
```php
// Core/Validator.php

public static function email($value)
{
    return filter_var($value, FILTER_VALIDATE_EMAIL);
}
```

for passwords — `password_hash()` and `password_verify()`:

```php
// Http/controllers/registration/store.php

$db->query('INSERT INTO users (email, password) VALUES (:email, :password)', [
    'email'    => $email,
    'password' => password_hash($password, PASSWORD_BCRYPT),
]);
```

**`password_hash($password, PASSWORD_BCRYPT)`** — bcrypt takes the raw password, generates a random salt automatically, hashes them together. returns one string that contains the algorithm + salt + hash. you store that string.

**why bcrypt is intentionally slow** — each guess takes real computation. if an attacker steals the database, they can't brute-force billions of guesses per second. each guess costs real time.

```php
// Http/controllers/session/store.php

if (password_verify($password, $user['password'])) {
    login($user);
    header('location: /');
    exit();
}
```

**`password_verify($plaintext, $storedHash)`** — extracts the salt from the stored hash string, re-runs bcrypt with that salt on the submitted password, checks if the result matches. returns `true` or `false`. you never touch the salt yourself.

---

### the vague error message on login

```php
// Http/controllers/session/store.php

$user = $db->query('SELECT * FROM users WHERE email = :email', [
    'email' => $email
])->find();

if ($user) {
    if (password_verify($password, $user['password'])) {
        login($user);
        header('location: /');
        exit();
    }
}

return view('session/create.view.php', [
    'errors' => [
        'email' => 'No matching account found for that email address and password.'
    ]
]);
```

both "email not found" and "wrong password" show the exact same message. if you showed different messages, an attacker could use the login form to check which emails are registered. one vague message gives them nothing.

---

## 9. CRUD controllers — the pattern that repeats

every controller follows: **fetch → authorize → validate → act → redirect**

### `Http/controllers/notes/store.php` — creating a note

// Http/controllers/notes/store.php

```php
<?php

use Core\App;
use Core\Database;
use Core\Validator;

$db = App::resolve(Database::class);
$errors = [];

if (!Validator::string($_POST['body'], 1, 1000)) {
    $errors['body'] = 'A body of no more than 1000 characters is required.';
}

if (!empty($errors)) {
    return view('notes/create.view.php', [
        'heading' => 'New Note',
        'errors'  => $errors,
    ]);
}

$db->query('INSERT INTO notes (body, user_id) VALUES (:body, :user_id)', [
    'body'    => $_POST['body'],
    'user_id' => $_SESSION['user']['id'],
]);

header('location: /notes');
exit();
```

**`App::resolve(Database::class)`** — gets the database from the container. no config setup, no new PDO, nothing. just ask and receive.

**`$errors = []`** — starts empty. passing an empty array (not null) means the view can always safely check `$errors['body']` without worrying about null errors.

**`Validator::string($_POST['body'], 1, 1000)`** — checks the submitted body is between 1 and 1000 characters. `$_POST['body']` is the text from the textarea.

**`!Validator::string(...)`** — `!` negates it. "if the body is NOT valid".

**`:body` and `:user_id` in SQL** — named placeholders. we never put `$_POST['body']` directly in a SQL string (that's SQL injection). these are sent separately and PDO escapes them.

**`return view(...)`** — `return` stops the controller from continuing. the redirect never runs if there are errors.

**`header('location: /notes')`** — redirect to the notes list. why redirect instead of rendering? if we rendered after a POST, hitting refresh re-submits the form and creates a duplicate note. redirecting makes the browser do a GET request — refresh just reloads the list.

**`exit()`** — stops all execution after the redirect header. without it, PHP would keep running code below.

---

### `Http/controllers/notes/destroy.php` — deleting a note

```php
// Http/controllers/notes/destroy.php

<?php

use Core\App;
use Core\Database;

$db = App::resolve(Database::class);

$note = $db->query('SELECT * FROM notes WHERE id = :id', [
    'id' => $_POST['id']
])->findOrFail();

authorize($note['user_id'] === $_SESSION['user']['id']);

$db->query('DELETE FROM notes WHERE id = :id', [
    'id' => $_POST['id']
]);

header('location: /notes');
exit();
```

**`$_POST['id']`** — comes from `<input type="hidden" name="id">` in the delete form. it arrives as POST because the form uses `method="POST"` (with `_method="DELETE"` for spoofing).

**`->findOrFail()`** — if the note ID doesn't exist in the database, this calls `abort(Response::NOT_FOUND)` and stops everything. we never reach the DELETE query.

**`authorize($note['user_id'] === $_SESSION['user']['id'])`** — `===` comparison returns `true` or `false`. if `false`, `authorize()` calls `abort(403)`. we always check ownership. someone could change the ID in the form to try to delete someone else's note.

**why SELECT before DELETE** — we need `$note['user_id']` to check ownership. can't get it without fetching first.

---

## routes.php — how everything connects
// routes.php


```php
<?php

$router->get('/', 'Http/controllers/index.php');
$router->get('/about', 'Http/controllers/about.php');
$router->get('/contact', 'Http/controllers/contact.php');

$router->get('/notes', 'Http/controllers/notes/index.php')->only('authenticated');
$router->get('/notes/create', 'Http/controllers/notes/create.php')->only('authenticated');
$router->post('/notes', 'Http/controllers/notes/store.php')->only('authenticated');
$router->get('/note', 'Http/controllers/notes/show.php')->only('authenticated');
$router->get('/note/edit', 'Http/controllers/notes/edit.php')->only('authenticated');
$router->patch('/note', 'Http/controllers/notes/update.php')->only('authenticated');
$router->delete('/note', 'Http/controllers/notes/destroy.php')->only('authenticated');

$router->get('/register', 'Http/controllers/registration/create.php')->only('guest');
$router->post('/register', 'Http/controllers/registration/store.php')->only('guest');

$router->get('/login', 'Http/controllers/session/create.php')->only('guest');
$router->post('/session', 'Http/controllers/session/store.php')->only('guest');
$router->delete('/session', 'Http/controllers/session/destroy.php')->only('authenticated');
```

notice i use `->only('authenticated')` not `->only('auth')` because my middleware class is `Authenticated`, not `Auth`. the string maps to `Middleware::MAP['authenticated']` which points to `Authenticated::class`.

`/note` now has three separate routes — GET, PATCH, DELETE — each pointing to a completely different controller file. this was impossible with the old URI-only router.

---

## quick reference — things i want to remember

| thing | what it does |
|---|---|
| `-t public/` in server command | sets web root — only `public/` is browser-accessible |
| `define('BASE_PATH', __DIR__ . '/../')` | absolute path anchor for the whole project |
| `session_start()` | must be first line — creates or resumes a session |
| `spl_autoload_register()` | auto-loads class files on demand — no manual requires |
| `namespace Core;` | gives the class a "last name" — prevents name collisions |
| `use Core\Database;` | imports so you can write `Database` instead of `Core\Database` |
| `Database::class` | returns `'Core\Database'` as a string — auto-updates if class is renamed |
| `extract($attributes)` | turns array keys into local variables in the view |
| `App::resolve(Database::class)` | gets a configured instance from the container |
| `call_user_func($resolver)` | calls the stored factory closure — builds the object |
| `(int) $user['id']` | casts to int so `===` comparisons work (DB returns strings sometimes) |
| `session_regenerate_id(true)` | rotates session ID at login — blocks fixation attacks |
| `$_POST['_method']` | spoofs DELETE/PATCH from HTML forms |
| `->only('authenticated')` | attaches my `Authenticated` middleware to protect a route |
| `array_key_last($this->routes)` | gets the last route — lets `only()` chain |
| `return $this` | enables method chaining `->get(...)->only(...)` |
| `\Exception` with leading backslash | "global" Exception, not one inside the current namespace |
| `?? false` in session checks | prevents "undefined index" if key was never set |
| `(new $middleware)->handle()` | instantiates class from a string name and calls a method |