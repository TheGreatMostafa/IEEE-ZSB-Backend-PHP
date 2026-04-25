# Task 17 - MVC Research Questions

---

## 1. The MVC Pattern

**MVC** stands for **Model-View-Controller**. It is an architectural pattern that separates an application into three distinct layers, each with a single, well-defined responsibility.

- **Model**: Manages the data and business logic. It talks directly to the database, running queries, saving records, and enforcing rules like "a user must have a unique email." It knows nothing about how data is displayed.
- **View**: Handles presentation only. It takes data passed to it from the Controller and renders the HTML the user sees in the browser. It contains no database calls and no business logic, purely display.
- **Controller**: Acts as the middleman. It receives the incoming request, asks the Model for data if needed, and hands that data to the correct View to render. It orchestrates the flow but does not store data or produce raw HTML itself.

**In plain terms:** The Model is the brain, the View is the face, and the Controller is the hands that connect them.

```php
<?php
// Model - only cares about data
class UserModel {
    public function findAll(): array {
        // Talks to the database and returns raw data
        return $pdo->query("SELECT * FROM users")->fetchAll();
    }
}

// Controller - connects Model to View
class UserController {
    public function index(): void {
        $model = new UserModel();
        $users = $model->findAll();     // Ask Model for data
        $this->render('users', $users); // Pass data to View
    }
}

// View (users.php) - only cares about display
foreach ($users as $user) {
    echo "<p>{$user['name']}</p>";
}
?>
```

Each layer has one job and knows as little as possible about the others.

---

## 2. Routing

A **Router** is the component that maps an incoming URL (and HTTP method) to the specific piece of code that should handle it.

Think of a website as a large building and the Router as the **traffic cop / reception desk** at the entrance. Every visitor (HTTP request) arrives at the front door, and the traffic cop looks at *where* they want to go (the URL path) and *how* they arrived (GET or POST), then directs them to the right office (the correct Controller and method). If no matching route is found, the visitor gets a "404, Not Found" response.

```php
<?php
// Configuring routes - telling the Router what to do for each URL

// When a GET request comes in for "/", call SiteController::home()
$app->router->get('/', [SiteController::class, 'home']);

// When a GET request comes in for "/register", call AuthController::register()
$app->router->get('/register', [AuthController::class, 'register']);

// When a POST request comes in for "/register", call AuthController::store()
$app->router->post('/register', [AuthController::class, 'store']);

// Without a Router, you would need a separate PHP file for every URL
// about.php, contact.php, register.php... which is unmaintainable at scale
?>
```

The Router reads the path from `$_SERVER['REQUEST_URI']` and the method from `$_SERVER['REQUEST_METHOD']` to find the right match and dispatch the request.

---

## 3. The Front Controller

A **Front Controller** means that *every single request*, regardless of the URL, is funnelled through one single entry point, `index.php`. The web server is configured via `.htaccess` to redirect all traffic there.

**Traditional approach (no Front Controller):** Every page was its own file, `about.php`, `contact.php`, `login.php`. Each file repeated setup code (database connection, session start, security checks), there was no central place to enforce rules, and adding a new page meant creating a brand new file from scratch.

**Modern approach: the Front Controller:**

```
example.com/about      →  index.php  →  Router  →  SiteController::about()
example.com/register   →  index.php  →  Router  →  AuthController::register()
example.com/contact    →  index.php  →  Router  →  SiteController::contact()
```

```php
<?php
// index.php: the single entry point for the entire application

require_once 'vendor/autoload.php';

// Boot the application once, here, for every request
$app = new App\Core\Application(__DIR__);

// All routes configured in one central place
$app->router->get('/', [SiteController::class, 'home']);
$app->router->get('/about', [SiteController::class, 'about']);
$app->router->get('/register', [AuthController::class, 'register']);
$app->router->post('/register', [AuthController::class, 'store']);

// Hand off to the Router: it takes it from here
$app->run();
?>
```

This gives you one place to boot the application, start sessions, connect to the database, and apply security headers: instead of repeating that logic in dozens of separate files.

---

## 4. Clean URLs

A **clean URL** is a human-readable path like `example.com/users/profile` instead of a messy query-string URL like `example.com/index.php?page=users&action=profile`.

### Why clean URLs matter:

**Readability**: A human can instantly understand what a clean URL points to. Query strings expose internal implementation details (`page=`, `action=`) and are hard to read, share, or remember.

**SEO**: Search engines rank pages with descriptive, keyword-rich URLs higher. `/blog/how-mvc-works` signals meaningful content; `index.php?page=blog&id=42` does not.

**Security**: Exposing query parameters like `?page=admin` can invite parameter-tampering attacks. Clean URLs hide the internal routing mechanism from the outside world.

**Flexibility**: If the underlying technology changes (e.g., switching languages), clean URLs stay the same for users and search engines. Messy URLs that expose `index.php` would break.

```php
<?php
// The Router makes clean URLs possible by parsing the path directly

// Messy - exposes implementation, easy to tamper with
// example.com/index.php?page=users&action=profile&id=42

// Clean - readable, secure, SEO-friendly
// example.com/users/42/profile

$app->router->get('/users/{id}/profile', [UserController::class, 'profile']);

// Inside the Router, it reads the path from the request:
$path   = $_SERVER['REQUEST_URI'];    // "/users/42/profile"
$method = $_SERVER['REQUEST_METHOD']; // "GET"

// Matches the pattern, extracts {id} = 42, and calls UserController::profile(42)
?>
```

The Router parses the URL path and maps it to the correct controller action, no physical file needs to exist on disk for that path.

---

## 5. Separation of Concerns

**Separation of Concerns** is the principle that each part of the codebase should have one job and know as little as possible about the other parts. Putting SQL queries directly inside HTML files violates this principle completely.

### Why mixing SQL into HTML is a terrible idea:

**Maintainability**: If your database schema changes, you must hunt through every HTML file to update queries. In a large project this becomes a nightmare with no clear place to start.

**Readability**: A designer working on HTML has to wade through raw SQL. A backend developer fixing a query has to read through HTML markup. Neither can work cleanly or independently.

**Security**: SQL buried inside view files is harder to audit for injection vulnerabilities. Centralising database access in Models makes it far easier to apply sanitisation and prepared statements in one place.

**Reusability**: A Model method that fetches users can be called by multiple Controllers. SQL hardcoded inside a view file cannot be reused anywhere.

**Testability**: You cannot unit-test a database query tangled inside HTML. Isolating it in a Model class makes automated testing straightforward.

```php
<?php
// ❌ DON'T DO THIS, SQL mixed directly into a view file (users.php)
/*
<html>
<body>
  <?php
    $conn   = mysqli_connect("localhost", "root", "", "mydb");
    $result = mysqli_query($conn, "SELECT * FROM users");
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<p>" . $row['name'] . "</p>";
    }
  ?>
</body>
</html>
*/

// -----------------------------------------------

// ✅ DO THIS, each concern in its own layer

// Model: only talks to the database
class User extends Model {
    public static function findAll(): array {
        return static::query("SELECT * FROM users");
    }
}

// Controller: asks the Model, passes data to the View
class UserController extends Controller {
    public function index(): void {
        $users = User::findAll();
        $this->render('users/index', ['users' => $users]);
    }
}

// View (users/index.php): only displays what it receives
foreach ($users as $user) {
    echo "<p>{$user->name}</p>";
}
?>
```

MVC enforces separation by design, Models handle data, Views handle display, Controllers handle flow, and none of them should do each other's job.

---