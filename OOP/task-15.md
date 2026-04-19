# Task 15 - OOP Research Questions

---

## 1. Inheritance

**Inheritance** allows a child class to acquire the properties and methods of a parent class. The main benefit is code reuse, instead of rewriting shared logic in every class, you define it once in the parent and let child classes extend it.

**Analogy:** A `User` is the parent class. An `Admin` is a child class that inherits general user behavior but gains extra privileges on top.

```php
<?php
class User {
    public string $username;
    public string $email;

    public function __construct(string $username, string $email) {
        $this->username = $username;
        $this->email    = $email;
    }

    public function getInfo(): string {
        return "User: {$this->username} | Email: {$this->email}";
    }
}

class Admin extends User {
    public string $role;

    public function __construct(string $username, string $email, string $role) {
        parent::__construct($username, $email);
        $this->role = $role;
    }

    public function getInfo(): string {
        return parent::getInfo() . " | Role: {$this->role}";
    }
}

$user  = new User("john_doe", "john@example.com");
$admin = new Admin("super_ali", "ali@example.com", "Super Admin");

echo $user->getInfo();  // User: john_doe | Email: john@example.com
echo $admin->getInfo(); // User: super_ali | Email: ali@example.com | Role: Super Admin
?>
```

---

## 2. The `final` Keyword

- Placing `final` before a **class** prevents any other class from extending it.
- Placing `final` before a **method** prevents child classes from overriding that specific method.

### Why would a developer use this?

To protect critical logic from being accidentally or intentionally changed by a subclass. If a class or method contains behavior that must stay consistent across the entire application, such as a security check or an audit trail, marking it `final` guarantees no one can alter it through inheritance.

```php
<?php
class Order {
    final public function generateInvoiceId(): string {
        // Billing logic that must never be tampered with
        return "INV-" . strtoupper(uniqid());
    }
}

class DiscountedOrder extends Order {
    // This would throw a fatal error:
    // public function generateInvoiceId(): string { return "FREE"; }
}

final class EncryptionService {
    public function encrypt(string $data): string {
        return base64_encode($data);
    }
}

// This would throw a fatal error:
// class WeakEncryption extends EncryptionService { }
?>
```

---

## 3. Overriding Methods

**Overriding** means redefining a method in a child class that already exists in the parent class. The child's version replaces the parent's version when called on a child object.

To call the original parent method from inside the overridden method, use `parent::methodName()`.

```php
<?php
class Discount {
    public function apply(float $price): float {
        return $price * 0.9; // 10% off by default
    }
}

class SeasonalDiscount extends Discount {
    public function apply(float $price): float {
        $afterBase = parent::apply($price); // apply the original 10% first
        return $afterBase * 0.8;            // then apply an extra 20% on top
    }
}

class NoDiscount extends Discount {
    public function apply(float $price): float {
        return $price; // override: no reduction at all
    }
}

$regular  = new Discount();
$seasonal = new SeasonalDiscount();
$full     = new NoDiscount();

echo $regular->apply(100);  // 90
echo $seasonal->apply(100); // 72
echo $full->apply(100);     // 100
?>
```

---

## 4. Abstract Class vs. Interface

- An **Abstract Class** can contain both fully implemented methods and abstract methods (methods with no body that child classes must implement). A class can only extend **one** abstract class.
- An **Interface** is a pure contract, it contains only method signatures with no implementation. A class can implement **multiple** interfaces at the same time.

### Main difference:

An abstract class shares common code among related classes, while an interface simply enforces that certain methods exist, regardless of what those classes are.

```php
<?php
// Abstract Class - provides shared logic + enforces a contract
abstract class Report {
    abstract public function generate(): string;

    public function export(): void {
        $content = $this->generate();
        echo "Exporting report:\n{$content}";
    }
}

// Interfaces - pure contracts, can be combined freely
interface Schedulable {
    public function schedule(string $datetime): void;
}

interface Emailable {
    public function sendTo(string $email): void;
}

// Extends one abstract class, implements multiple interfaces
class SalesReport extends Report implements Schedulable, Emailable {
    public function generate(): string {
        return "Total Sales: $15,230";
    }

    public function schedule(string $datetime): void {
        echo "Report scheduled for {$datetime}";
    }

    public function sendTo(string $email): void {
        echo "Report sent to {$email}";
    }
}

$report = new SalesReport();
$report->export();               // Exporting report: Total Sales: $15,230
$report->schedule("2025-08-01"); // Report scheduled for 2025-08-01
$report->sendTo("ceo@co.com");   // Report sent to ceo@co.com
?>
```

---

## 5. Polymorphism

**Polymorphism** means "many forms." In OOP, it refers to the ability of different objects to respond to the same method call in their own unique way. You can treat different objects uniformly through a shared parent or interface, and each object will execute its own version of the method.

```php
<?php
abstract class PaymentMethod {
    abstract public function pay(float $amount): string;
}

class CreditCard extends PaymentMethod {
    public function pay(float $amount): string {
        return "Charged \${$amount} to Credit Card.";
    }
}

class Wallet extends PaymentMethod {
    public function pay(float $amount): string {
        return "Deducted \${$amount} from Wallet balance.";
    }
}

class BankTransfer extends PaymentMethod {
    public function pay(float $amount): string {
        return "Transferred \${$amount} via Bank Transfer.";
    }
}

$methods = [new CreditCard(), new Wallet(), new BankTransfer()];

foreach ($methods as $method) {
    echo $method->pay(250.00) . "\n";
}
// Charged $250 to Credit Card.
// Deducted $250 from Wallet balance.
// Transferred $250 via Bank Transfer.
?>
```

All three objects share the same `pay()` method name, but each one behaves differently depending on the payment type, that is polymorphism in action.