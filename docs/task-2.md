# Task 2 - What is Actually Happening:

so basically in this task we built a notes feature from scratch using pure php, no frameworks. i'll go through the things that actually confused me and explain them the way i understand them now.

---

## How the whole app flows

the thing that helped me the most was just drawing out what happens when you visit a page:

```
you go to /notes in the browser
    → lands on index.php (always, every request)
    → router.php reads the url
    → finds a match in routes.php
    → loads the right controller
    → controller gets data from db
    → passes it to the view
    → view prints the html
```

once i got this in my head everything else made sense.

---

## `routes.php`

before this milestone the routes were just sitting inside router.php mixed in with all the logic. that's annoying because every time you want to see what pages exist you have to scroll through code.

so we moved them to their own file:

```php
// routes.php

<?php

return [
    '/'            => 'controllers/index.php',
    '/about'       => 'controllers/about.php',
    '/contact'     => 'controllers/contact.php',
    '/notes'       => 'controllers/notes.php',
    '/note'        => 'controllers/note.php',
    '/note/create' => 'controllers/note-create.php',
];
```

just a php file that returns an array. same pattern as config.php. now if i want to add a page i only touch this file and nothing breaks.

---

## the `$USER_ID` thing

ok this one genuinely confused me for a bit. the controllers all use `$USER_ID` but if you look at any controller file, it never gets defined there. so where is it coming from?

turns out its coming from `routeToController()`:

```php
// router.php

function routeToController($uri, $routes, $USER_ID = 1) {
    if (array_key_exists($uri, $routes))
        require $routes[$uri];
    else
        abort();
}
```

when php `require`s a file it doesnt create a new scope for it — the file just runs inside the same scope as whoever called require. so `$USER_ID` that's defined as a parameter here is automatically available inside whichever controller gets loaded.

the `= 1` just means its hardcoded to user 1 for now since we havent built login yet. when auth gets added later, this is literally the one line that changes. smart.

---

## Database class and why query() returns $this

the database class wraps pdo so we dont have to repeat connection logic everywhere. heres the full thing:

```php
// Database.php

<?php

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

    public function query($query, $params = [])
    {
        $this->statement = $this->connection->prepare($query);
        $this->statement->execute($params);
        return $this;
    }

    public function findAll()
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

        if (!$result) {
            abort();
        }

        return $result;
    }
}
```

the part that took me a second was `return $this` at the end of `query()`. it returns the database object itself which means you can immediately call another method on it:

```php
// controllers/notes.php

$notes = $db->query('SELECT * FROM notes WHERE user_id = ?', [$USER_ID])->findAll();

// controllers/note.php

$note = $db->query('SELECT * FROM notes WHERE id = ?', [$NOTE_ID])->findOrFail();
```

`findOrFail()` is probably my favorite addition here. instead of writing this in every single controller:

```php
// the old way, in any controller

$note = $db->query(...)->find();
if (!$note) abort();
```

you just do `->findOrFail()` and it handles it internally. less repetition.

---

## `Authorization` - making sure you can only see your own notes

when someone visits `/note?id=5` two bad things could happen:

- that note doesnt exist → should get a 404
- that note belongs to a different user → should get a 403

heres how the controller handles both:

```php
// controllers/note.php

$NOTE_ID = $_GET['id'];

$note = $db->query('SELECT * FROM notes WHERE id = ?', [$NOTE_ID])->findOrFail();

authorize($note['user_id'] === $USER_ID);
```

line 3 handles the 404 (note doesnt exist).
line 5 handles the 403 (note exists but isnt yours).

the `authorize()` function is simple:

```php
// functions.php

function authorize($condition, $status = Response::FORBIDDEN)
{
    if (!$condition) {
        abort($status);
    }
}
```

pass it something that evaluates to true or false. if its false, it stops execution and loads the error page.

and the status codes are stored as constants so we're not writing random numbers everywhere:

```php
// Response.php

class Response {
    const FORBIDDEN = 403;
    const NOT_FOUND = 404;
}
```

`Response::FORBIDDEN` > `403`. easier to read, harder to mistype.

---

## the create note form (GET and POST in one file)

this one is interesting. one controller file handles both showing the empty form and processing the submission:

```php
// controllers/note-create.php

<?php

require 'Validator.php';

$config = require('config.php');
$db = new Database($config['database']);
$heading = 'New Note';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];

    if (!Validator::string($_POST['body'], 1, 1000)) {
        $errors['body'] = 'A body of no more than 1000 characters is required.';
    }

    if (empty($errors)) {
        $db->query('INSERT INTO notes (body, user_id) VALUES (:body, :user_id)', [
            'body'    => $_POST['body'],
            'user_id' => $USER_ID,
        ]);
    }
}

require 'views/note-create.view.php';
```

if its a GET request the if block doesnt even run, just renders the form.

if its POST it validates the input, if theres errors it stores them in `$errors`, if theres no errors it does the insert. the view gets loaded either way at the bottom.

one thing i noticed — the insert uses `:body` and `:user_id` instead of `?`. these are named placeholders and the array you pass in maps by key name not by position. i think its cleaner especially when you have a lot of values.

---

## Validator class

```php
// Validator.php

<?php

class Validator
{
    public static function string($value, $min = 1, $max = INF)
    {
        $value = trim($value);
        return strlen($value) >= $min && strlen($value) <= $max;
    }

    public static function email($value)
    {
        $value = trim($value);
        return filter_var($value, FILTER_VALIDATE_EMAIL);
    }
}
```

static methods because there's no state involved. you give it a value, it gives you back true or false. no need to instantiate anything, just call `Validator::string(...)`.

the `trim()` before checking length is important — if someone submits a bunch of spaces it would pass a minimum length check without the trim. trim first, then check.

`$max = INF` means no upper limit by default, so you can use `Validator::string($val, 1)` as a simple "not empty" check.

---

## two things in the view that matter

```php
// views/note-create.view.php

<textarea name="body"><?= $_POST['body'] ?? '' ?></textarea>

<?php if (isset($errors['body'])) : ?>
    <p class="text-red-500 text-xs mt-2"><?= $errors['body'] ?></p>
<?php endif; ?>
```

`$_POST['body'] ?? ''` — if the form was submitted and failed validation, this repopulates the textarea with what the user already typed. the `??` means "use this if the left side doesnt exist", so on first load it just outputs nothing.

`isset($errors['body'])` — on a GET request `$errors` doesnt exist at all. without isset php would throw a warning. this way it just quietly returns false and no error message shows.

small stuff but it makes the form actually usable.