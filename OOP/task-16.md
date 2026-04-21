# Task 16 - OOP Research Questions

---

## 1. Traits

**Traits** are a mechanism PHP provides to reuse sets of methods across multiple classes without using inheritance. Since PHP only allows a class to extend **one** parent class, Traits solve the problem of sharing behavior across unrelated classes by letting you "inject" a block of methods into any class using the `use` keyword.

**When should you use them?** Use a Trait when you have a piece of functionality that needs to be shared between classes that do not share a logical parent-child relationship. For example, a `Loggable` trait can be used by both an `Order` class and a `User` class without forcing them to extend a common base class.

```php
<?php
trait Loggable {
    public function log(string $message): void {
        echo "[LOG] " . get_class($this) . ": {$message}\n";
    }
}

trait Timestampable {
    public function getCreatedAt(): string {
        return date("Y-m-d H:i:s");
    }
}

class Order {
    use Loggable, Timestampable;

    public string $id;

    public function __construct(string $id) {
        $this->id = $id;
    }
}

class User {
    use Loggable;

    public string $username;

    public function __construct(string $username) {
        $this->username = $username;
    }
}

$order = new Order("ORD-001");
$order->log("Order created.");          // [LOG] Order: Order created.
echo $order->getCreatedAt() . "\n";     // 2025-08-01 14:00:00

$user = new User("john_doe");
$user->log("User logged in.");          // [LOG] User: User logged in.
?>
```

Both `Order` and `User` share the `log()` method through the `Loggable` trait, without being forced into an inheritance relationship with each other.

---

## 2. Namespaces

A **Namespace** is a way of grouping related classes, functions, and constants under a unique named path, similar to how folders organize files on a computer. You declare a namespace at the top of a PHP file using the `namespace` keyword.

### How does it prevent naming collisions?

Imagine two different libraries both define a class called `User`. Without namespaces, including both files would cause a fatal error because PHP sees two classes with the exact same name. Namespaces solve this by qualifying each class with its own unique path, so `App\Models\User` and `Auth\Providers\User` are treated as completely different classes even though their short names are identical.

```php
<?php
// File: App/Models/User.php
namespace App\Models;

class User {
    public function getType(): string {
        return "App Model User";
    }
}
?>

<?php
// File: Auth/Providers/User.php
namespace Auth\Providers;

class User {
    public function getType(): string {
        return "Auth Provider User";
    }
}
?>

<?php
// File: index.php
require "App/Models/User.php";
require "Auth/Providers/User.php";

use App\Models\User as AppUser;
use Auth\Providers\User as AuthUser;

$appUser  = new AppUser();
$authUser = new AuthUser();

echo $appUser->getType();  // App Model User
echo $authUser->getType(); // Auth Provider User
?>
```

The `use ... as` syntax gives each class a local alias, making it easy to work with both in the same file without any conflict.

---

## 3. Autoloading

**Autoloading** is a mechanism that automatically loads the PHP file for a class the moment that class is first used, instead of requiring you to manually `require` every single file at the top of your script.

### Before autoloading:

You had to write a `require` statement for every class file, and as a project grew, the top of every file became a long, fragile list of manual includes. If you forgot one, or if a file moved, everything broke.

### How it saves time:

PHP's `spl_autoload_register()` function lets you define a single callback that PHP calls automatically whenever an unknown class is encountered. Modern projects use **Composer's autoloader**, which follows the PSR-4 standard and maps namespaces directly to folder paths, so you never write a `require` again.

```php
<?php
// Without autoloading - manual requires for every class
require "app/Models/User.php";
require "app/Models/Order.php";
require "app/Services/PaymentService.php";
require "app/Services/EmailService.php";
// ...this list grows forever

// -----------------------------------------------

// With a custom autoloader using spl_autoload_register
spl_autoload_register(function (string $className): void {
    // Convert namespace separators to directory separators
    $filePath = str_replace("\\", "/", $className) . ".php";
    if (file_exists($filePath)) {
        require $filePath;
    }
});

// Now PHP auto-loads the file the moment this line is reached
$user = new App\Models\User(); // Automatically loads app/Models/User.php
?>

<?php
// With Composer (the modern standard) - just one line in your entry point
require "vendor/autoload.php";

// Composer maps App\Models\User -> src/Models/User.php automatically
$user  = new App\Models\User();
$order = new App\Models\Order();
// No manual requires needed at all
?>
```

---

## 4. Magic Methods (`__get` and `__set`)

`__get` and `__set` are **magic methods** that PHP calls automatically when code tries to read from or write to a property that is either inaccessible (like `private` or `protected`) or does not exist on the object at all.

- **`__get($name)`** is triggered when reading a property that cannot be accessed directly.
- **`__set($name, $value)`** is triggered when writing to a property that cannot be accessed directly.

### When are they useful?

They are commonly used to add validation logic before setting a value, to create virtual read-only properties, or to build dynamic data containers where properties are stored internally in an array rather than declared explicitly on the class.

```php
<?php
class Product {
    private array $data = [];
    private array $readOnly = ["sku"];

    public function __construct(string $sku) {
        $this->data["sku"] = $sku;
    }

    // Triggered automatically when reading an inaccessible property
    public function __get(string $name): mixed {
        if (array_key_exists($name, $this->data)) {
            return $this->data[$name];
        }
        return null;
    }

    // Triggered automatically when writing to an inaccessible property
    public function __set(string $name, mixed $value): void {
        if (in_array($name, $this->readOnly)) {
            echo "Error: '{$name}' is read-only and cannot be changed.\n";
            return;
        }
        $this->data[$name] = $value;
    }
}

$product = new Product("SKU-999");

// Triggers __set: stores the value in the internal $data array
$product->name  = "Wireless Mouse";
$product->price = 49.99;

// Triggers __get: reads the value from the internal $data array
echo $product->name;  // Wireless Mouse
echo $product->sku;   // SKU-999

// Triggers __set: blocked because "sku" is read-only
$product->sku = "HACKED"; // Error: 'sku' is read-only and cannot be changed.
?>
```

---

## 5. Static Methods and Properties

A method or property declared as **`static`** belongs to the **class itself**, not to any individual object instance. This means you can call a static method or access a static property directly on the class without ever creating an object using the `new` keyword. The `::` (double colon) operator is used instead of `->`.

**Do you need `new` to access a static method?** No. Static members are accessed directly via `ClassName::methodName()` or `ClassName::$propertyName`.

**When should you use static?** Use static for utility/helper methods that do not depend on object state, or for shared data that must be the same across all instances of a class, such as a counter tracking how many objects have been created.

```php
<?php
class MathHelper {
    // Static method - belongs to the class, not an instance
    public static function square(float $number): float {
        return $number * $number;
    }

    public static function percentage(float $value, float $total): float {
        return ($value / $total) * 100;
    }
}

// No object needed - called directly on the class
echo MathHelper::square(6);              // 36
echo MathHelper::percentage(45, 200);    // 22.5

// -----------------------------------------------

class DatabaseConnection {
    private static int $connectionCount = 0;

    public function __construct() {
        self::$connectionCount++;
    }

    // Static method to read the shared static property
    public static function getConnectionCount(): int {
        return self::$connectionCount;
    }
}

$db1 = new DatabaseConnection();
$db2 = new DatabaseConnection();
$db3 = new DatabaseConnection();

// The static property is shared across all instances
echo DatabaseConnection::getConnectionCount(); // 3
?>
```

`self::` is used inside the class to refer to static members of the same class, while `ClassName::` is used from outside.