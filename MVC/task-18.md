# Task 18 - MVC Research Questions (Part 2)

---

## 1. The Controller's Job

When a user clicks "View Profile," the **Controller** acts as the traffic director for that specific request. It does not fetch data itself, nor does it produce any HTML. Instead, it coordinates everything that needs to happen before the final page is assembled and sent back.

The sequence of steps the Controller performs:

1. **Receives the request**: The Router matches the incoming URL (e.g. `/users/42/profile`) and HTTP method (`GET`) to the correct Controller method (e.g. `UserController::profile()`).
2. **Reads the request data**: It extracts anything it needs from the request, such as the user ID from the URL parameter (`42`).
3. **Talks to the Model**: It calls the appropriate Model method (e.g. `User::findById(42)`) to fetch the data it needs. The Controller never writes a SQL query itself.
4. **Applies any business logic**: For example, it might check whether the logged-in user actually has permission to view this profile.
5. **Passes data to the View**: It calls `$this->render('users/profile', ['user' => $user])`, handing the fetched data to the correct View file.
6. **Returns the response**: The View renders the HTML using the data it received, and the final page is sent back to the browser.

```php
<?php
class UserController extends Controller {

    public function profile(int $id): void
    {
        // Step 2: Read the request parameter
        // Step 3: Ask the Model for the data
        $user = User::findById($id);

        // Step 4: Business logic: is the user allowed to see this?
        if (!$user) {
            // Redirect or show a 404 if the user doesn't exist
            $this->redirect('/404');
            return;
        }

        // Step 5 & 6: Pass data to the View and render the response
        $this->render('users/profile', [
            'user' => $user,
        ]);
    }
}
?>
```

The Controller's job is purely to **orchestrate**: it reads the request, fetches the data, checks conditions, and hands everything off to the View. It never touches HTML directly.

---

## 2. Dynamic Views

A **static HTML file** is a file whose content is fixed and identical for every person who visits it. The server simply reads the file from disk and sends it as-is, nothing on the page can change based on who is logged in, what is stored in the database, or what time it is.

A **dynamic PHP View** is a template that is executed on the server before being sent to the browser. It can receive data from the Controller (like a user object fetched from the database) and weave that data directly into the output. Every visitor can see a page that is personalised to them, even though the template file itself is the same file on disk.

```php
<?php
// STATIC: this file shows exactly the same thing to everyone, always
?>
<html>
<body>
    <h1>Welcome, User!</h1>
    <p>You have 0 new messages.</p>
</body>
</html>


<?php
// DYNAMIC: this View receives $user and $messageCount from the Controller
// and produces a different page for every person who visits
?>
<html>
<body>
    <h1>Welcome, <?= htmlspecialchars($user->firstName) ?>!</h1>
    <p>You have <?= $messageCount ?> new messages.</p>
</body>
</html>
```

In short: a static file is a photograph, it never changes. A dynamic PHP View is a template, it is filled in fresh each time a request is made, using whatever data the Controller passes to it.

---

## 3. Data Passing

The Controller passes data to a View through a **shared data array** that is handed to the `render()` method. Each key in the array becomes a variable that is available inside the View file.

The typical flow looks like this:

1. The Controller fetches data from the Model.
2. It calls `$this->render('view-name', ['key' => $value])`.
3. Inside the base `Controller` class, `render()` extracts that array into individual PHP variables using `extract()`.
4. It then includes the View file, where those variables are now in scope and ready to use.

```php
<?php
// --- In the Controller ---
class UserController extends Controller {

    public function index(): void
    {
        // 1. Ask the Model for data
        $users = User::findAll();

        // 2. Pass an associative array to the View
        //    The key 'users' becomes the variable $users inside the View
        $this->render('users/index', [
            'users' => $users,
        ]);
    }
}

// --- Inside the base Controller::render() method ---
protected function render(string $view, array $params = []): void
{
    // 3. Turn every key in the $params array into a real PHP variable
    foreach ($params as $key => $value) {
        $$key = $value; // $$key means "a variable named whatever $key is"
    }

    // 4. Include the View file, $users is now available inside it
    include_once "views/{$view}.php";
}

// --- Inside views/users/index.php (the View) ---
foreach ($users as $user) {
    echo "<p>" . htmlspecialchars($user->firstName) . "</p>";
}
?>
```

The View never fetches data on its own. It simply uses the variables that the Controller prepared and passed in, keeping the two layers cleanly separated.

---

## 4. Templating (Headers & Footers)

In MVC, the solution to repeated navigation bars and footers is a shared **layout file**. Instead of every View file containing its own `<html>`, `<head>`, `<nav>`, and `<footer>` blocks, there is one central layout template that wraps every page. Each View only contains the unique content for that specific page.

The Controller's `render()` method is responsible for loading this layout and injecting the View's content into it at the right place.

```
Without a layout (the wrong way):
    register.php  --> contains nav, full HTML skeleton, footer
    profile.php   --> contains nav, full HTML skeleton, footer  (duplicated)
    home.php      --> contains nav, full HTML skeleton, footer  (duplicated again)

    Change the navbar link? Edit every single file. Easy to miss one.

With a layout (the MVC way):
    main.php      --> contains nav, full HTML skeleton, footer  (defined ONCE)
    register.php  --> contains only the registration form
    profile.php   --> contains only the profile card
    home.php      --> contains only the home page content

    Change the navbar link? Edit main.php. Done.
```

```php
<?php
// --- views/layouts/main.php (the shared layout) ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>My App</title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
    <!-- Navigation bar defined ONCE here -->
    <nav>
        <a href="/">Home</a>
        <a href="/register">Register</a>
        <a href="/login">Login</a>
    </nav>

    <!-- The unique content of each page goes here -->
    <main>
        <?php include_once $this->getView($view) ?>
    </main>

    <!-- Footer defined ONCE here -->
    <footer>
        <p>&copy; 2024 My App</p>
    </footer>
</body>
</html>


<?php
// --- views/register.php (the View, no nav, no footer, just the form) ---
?>
<h1>Register</h1>
<form method="POST" action="/register">
    <input type="text" name="firstName" placeholder="First Name">
    <input type="email" name="email" placeholder="Email">
    <button type="submit">Sign Up</button>
</form>
```

This is the same principle as the `<slot>` concept in modern frontend frameworks. The layout is the permanent frame of the website; the Views are the interchangeable content inside it. Update the layout once and every page on the site reflects the change instantly.

---

## 5. Logic in Views

It is considered bad practice to put complex `if` statements and heavy data-processing loops directly inside View files because the **View's only job is display**. The moment a View starts making decisions or transforming data, it is doing the Controller's or Model's work, and all the problems of mixing concerns come rushing back.

Here is why it matters in practice:

**Readability**: A designer or frontend developer editing the View file should only need to understand HTML. Complex PHP logic buried in the template makes the file harder to read and reason about for anyone who is not a backend developer.

**Maintainability**: If business logic (e.g. "only show admin features if the user's role is 2 and their account was created before a certain date") is scattered across View files, it becomes very difficult to find, update, or keep consistent when requirements change.

**Testability**: Logic inside a View file cannot be unit-tested in isolation. Logic inside a Controller method or a Model method can be tested cleanly without ever touching a browser.

**Separation of Concerns**: The whole point of MVC is that each layer has one responsibility. A View that filters, sorts, and transforms data is no longer just a View.

```php
<?php
// BAD: heavy logic jammed directly into the View

foreach ($users as $user) {
    // Sorting, filtering, and formatting all done inside the template
    if ($user->status === 1 && $user->createdAt > strtotime('2023-01-01')) {
        $fullName = strtoupper($user->firstName . ' ' . $user->lastName);
        if (strlen($fullName) > 20) {
            $fullName = substr($fullName, 0, 17) . '...';
        }
        echo "<p>{$fullName}</p>";
    }
}
?>


<?php
// GOOD: the Controller prepares clean, display-ready data before sending it to the View

// In the Controller:
$activeUsers = User::findActiveAfter('2023-01-01'); // Model handles the filter

foreach ($activeUsers as $user) {
    $user->displayName = $user->getFormattedName(20); // Model/service handles the formatting
}

$this->render('users/index', ['users' => $activeUsers]);

// In the View, nothing but clean, simple display logic:
foreach ($users as $user) {
    echo "<p>{$user->displayName}</p>";
}
?>
```

A View should ideally contain nothing more complex than a simple `foreach` loop to display a list, or a basic `if` to show or hide an element. Any real processing, filtering, sorting, transforming, calculating, belongs in the Controller or the Model, where it can be tested, reused, and maintained cleanly.

---