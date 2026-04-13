# PHP Security Notes

The core idea behind all of this is simple: **don't trust anything that comes from outside your application.** Not URLs, not form fields, not uploaded files, not even cookies. Once that mindset clicks, most of the stuff below starts to feel obvious rather than arbitrary.

---

## It Starts Before the Code

Here's something most developers don't think about, a lot of successful attacks never touch the code at all. Attackers go after people first because it's easier.

A fake login page that looks identical to the real one, a casual email asking someone to "verify their account", or just checking someone's public Instagram to answer their security question ("what's your dog's name?"). These aren't exotic techniques, they work constantly because people aren't on guard for them.

The practical takeaways: turn on two-factor authentication everywhere, teach your users to glance at the URL bar before they type a password, and shred printed documents. None of that requires a single line of PHP.

---

## Hide Your Code From the Browser

Once you're thinking about the code itself, the first thing to sort out is your folder structure. Right now, if someone guesses the path to your config file, they might be able to visit it in a browser and read it. That's bad.

The fix is to split your project into two folders, one that the web server exposes, and one that it doesn't:

```
project/
├── private/          ← PHP logic, config, functions — server only
│   ├── functions.php
│   └── index.php     ← empty, just blocks directory listing
└── public/           ← your actual web root
    └── index.php
```

Point your server's document root at `public/`. After that, a browser literally cannot navigate to `private/`, only your server-side PHP can reach it. From `public/index.php`, you include what you need like this:

```php
require('../private/functions.php');
```

One more thing while you're at it , add `Options -Indexes` to your `.htaccess` and drop an empty `index.php` in every folder. Otherwise Apache will happily show a visitor the full list of files in any directory that doesn't have an index file.

---

## One Door Is Easier to Guard Than Many

With private files sorted, the next question is how your pages get loaded. The typical setup, `index.php`, `login.php`, `posts.php` each sitting in the web root, means every one of those files is an entry point an attacker can probe directly. The more entry points, the more surface area to defend.

A cleaner approach is to route everything through a single `index.php` and keep all the actual pages inside `private/includes/`:

```
public/
└── index.php              ← only entry point

private/includes/
├── home.php
├── posts.php
├── login.php
└── 404.php
```

That single `index.php` figures out which page to load and includes it:

```php
$folder   = '../private/includes/';
$files    = glob($folder . '*.php');
$page     = $_GET['page'] ?? 'home';
$filename = $folder . $page . '.php';

if (in_array($filename, $files)) {
    include($filename);
} else {
    include($folder . '404.php');
}
```

Notice the `glob()` call, it builds a list of files that actually exist in that folder, and the `in_array()` check means only those files can ever be loaded. No matter what someone puts in the URL, they can't load anything outside that list.

If you want clean URLs like `/login` instead of `?page=login`, a couple of `.htaccess` lines handle that:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?url=$1 [QSA,L]
```

---

## Why the Whitelist Matters So Much

It's worth pausing on why that `glob()` + `in_array()` pattern is so important, because the naive version of file includes is genuinely dangerous:

```php
// DON'T do this
$page = $_GET['page'];
include($page);
```

An attacker can pass `../../etc/passwd` as the page value and read system files. Or they can upload an image with PHP code tucked into it and then trigger it through this include. The `include` function doesn't care about file extensions, if there's a `<?php` tag in there, it runs.

The whitelist approach sidesteps all of that. And on the image upload side, always crop or resize uploaded images, rewriting the pixels destroys any hidden code that was embedded in the file.

---

## Keep Database Code in One Place

The same "one door" thinking applies to your database logic. If every page file has its own connection code, then every page file has credentials in it, and any bug in your query handling exists in a dozen places at once.

Pull it all into `functions.php`:

```php
function connect(): PDO {
    return new PDO("mysql:host=localhost;dbname=mydb", 'user', 'pass');
}

function db_read(string $query): array|false {
    $stmt = connect()->prepare($query);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return empty($data) ? false : $data;
}
```

Since `functions.php` is already being included at the top of `index.php`, every page gets access to these functions automatically. Individual pages end up looking like this:

```php
$posts = db_read('SELECT * FROM posts');

if ($posts) {
    foreach ($posts as $post) {
        echo '<h2>' . $post['title'] . '</h2>';
        echo '<p>'  . $post['body']  . '</p>';
    }
}
```

No credentials anywhere except one file. No connection logic to maintain in multiple places. If you need to patch something in how queries are handled, there's exactly one function to update and it applies everywhere instantly.

---

That's the foundation. Private folders, a single entry point, whitelisted includes, and centralised database logic. None of it is complicated, it's mostly just being deliberate about structure from the start.