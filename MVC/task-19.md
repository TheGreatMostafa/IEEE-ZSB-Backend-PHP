# Task 19 - MVC Research Questions (Part 3)

---

## 1. Who Talks to the Database?

In the MVC pattern, the **Model** is the only part of the application that is allowed to talk directly to the database. Neither the Controller nor the View should ever contain SQL queries.

The reason is **Separation of Concerns**. Each layer has one job, and the Model's job is to manage data. When database logic lives exclusively inside the Model, there is one clear, predictable place to go whenever a query needs to be written, fixed, or updated. If Controllers or Views were also allowed to run queries, you would end up with SQL scattered across the entire codebase, impossible to audit for security issues, impossible to reuse, and a nightmare to maintain when the database schema changes.

```php
<?php
// WRONG: the Controller reaches directly into the database
class UserController extends Controller {
    public function index(): void
    {
        // The Controller should NEVER write SQL, this belongs in the Model
        $pdo  = new PDO('mysql:host=localhost;dbname=myapp', 'root', 'secret');
        $stmt = $pdo->query("SELECT * FROM users");
        $users = $stmt->fetchAll();

        $this->render('users/index', ['users' => $users]);
    }
}

// CORRECT: all database access lives inside the Model
class User extends Model {
    public static function findAll(): array
    {
        // SQL stays here, the Model owns every conversation with the database
        return static::query("SELECT * FROM users");
    }
}

class UserController extends Controller {
    public function index(): void
    {
        // The Controller simply asks the Model for the data it needs
        $users = User::findAll();
        $this->render('users/index', ['users' => $users]);
    }
}
?>
```

**In short:** The Model is the gatekeeper of the database. Every other layer has to ask the Model for data, it never goes around it.

---

## 2. Sensitive Configuration Files

Sensitive values like database passwords, API keys, and secret tokens should never be hardcoded directly inside application files. They belong in a **separate configuration file** that is kept out of version control (i.e., added to `.gitignore`).

Here is why it matters:

**Security**: If your source code is ever made public, accidentally pushed to GitHub, shared with a colleague, or leaked in a breach, hardcoded credentials are immediately exposed to anyone who reads the file. A separate config file that is never committed to the repository cannot be leaked this way.

**Environment flexibility**: A project typically runs in multiple environments: a local machine for development, a staging server for testing, and a production server for real users. Each environment needs different credentials. A config file lets you swap those values per environment without ever touching the application code.

**Maintainability**: If a password changes, you update it in one config file. If it were hardcoded, you would have to hunt through every file that mentions it and hope you did not miss one.

```php
<?php
//  WRONG: credentials hardcoded directly in the application file
class Database {
    private string $host     = 'localhost';
    private string $dbName   = 'myapp';
    private string $username = 'root';
    private string $password = 'super_secret_password_123'; // ← exposed to anyone who sees this file
}


//  CORRECT: credentials live in a separate config file that is never committed

// --- .env (in the project root, added to .gitignore) ---
/*
    DB_HOST=localhost
    DB_NAME=myapp
    DB_USER=root
    DB_PASS=super_secret_password_123
*/

// --- config/db.php (loads the values safely) ---
return [
    'host'     => $_ENV['DB_HOST'],
    'dbName'   => $_ENV['DB_NAME'],
    'username' => $_ENV['DB_USER'],
    'password' => $_ENV['DB_PASS'],
];

// --- Application bootstrap (reads the config, never the raw password) ---
$config = require_once 'config/db.php';
$dsn    = "mysql:host={$config['host']};dbname={$config['dbName']}";
$pdo    = new PDO($dsn, $config['username'], $config['password']);
?>
```

The rule of thumb is simple: if a value would cause a problem if a stranger read it, it does not belong in the source code.

---

## 3. What is PDO?

**PDO** stands for **PHP Data Objects**. It is a database abstraction layer built into PHP that provides a consistent, unified interface for connecting to and querying many different types of databases, MySQL, PostgreSQL, SQLite, and others, using exactly the same code.

Older approaches like `mysqli` are tightly coupled to a single database engine (MySQL only). If you ever needed to switch databases with `mysqli`, you would have to rewrite every query. With PDO, only the connection string (the DSN) changes; the rest of the code stays the same.

Beyond portability, PDO is preferred for several other reasons:

**Prepared Statements**: PDO has first-class, clean support for prepared statements (covered in the next section), which is the primary defence against SQL Injection attacks.

**Error handling**: PDO uses exceptions for error reporting, which integrates cleanly with PHP's standard `try/catch` error-handling flow instead of forcing you to manually check return values after every query.

**Cleaner API**: The PDO API is more consistent and object-oriented than `mysqli`, making it easier to read and work with.

```php
<?php
// --- mysqli approach (MySQL only, tied to one database engine) ---
$conn   = mysqli_connect('localhost', 'root', 'secret', 'myapp');
$result = mysqli_query($conn, "SELECT * FROM users");
$users  = mysqli_fetch_all($result, MYSQLI_ASSOC);


// --- PDO approach (works with MySQL, PostgreSQL, SQLite, and more) ---
try {
    // Only this line changes if you switch database engines
    $pdo = new PDO('mysql:host=localhost;dbname=myapp', 'root', 'secret');

    // Tell PDO to throw exceptions whenever something goes wrong
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch results as objects rather than plain arrays
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);

    $stmt  = $pdo->query("SELECT * FROM users");
    $users = $stmt->fetchAll();

} catch (PDOException $e) {
    // Errors are caught like any other PHP exception, clean and consistent
    echo "Database error: " . $e->getMessage();
}
?>
```

In short: PDO is the modern, safe, and flexible standard for database access in PHP. `mysqli` is an older tool that only works with one database and lacks PDO's elegance.

---

## 4. Prepared Statements and SQL Injection

**SQL Injection** is an attack where a malicious user types SQL code into an input field (like a login form) and tricks the application into running that code against the database. It is one of the most common and dangerous web vulnerabilities.

**Prepared Statements** prevent SQL Injection by completely separating the SQL query structure from the user-supplied data. The database receives the query template first, compiles it, and only then receives the data as a safe parameter. No matter what a user types into the input field, it is treated as plain data, it can never be interpreted as SQL code.

```php
<?php
//  VULNERABLE: user input is pasted directly into the SQL string
//
// If $email = "' OR '1'='1" the query becomes:
// SELECT * FROM users WHERE email = '' OR '1'='1'
// This is always true, the attacker bypasses the login check entirely.

$email = $_POST['email'];
$stmt  = $pdo->query("SELECT * FROM users WHERE email = '$email'");


//  SAFE: Prepared Statement with a named placeholder
//
// Step 1: Send the query STRUCTURE to the database, no data yet.
//         The :email placeholder marks where data will go.
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");

// Step 2: Bind the actual user input to the placeholder.
//         PDO passes this value as plain data, the database never
//         interprets it as SQL, no matter what the user typed.
$stmt->bindValue(':email', $_POST['email']);

// Step 3: Execute the now-safe query.
$stmt->execute();

$user = $stmt->fetch();

// Even if the attacker types: ' OR '1'='1
// The database looks for a user whose email literally IS that string.
// It finds nothing. The attack fails completely.
?>
```

Think of it like a form with a fixed structure and blank fields. The structure of the sentence is locked in before any user data is inserted. The user can write whatever they like in the blank, but they cannot rewrite the structure of the sentence itself.

---

## 5. Fetching One Row vs. Multiple Rows

When querying a database you choose between fetching a **single row** or **multiple rows** depending on what the question you are asking the database actually is.

**Fetching a single row** makes sense when you are looking up one specific, uniquely identified record. The question has exactly one correct answer.

A real-world example: a user clicks "View Profile" for the account with ID 42. You only want the one user whose `id` is `42`. Fetching an array of users would be unnecessary and wasteful, there is only ever one person with that ID.

**Fetching multiple rows** makes sense when you are retrieving a collection of records that all satisfy some condition. The question has a list as its answer.

A real-world example: an admin opens the "All Users" page to see everyone registered on the platform. The query returns every row in the `users` table, and the View loops over the full list to display each one.

```php
<?php
// --- Fetching a SINGLE row ---
// Use case: the user navigates to /users/42/profile
// We need exactly one record, the user with id = 42

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
$stmt->bindValue(':id', 42, PDO::PARAM_INT);
$stmt->execute();

$user = $stmt->fetch(); // Returns one object (or false if not found)

echo "Welcome, " . $user->firstName;


// --- Fetching MULTIPLE rows ---
// Use case: the admin opens /users to see all registered accounts
// We need every record in the table, an array to loop over

$stmt = $pdo->prepare("SELECT * FROM users ORDER BY createdAt DESC");
$stmt->execute();

$users = $stmt->fetchAll(); // Returns an array of objects

foreach ($users as $user) {
    echo "<p>" . htmlspecialchars($user->firstName) . "</p>";
}
?>
```

**The simple rule:** if the answer is one thing, use `fetch()`. If the answer is a list of things, use `fetchAll()`. Misusing `fetchAll()` when you only need one record wastes memory; misusing `fetch()` when you need a list means you only ever process the first result.

---