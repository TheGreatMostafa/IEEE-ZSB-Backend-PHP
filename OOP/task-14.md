# Task 14 - OOP Research Questions

---

## 1. Class vs. Object

A **Class** represents the blueprint of any entity or object. It tells us how an object looks and behaves, but the class does not have a physical existence.

An **Object** is the physical entity created using that class definition. We may create multiple objects from a single class, and all of those objects store their data separately.

**Analogy:** A recipe serves as a class, whereas a dish created according to that recipe is the object.

```php
<?php
class Recipe {
    public string $name;
    public int $servings;

    public function __construct(string $name, int $servings) {
        $this->name = $name;
        $this->servings = $servings;
    }

    public function describe(): string {
        return "{$this->name} - serves {$this->servings}";
    }
}

$dish1 = new Recipe("Pasta", 2);
$dish2 = new Recipe("Pizza", 4);

echo $dish1->describe(); // Pasta - serves 2
echo $dish2->describe(); // Pizza - serves 4
?>
```

---

## 2. $this vs. self::

- **$this** points to the object instance. Used when accessing non-static members of the class.
- **self::** points to the class name. Used when accessing static properties, static functions, and constants in a class.

```php
<?php
class Student {
    private string $name;
    private static int $studentCount = 0;
    const SCHOOL = "IEEE Academy";

    public function __construct(string $name) {
        $this->name = $name;        // $this -> this specific object
        self::$studentCount++;      // self:: -> class-level counter
    }

    public function getName(): string {
        return $this->name;
    }

    public function getSchool(): string {
        return self::SCHOOL;
    }

    public static function getStudentCount(): int {
        return self::$studentCount;
    }
}

$s1 = new Student("Ali");
$s2 = new Student("Omar");

echo Student::getStudentCount(); // 2
?>
```

---

## 3. Access Modifiers (Encapsulation)

- **public**: accessible everywhere, within the same class, inherited by child classes, and externally.
- **protected**: only accessible within the class and any inheriting class.
- **private**: only accessible inside the class where it was defined.

### Why use the private access modifier?

To prevent data from getting modified arbitrarily without validation. When data is declared public, there would be no check when the property is assigned any value.

```php
<?php
class Player {
    private int $health = 100;

    public function takeDamage(int $amount): void {
        if ($amount < 0) {
            return;
        }
        $this->health = max(0, $this->health - $amount);
    }

    public function getHealth(): int {
        return $this->health;
    }
}

$player = new Player();
$player->takeDamage(30);
echo $player->getHealth(); // 70

// $player->health = -999; // error, can't access private property
?>
```

---

## 4. Typed Properties

Typed properties enable the declaration of the actual data type a particular property should store. PHP will throw an error as soon as you attempt to assign a property the wrong type, rather than allowing the problematic assignment to go unnoticed somewhere else in your program.

```php
<?php
// without types - wrong values can slip in silently
class Order {
    public $items;
    public $total;
    public $isPaid;
}

$order = new Order();
$order->total = "free"; // no error, but will break later

// with types - caught immediately
class Order {
    public array $items;
    public float $total;
    public bool $isPaid;
    public ?string $couponCode; // string or null
}

$order = new Order();
$order->total = "free"; // TypeError thrown right here
?>
```

---

## 5. Constructor Methods

The `__construct()` method is a non-returning function that gets called right away when a new instance is created by the `new` operator. It is tasked with the initialization of an object, ensuring that the object is always correctly initialized.

By requiring arguments, you ensure that the person creating an object provides all the needed information, hence cannot create any invalid objects.

```php
<?php
// without constructor - easy to miss a property
$ticket = new Ticket();
$ticket->event = "Concert";
// forgot seat and price, object is incomplete

// with constructor - all data must be provided upfront
class Ticket {
    public function __construct(
        private string $event,
        private string $seat,
        private float $price
    ) {
        if ($this->price < 0) {
            throw new InvalidArgumentException("Price cannot be negative.");
        }
    }

    public function summary(): string {
        return "{$this->event} | Seat: {$this->seat} | \${$this->price}";
    }
}

$ticket = new Ticket("Concert", "A12", 49.99);
echo $ticket->summary(); // Concert | Seat: A12 | $49.99
?>
```