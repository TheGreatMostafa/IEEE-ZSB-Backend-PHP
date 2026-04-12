# Last Progress
---

## Section 1: Bootstrap & Configuration

> **Objective:** Understand how the application starts up from locating files, to reading settings, to preparing the container before a single request is handled.

---

## 1. The Entry Point & `base_path()`

Think about what happens when a URL hits your server. Before any controller runs, before any database is touched, the app needs to answer one basic question: *where am I on disk?* That's what `BASE_PATH` solves. Define it once at the top of your front controller, then wrap it in a helper so nothing else ever needs to think about it again:

```php
// public/index.php
define('BASE_PATH', __DIR__ . '/../');
```

```php
// Core/functions.php
function base_path($path)
{
    return BASE_PATH . $path;
}
```

Now instead of sprinkling `__DIR__ . '/../../config.php'` across your files, every file speaks the same language:

```php
require base_path('bootstrap.php');
require base_path('views/home.php');
```

---

## 2. Configuration:  `config.php`

All environment-specific values live in one file. It does nothing fancy, it just returns an array. That simplicity is the point:

```php
// config.php
return [
    'database' => [
        'host'    => 'localhost',
        'port'    => 3306,
        'dbname'  => 'myapp',
        'charset' => 'utf8mb4'
    ],
];
```

When you move to production, you change this one file. When you want to exclude credentials from version control, you add this one file to `.gitignore`. Everything that needs these values reads them from here, nothing hardcodes them.

---

## 3. Bootstrapping: `bootstrap.php`

`bootstrap.php` is where the application's pieces get introduced to each other. It creates a `Container`, tells it how to build a `Database`, and hands the whole container to `App` so the rest of the codebase can use it:

```php
// bootstrap.php
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

Notice the database isn't actually *built* here, only a recipe for building it is stored. The real connection only opens the first time something asks for it. This is called *lazy instantiation*, and it means you don't pay the cost of a database connection on requests that never touch the database.

---

## Section 2: The Service Container `Core/Container.php` & `Core/App.php`

> **Objective:** Understand how the app manages its dependencies and why that matters as the project grows.

---

## 1. What Problem the Container Solves

Imagine a controller that needs a `Database`. It could do `new Database(...)` itself, but then it needs to know the credentials, the DSN format, everything. That's too much responsibility for a controller.

A service container takes that burden away. You register *how* to build things once, and everywhere else simply asks for them by name. The controller stays focused on its actual job.

---

## 2. `Core/Container.php`

The container stores closures (factory functions) under string keys. When something asks for a key, the container runs the matching factory and returns the result:

```php
// Core/Container.php
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

        return call_user_func($this->bindings[$key]);
    }
}
```

**Key design decisions:**

- `bind()` just stores the closure, it doesn't execute it. Nothing is built until it's needed.
- `resolve()` runs the factory fresh on every call. There's no caching, so every resolution gets a new instance.
- If you try to resolve something that was never registered, you get a loud `Exception`, not a silent `null` that crashes somewhere mysterious later.

---

## 3. `Core/App.php`: The Global Access Point

Having the container is great, but passing it around manually everywhere would be tedious. `App` solves that by holding the container statically , any class in the project can reach it without needing it passed in:

```php
// Core/App.php
namespace Core;

class App
{
    protected static $container;

    public static function setContainer($container)
    {
        static::$container = $container;
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

You set the container once in `bootstrap.php`. After that, anywhere in the codebase: `App::resolve(Database::class)` , done.

---

## Section 3: The Database Layer, `Core/Database.php`

> **Objective:** Understand the wrapper that makes database work safe, readable, and consistent across the whole project.

---

## 1. Connecting via PDO

PHP's built-in PDO is powerful but verbose. The `Database` class wraps it to hide the boilerplate. The constructor takes the config array, builds a DSN string from it, and opens the connection , with `FETCH_ASSOC` set so every result comes back as a named-key array, not a mess of numeric indices:

```php
// Core/Database.php
namespace Core;

use PDO;

class Database
{
    public $connection;
    public $statement;

    public function __construct($config, $username = 'root', $password = '')
    {
        $dsn = 'mysql:' . http_build_query($config, '', ';');

        $this->connection = new PDO($dsn, $username, $password, [
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    }
}
```

`http_build_query` is a clever trick here , it turns `['host' => 'localhost', 'dbname' => 'myapp']` into the `host=localhost;dbname=myapp` format that PDO's DSN expects, without any manual string-building.

---

## 2. Running Queries: `query()`

Every query in the app goes through this one method. It prepares the statement, binds the parameters, executes it, and returns `$this` so you can chain a fetch method right after:

```php
// Core/Database.php
public function query($query, $params = [])
{
    $this->statement = $this->connection->prepare($query);
    $this->statement->execute($params);
    return $this;
}
```

The critical detail is that user input always goes into the `$params` array , it is *never* concatenated directly into the query string. This is how prepared statements prevent SQL injection. A user can't escape the parameter boundary and inject malicious SQL.

```php
// Safe , the email value is bound separately
App::resolve(Database::class)
    ->query('SELECT * FROM users WHERE email = :email', ['email' => $email])
    ->find();
```

---

## 3. Fetching Results

After `query()`, you pick how you want the results:

```php
// Core/Database.php
public function get()
{
    return $this->statement->fetchAll();
}

public function find()
{
    return $this->statement->fetch();
}

public function findOrFail()
{
    $result = $this->find();

    if (! $result) {
        abort();
    }

    return $result;
}
```

| Method | Returns | When to use |
|--------|---------|-------------|
| `get()` | All matching rows | Listing pages , you expect multiple results |
| `find()` | First row, or `false` | When you want to handle "not found" yourself |
| `findOrFail()` | First row, or a 404 page | When a missing record should always be an error |

`findOrFail()` is particularly handy for detail pages , instead of manually checking if the result is empty and calling `abort()`, you get that behaviour for free.

---

## Section 4: Authentication : `Core/Authenticator.php`

> **Objective:** Understand how users get logged in, how their identity is stored, and how they get logged out cleanly.

---

## 1. `attempt()` : Verifying Credentials

When a user submits the login form, `attempt()` handles the whole verification flow , look up the user, check the password, and if everything matches, log them in:

```php
// Core/Authenticator.php
namespace Core;

class Authenticator
{
    public function attempt($email, $password)
    {
        $user = App::resolve(Database::class)
            ->query('select * from users where email = :email', [
                'email' => $email
            ])->find();

        if ($user) {
            if (password_verify($password, $user['password'])) {
                $this->login(['email' => $email]);
                return true;
            }
        }

        return false;
    }
}
```

**Key design decisions:**

- `password_verify()` is not optional. Passwords in the database must be stored as bcrypt hashes (via `password_hash()`), never as plain text. `password_verify()` knows how to compare them safely.
- `attempt()` only returns `true` or `false`. It doesn't redirect, doesn't load a view , it just answers the question. The controller calling it decides what happens next.

---

## 2. `login()` & `logout()`

Once `attempt()` confirms the credentials are valid, `login()` records the authenticated user in the session. Only the email is stored , not the full database row. Right after writing to the session, the session ID is regenerated:

```php
// Core/Authenticator.php
public function login($user)
{
    $_SESSION['user'] = [
        'email' => $user['email']
    ];

    session_regenerate_id(true);
}
```

Regenerating the session ID after login is a security measure that closes off *session fixation* attacks , a technique where an attacker tricks a user into logging in with a session ID the attacker already knows.

Logging out is a one-liner, and deliberately so. All the session-clearing logic belongs in `Session::destroy()`, not scattered here:

```php
// Core/Authenticator.php
public function logout()
{
    Session::destroy();
}
```

---

## Section 5: Global Helpers `Core/functions.php`

> **Objective:** Get familiar with the small functions available everywhere , the ones you'll reach for constantly.

---

## 1. Debugging, Aborting & Authorization

Three helpers that deal with stopping execution in different ways:

```php
// Core/functions.php
function dd($value)
{
    echo "<pre>";
    var_dump($value);
    echo "</pre>";
    die();
}

function abort($code = 404)
{
    http_response_code($code);
    require base_path("views/{$code}.php");
    die();
}

function authorize($condition, $status = Response::FORBIDDEN)
{
    if (! $condition) {
        abort($status);
    }

    return true;
}
```

`dd()` , short for "dump and die" , is your best friend while debugging. It prints any value in a readable format and immediately stops the script. Just don't leave it in production.

`abort()` is more intentional. It sends the right HTTP status code and loads the matching error view , so `abort(404)` renders `views/404.php`, `abort(403)` renders `views/403.php`, and so on.

`authorize()` wraps `abort()` into a clean one-liner for protecting resources. Instead of an `if` block in every controller:

```php
// Core/functions.php , usage in a controller
authorize($currentUser['id'] === $resource['user_id']);
```

---

## 2. Views, Redirects & Old Input

The three helpers you'll use in almost every controller:

```php
// Core/functions.php
function view($path, $attributes = [])
{
    extract($attributes);
    require base_path('views/' . $path);
}

function redirect($path)
{
    header("location: {$path}");
    exit();
}

function old($key, $default = '')
{
    return Core\Session::get('old')[$key] ?? $default;
}
```

**Key design decisions:**

- `view()` calls `extract()` on the attributes array before loading the file. That turns `['errors' => [...]]` into a proper `$errors` variable inside the view , clean and simple, no template engine required.
- `redirect()` always calls `exit()` and that's intentional. A very common PHP mistake is sending a `Location` header and forgetting the `exit()` , the browser navigates away but the script keeps running server-side. This helper makes that impossible.
- `old()` is for repopulating form fields after a failed submission. Use it on every field except the password , never echo a submitted password back into the page.

```php
{{-- In a view --}}
<input type="email" name="email" value="<?= old('email') ?>">
```

---

## Section 6: Project Structure

```
project/
├── Core/
│   ├── App.php            ← Static access point for the container
│   ├── Authenticator.php  ← Login, logout, and session writing
│   ├── Container.php      ← Dependency injection container
│   ├── Database.php       ← PDO wrapper with query chaining
│   └── functions.php      ← Global helpers (dd, view, redirect, old…)
├── config.php             ← Database credentials and app settings
├── bootstrap.php          ← Wires everything together at startup
└── public/
    └── index.php          ← Front controller , every request starts here
```

**The flow of a typical request:**

1. `public/index.php` defines `BASE_PATH`, requires `bootstrap.php`, and starts the session.
2. The router matches the URI and loads the appropriate controller.
3. The controller calls `App::resolve()` to get its dependencies, runs its logic, and responds with either `view()` or `redirect()`.
4. If something goes wrong , record not found, unauthorized access , `abort()` takes over, renders the error view, and halts.