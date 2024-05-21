<?php
// Подключение к базе данных
try {
    $pdo = new PDO("sqlite:todos_feedback.db");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    // Создаем таблицу для корзины, если ее еще нет
    $pdo->query("CREATE TABLE IF NOT EXISTS BasketItems (id INTEGER PRIMARY KEY, Item TEXT, Quantity INTEGER)");
} catch (PDOException $e) {
    die("Підключення не вдалося: " . $e->getMessage());
}

// Функция получения текущего количества каждого продукта в корзине
function getCurrentQuantities($pdo) {
    $quantities = [];
    $stmt = $pdo->query("SELECT Item, SUM(Quantity) as total FROM BasketItems GROUP BY Item");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $quantities[$row['Item']] = (int)$row['total'];
    }
    return $quantities;
}

// Лимит максимального количества
$maxQuantity = 10;

// Получаем текущее количество продуктов
$currentQuantities = getCurrentQuantities($pdo);

// Додавання фруктів/овочів у корзину
if (isset($_POST['item']) && isset($_POST['quantity'])) {
    $item = $_POST['item'];
    $quantity = (int) $_POST['quantity'];

    // Проверка, сколько осталось добавить
    $available = $maxQuantity - ($currentQuantities[$item] ?? 0);
    if ($quantity <= $available) {
        $insert = $pdo->prepare("INSERT INTO BasketItems (Item, Quantity) VALUES (:item, :quantity)");
        if (!$insert->execute([':item' => $item, ':quantity' => $quantity])) {
            $error = "Не вдалося додати елемент.";
        }
        // Обновление текущего количества
        $currentQuantities[$item] = ($currentQuantities[$item] ?? 0) + $quantity;
    } else {
        $error = "Максимальна кількість для $item - $maxQuantity. Доступно для додавання - $available.";
    }
}

// Вибір фруктів/овощів із корзини
try {
    $items_select = $pdo->query("SELECT * FROM BasketItems ORDER BY id DESC");
    $items = $items_select ? $items_select->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (PDOException $e) {
    $error = "Не вдалося завантажити дані: " . $e->getMessage();
    $items = [];
}

// Видалення одного елемента з корзини
if (isset($_POST['delete-one'])) {
    $delete = $pdo->prepare("DELETE FROM BasketItems WHERE id = :id");
    if (!$delete->execute([':id' => $_POST['id']])) {
        $error = "Не вдалося видалити елемент.";
    }
}

// Видалення всіх елементів з корзини
if (isset($_POST['delete-all'])) {
    if (!$pdo->query("DELETE FROM BasketItems")) {
        $error = "Не вдалося видалити всі елементи.";
    } else {
        $currentQuantities = [];
    }
}

// Доступные продукты для корзины
$availableProducts = [
    "Яблуко" => "Яблуко",
    "Апельсин" => "Апельсин",
    "Банан" => "Банан",
    "Морква" => "Морква",
    "Помідор" => "Помідор"
];

// HTML and PHP mixed below...
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css" crossorigin="anonymous" />
    <title>Список Кошик</title>
</head>
<body class="d-flex flex-column" style="min-height: 100%; background-color: #dee2e6;">

    <!-- Навигационная панель -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <a class="navbar-brand" href="indexfirst.php">Main page with To-Do List</a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item active">
                        <a class="nav-link" href="#">Кошик <span class="sr-only">(текущее)</span></a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container" style="max-width: 720px;">
        <div class="card my-4">
            <div class="card-body">
                <h1 class="mb-4">Ваш Кошик</h1>
                <?php if (isset($error)): ?>
                    <p class="alert alert-danger"><?= $error ?></p>
                <?php endif; ?>
                <form method="POST" class="mb-4">
                    <select name="item" class="form-control mb-2">
                        <?php foreach ($availableProducts as $key => $label): ?>
                            <?php
                            // Подсчет остатка
                            $available = $maxQuantity - ($currentQuantities[$key] ?? 0);
                            ?>
                            <option value="<?= htmlspecialchars($key) ?>" <?= $available <= 0 ? 'disabled' : '' ?>>
                                <?= htmlspecialchars($label) ?> (осталось: <?= $available ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="number" class="form-control mb-2" name="quantity" placeholder="Кількість" min="1" max="10" />
                    <button type="submit" class="btn btn-success">Додати в Кошик</button>
                </form>

                <!-- Список предметів у корзині -->
                <ul class="list-group mb-4">
                    <?php if (count($items) > 0): ?>
                        <?php foreach ($items as $item): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <?= htmlspecialchars($item["Item"]); ?> - <?= (int)$item["Quantity"]; ?> шт
                                <form method="POST" class="btn-group" role="group">
                                    <input type="hidden" name="id" value="<?= $item["id"]; ?>" />
                                    <button type="submit" name="delete-one" class="btn btn-danger">Видалити</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="list-group-item">Корзина порожня</li>
                    <?php endif; ?>
                </ul>

                <!-- Кнопка видалення всіх елементів -->
                <form method="POST">
                    <button type="submit" name="delete-all" class="btn btn-danger px-5 d-block mx-auto">
                        Видалити всі елементи
                    </button>
                    <div class="text-center">
    <a href="https://media1.giphy.com/media/v1.Y2lkPTc5MGI3NjExdzgzcTNiNnRkdWNrcTRreTBxZWNmZXBsdzNsOWdwNnBwdHMwajIybCZlcD12MV9pbnRlcm5hbF9naWZfYnlfaWQmY3Q9Zw/nUTBzjL2Svb8Jsljvf/giphy.gif" class="btn btn-warning px-5 mt-3 mx-auto">Оплатити</a>
</div>
                </form>
            </div>
        </div>
    </div>

    <footer class="text-center mt-auto mb-3">
        <p> You could contact me whenever your like, if you have any questions! <br> artem.filatov@nure.ua</p>
        <a href="https://instagram.com/artem_q.wq" class="text-reset text-decoration-none">
            Artem&nbsp;Filatov&nbsp;
        </a>
        2024
    </footer>
</body>
</html>