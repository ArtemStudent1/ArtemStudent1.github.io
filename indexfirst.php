<?php
// Подключение к базе данных
try {
    $pdo = new PDO("sqlite:todos_feedback.db");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    // Создание таблиц при необходимости
    $pdo->query("CREATE TABLE IF NOT EXISTS Feedback (id INTEGER PRIMARY KEY, Name TEXT, Message TEXT, Submitted INTEGER)");
    $pdo->query("CREATE TABLE IF NOT EXISTS Todos (id INTEGER PRIMARY KEY, Task TEXT, Complete INTEGER, Created INTEGER, Completed INTEGER, DueDate INTEGER, Notified INTEGER DEFAULT 0)");
    $pdo->query("CREATE TABLE IF NOT EXISTS Visits (id INTEGER PRIMARY KEY AUTOINCREMENT, Timestamp INTEGER, UserID TEXT)");

    // Проверка и добавление столбцов
    $columns = $pdo->query("PRAGMA table_info(Todos)")->fetchAll(PDO::FETCH_ASSOC);
    $hasDueDate = false;
    $hasNotified = false;
    foreach ($columns as $column) {
        if ($column['name'] === 'DueDate') {
            $hasDueDate = true;
        }
        if ($column['name'] === 'Notified') {
            $hasNotified = true;
        }
    }
    if (!$hasDueDate) {
        $pdo->query("ALTER TABLE Todos ADD COLUMN DueDate INTEGER");
    }
    if (!$hasNotified) {
        $pdo->query("ALTER TABLE Todos ADD COLUMN Notified INTEGER DEFAULT 0");
    }

    // Проверка и добавление столбца UserID, если его нет
    $columns = $pdo->query("PRAGMA table_info(Visits)")->fetchAll(PDO::FETCH_ASSOC);
    $hasUserID = array_search('UserID', array_column($columns, 'name')) !== false;
    if (!$hasUserID) {
        $pdo->query("ALTER TABLE Visits ADD COLUMN UserID TEXT");
    }

    // Инициализация идентификатора пользователя
    if (!isset($_COOKIE['userID'])) {
        $uniqueID = uniqid('user_', true);
        setcookie('userID', $uniqueID, time() + (86400 * 365), "/"); // Куки действует 1 год
        $_COOKIE['userID'] = $uniqueID;
    }
    $userID = $_COOKIE['userID'];

    // Вставка визита в базу данных
    try {
        $stmt = $pdo->prepare("INSERT INTO Visits (Timestamp, UserID) VALUES (strftime('%s', 'now'), :userID)");
        $stmt->execute([':userID' => $userID]);

        // Запис логов в файл
        $logFile = 'visits.log';
        $ip = $_SERVER['REMOTE_ADDR'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'];
        $time = date('Y-m-d H:i:s');
        $logMessage = "$time - IP: $ip | Agent: $userAgent\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    } catch (PDOException $e) {
        echo "Failed to record visit: " . $e->getMessage();
    }

    // Получение количества визитов для пользователя и общего количества визитов
    $userVisitCount = $pdo->prepare("SELECT COUNT(*) AS count FROM Visits WHERE UserID = :userID");
    $userVisitCount->execute([':userID' => $userID]);
    $userVisitCount = $userVisitCount->fetch(PDO::FETCH_ASSOC)['count'];

    $totalVisitCount = $pdo->query("SELECT COUNT(*) AS count FROM Visits")->fetch(PDO::FETCH_ASSOC)['count'];

} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}

$error = null;

// Создание новой задачи
if (isset($_POST["new-task"])) {
    $task = trim($_POST["new-task"]);
    $dueDate = isset($_POST["due-date"]) && $_POST["due-date"] ? strtotime($_POST["due-date"]) : null;

    if (empty($task)) {
        $error = "Задача не может быть пустой.";
    } else {
        try {
            $insert = $pdo->prepare("INSERT INTO Todos (Task, Complete, Created, DueDate) VALUES (:task, 0, strftime('%s', 'now'), :duedate)");
            $insert->execute([":task" => $task, ":duedate" => $dueDate]);

            // Перенаправление после успешного добавления задачи
            header("Location: indexfirst.php?lang=" . urlencode($lang));
            exit();
        } catch (PDOException $e) {
            $error = "Не удалось создать задачу: " . $e->getMessage();
        }
    }
}

// Завершить задачу
if (isset($_POST["complete"])) {
    try {
        $update = $pdo->prepare("UPDATE Todos SET Complete = 1, Completed = strftime('%s', 'now') WHERE id = :id");
        $update->execute([":id" => $_POST["id"]]);
    } catch (PDOException $e) {
        echo "Завершение задачи не удалось: " . $e->getMessage();
    }
}

// Отменить завершение задачи
if (isset($_POST["uncomplete"])) {
    try {
        $update = $pdo->prepare("UPDATE Todos SET Complete = 0, Completed = NULL WHERE id = :id");
        $update->execute([":id" => $_POST["id"]]);
    } catch (PDOException $e) {
        echo "Отмена завершения задачи не удалась: " . $e->getMessage();
    }
}

// Удалить одну задачу
if (isset($_POST["delete-one"])) {
    try {
        $delete = $pdo->prepare("DELETE FROM Todos WHERE id = :id");
        $delete->execute([":id" => $_POST["id"]]);
    } catch (PDOException $e) {
        echo "Удаление задачи не удалось: " . $e->getMessage();
    }
}

// Удалить все задачи
if (isset($_POST["delete-all"])) {
    try {
        $pdo->query("DELETE FROM Todos");
    } catch (PDOException $e) {
        echo "Удаление всех задач не удалось: " . $e->getMessage();
    }
}

// Отправка обратной связи
if (isset($_POST["submit-feedback"])) {
    try {
        $insert = $pdo->prepare("INSERT INTO Feedback (Name, Message, Submitted) VALUES (:name, :message, strftime('%s', 'now'))");
        $insert->execute([":name" => $_POST["name"], ":message" => $_POST["message"]]);
    } catch (PDOException $e) {
        echo "Отправка обратной связи не удалась: " . $e->getMessage();
    }
}

// Функция перевода
function getTranslation($lang, $key) {
    $translations = [
        "en" => ["title" => "To-do List", "new_task" => "New task", "add" => "Add", "delete_all" => "DELETE ALL TASKS", "feedback" => "Feedback", "submit" => "Submit", "search" => "Search tasks", "basket" => "Basket"],
        "uk" => ["title" => "Список справ на PHP", "new_task" => "Нове завдання", "add" => "Додати", "delete_all" => "ВИДАЛИТИ ВСІ ЗАВДАННЯ", "feedback" => "Зворотній зв'язок", "submit" => "Відправити", "search" => "Пошук задач", "basket" => "Кошик"],
        "ru" => ["title" => "Список дел на PHP", "new_task" => "Новое задание", "add" => "Добавить", "delete_all" => "УДАЛИТЬ ВСЕ ЗАДАНИЯ", "feedback" => "Обратная связь", "submit" => "Отправить", "search" => "Поиск задач", "basket" => "Корзина"]
    ];
    return $translations[$lang][$key] ?? $translations["en"][$key];
}

$lang = $_GET["lang"] ?? "en";

// Возвращаем результаты поиска или все задачи
$searchQuery = $_GET["search"] ?? '';
$tasksQuery = "SELECT * FROM Todos";
$params = [];

if ($searchQuery) {
    $tasksQuery .= " WHERE Task LIKE :search";
    $params[':search'] = '%' . $searchQuery . '%';
}
$tasksQuery .= " ORDER BY Complete ASC, Completed DESC, Created DESC";

$stmt = $pdo->prepare($tasksQuery);
$stmt->execute($params);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css" crossorigin="anonymous"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css"/>
    <title><?= getTranslation($lang, "title") ?></title>
    <style>
    body { 
        font-family: Arial, sans-serif; 
        background-color: #dee2e6; 
        margin: 0; 
        padding: 0; 
    }

    .notification {
        position: fixed;
        right: -400px;
        bottom: 20px;
        background-color: #fff3cd;
        color: #856404;
        border: 1px solid #ffeeba;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        width: 300px;
        transition: right 0.5s ease-in-out;
        z-index: 1000;
    }
    .notification.show {
        right: 20px;
    }
    .notification p {
        margin: 0;
        font-size: 16px;
        text-align: center;
    }
    .notification .close-btn {
        position: absolute;
        top: 5px;
        right: 10px;
        background: none;
        border: none;
        font-size: 20px;
        cursor: pointer;
        color: #856404;
    }

    /* Chat Widget Styles */
    #chatContainer { 
        position: fixed; 
        left: 10px; 
        bottom: 10px; 
        width: 420px; 
        height: 520px; 
        background: white; 
        padding: 10px; 
        border: 1px solid black; 
        border-radius: 10px; 
        box-shadow: 0 0 10px #ccc; 
        display: flex; 
        flex-direction: column; 
        z-index: 1001; 
    }
    #chatHeader { 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
    }
    #chatTitle { 
        font-size: 18px; 
        font-weight: bold; 
    }
    #closeBtn { 
        cursor: pointer; 
        font-size: 18px; 
    }
    #messageContainer { 
        flex-grow: 1; 
        overflow-y: auto; 
        padding: 10px; 
        background: #f9f9f9; 
        border: 1px solid #ddd; 
        border-radius: 10px; 
        margin-top: 10px; 
    }
    .message { 
        padding: 10px; 
        margin: 5px 0; 
        border-radius: 10px; 
        background-color: #e0e0e0; 
        width: fit-content; 
    }
    .admin { 
        background-color: #007bff; 
        color: white; 
    }
    .user { 
        background-color: #f1f1f1; 
    }
    .timestamp { 
        font-size: 0.8em; 
        color: #888; 
        margin-top: 5px; 
    }
    #nameInput, 
    #roleSelector, 
    #messageInput { 
        width: 100%; 
        padding: 10px; 
        border-radius: 5px; 
        border: 1px solid #ccc; 
        margin-top: 10px; 
    }
    #sendBtn { 
        padding: 10px 20px; 
        border: none; 
        border-radius: 5px; 
        background-color: #007bff; 
        color: white; 
        cursor: pointer; 
        margin-top: 10px; 
        width: 100%; 
    }
    #openChatBtn { 
        display: none; 
        position: fixed; 
        left: 10px; 
        bottom: 10px; 
        padding: 10px 20px; 
        border: none; 
        border-radius: 5px; 
        background-color: #007bff; 
        color: white; 
        cursor: pointer; 
        z-index: 1000; 
    }
</style>
</head>

<body class="d-flex flex-column" style="min-height: 100%; background-color: #dee2e6;">
<!-- Навигация -->
<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <a class="navbar-brand" href="?lang=<?= $lang ?>"><?= getTranslation($lang, "title") ?></a>
    <ul class="navbar-nav ml-auto">
        <li class="nav-item"><a class="nav-link" href="?lang=en">English</a></li>
        <li class="nav-item"><a class="nav-link" href="?lang=uk">Ukrainian</a></li>
        <li class="nav-item"><a class="nav-link" href="?lang=ru">Russian</a></li>
        <li class="nav-item"><a class="nav-link" href="kosyk.php"><?= getTranslation($lang, "basket") ?></a></li>
        <li class="nav-item"><a class="nav-link" href="tasks.php"></a></li>
    </ul>
    <!-- Поле поиска -->
    <form class="form-inline ml-2" method="GET">
        <input type="hidden" name="lang" value="<?= $lang; ?>"/>
        <input type="text" class="form-control mr-2" name="search" value="<?= htmlspecialchars($searchQuery); ?>" placeholder="<?= getTranslation($lang, "search") ?>" aria-label="Search"/>
        <button type="submit" class="btn btn-primary"><?= getTranslation($lang, "search") ?></button>
    </form>
</nav>

<div class="container" style="max-width: 720px;">
    <div class="alert alert-info" role="alert">
        Всього відвідувань сайту: <?= $totalVisitCount; ?>
        <br>Ваша кількість відвідувань цього сайту: <?= $userVisitCount; ?>
    </div>
    <div class="card my-4">
        <div class="card-body">
            <!-- Заголовок -->
            <h1 class="mb-4"><?= getTranslation($lang, "title") ?></h1>

            <!-- Сообщение об ошибке -->
            <?php if ($error): ?>
                <p class="alert alert-danger"><?= $error ?></p>
            <?php endif; ?>

            <!-- Форма новой задачи -->
            <form class="d-flex flex-column mb-4" method="POST">
                <div class="d-flex mb-2">
                    <input type="text" class="form-control mr-2" name="new-task" aria-label="<?= getTranslation($lang, "new_task") ?>"/>
                    <input type="text" class="form-control datepicker mr-2" name="due-date" placeholder="Due Date"/>
                </div>
                <button type="submit" class="btn btn-primary"><?= getTranslation($lang, "add") ?></button>
            </form>

            <!-- Список задач -->
            <ul class="list-group mb-4" id="task-list">
                <?php foreach ($tasks as $todo): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center task-item">
                        <div>
                            <?= $todo["Complete"] ? '<del class="text-secondary">' : ''; ?>
                            <?= htmlspecialchars($todo["Task"]); ?>
                            <?= $todo["Complete"] ? '</del>' : ''; ?>
                            <span class="ml-2 text-muted"><?= $todo["DueDate"] ? date("d.m.Y", $todo["DueDate"]) : 'No due date'; ?></span>
                        </div>

                        <!-- Кнопки завершения/удаления -->
                        <form class="btn-group" role="group" method="POST">
                            <button type="submit" name="<?= $todo["Complete"] ? 'un' : ''; ?>complete" class="btn btn-success<?= $todo["Complete"] ? ' active' : ''; ?>" aria-pressed="<?= $todo["Complete"] ? 'true' : 'false'; ?>" aria-label="Complete">
                                <i class="fas fa-check fa-fw"></i>
                            </button>
                            <input type="hidden" name="id" value="<?= $todo["id"]; ?>"/>
                            <button type="submit" name="delete-one" class="btn btn-danger" aria-label="Delete">
                                <i class="fas fa-trash-alt fa-fw"></i>
                            </button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>

            <!-- Кнопка удаления всех задач -->
            <form method="POST">
                <button type="submit" name="delete-all" class="btn btn-danger px-5 d-block mx-auto" <?= empty($tasks) ? 'disabled' : ''; ?>>
                    <?= getTranslation($lang, "delete_all") ?>
                </button>
            </form>
        </div>
    </div>

    <!-- Форма обратной связи -->
    <div class="card mb-4">
        <div class="card-body">
            <h2 class="mb-4"><?= getTranslation($lang, "feedback") ?></h2>
            <form method="POST">
                <input type="text" class="form-control mb-2" name="name" aria-label="Name" placeholder="Your Name"/>
                <textarea class="form-control mb-2" name="message" rows="4" aria-label="Message" placeholder="Your Message"></textarea>
                <button type="submit" name="submit-feedback" class="btn btn-primary"><?= getTranslation($lang, "submit") ?></button>
            </form>
        </div>
    </div>
</div>

<!-- Open Chat Button -->
<button id="openChatBtn">Open Chat</button>

<!-- Chat -->
<div id="chatContainer">
    <div id="chatHeader">
        <div id="chatTitle">Chat</div>
        <div id="closeBtn">&times;</div>
    </div>
    <div id="messageContainer"></div>
    <input type="text" id="nameInput" placeholder="Enter your name...">
    <select id="roleSelector">
        <option value="User">User</option>
        <option value="Admin">Admin</option>
    </select>
    <input type="text" id="messageInput" placeholder="Type a message...">
    <button id="sendBtn">Send</button>
</div>

<script>
    window.onload = function () {
        var conn = new WebSocket('ws://localhost:8080');
        var messageContainer = document.getElementById('messageContainer');
        var closeBtn = document.getElementById('closeBtn');
        var openChatBtn = document.getElementById('openChatBtn');
        var chatContainer = document.getElementById('chatContainer');

        var storedMessages = JSON.parse(localStorage.getItem('chatMessages')) || [];
        storedMessages.forEach(function(data) {
            appendMessage(data);
        });

        conn.onmessage = function(e) {
            var data = JSON.parse(e.data);
            appendMessage(data);
            storeMessage(data);
        };

        conn.onopen = function(e) { console.log("Connection established!"); };
        conn.onerror = function(e) { console.log("Connection error:", e); };
        conn.onclose = function(e) { console.log("Connection closed:", e); };

        document.getElementById('sendBtn').onclick = function() {
            var name = document.getElementById('nameInput').value;
            var role = document.getElementById('roleSelector').value;
            var message = document.getElementById('messageInput').value;
            var timestamp = new Date().toLocaleString();
            if (!name) {
                alert('Please enter your name.');
                return;
            }
            if (message.length > 350) {
                alert('Message exceeds 350 characters!');
                return;
            }
            if (message) {
                var dataToSend = JSON.stringify({ message: message, sender: name, senderRole: role, timestamp: timestamp });
                conn.send(dataToSend);
                document.getElementById('messageInput').value = '';
            } else {
                alert('Please enter a message.');
            }
        };

        function appendMessage(data) {
            var messageDiv = document.createElement('div');
            messageDiv.classList.add('message');
            messageDiv.classList.add(data.senderRole === 'Admin' ? 'admin' : 'user');
            messageDiv.textContent = `${data.sender}: ${data.message}`;
            var timestampDiv = document.createElement('div');
            timestampDiv.classList.add('timestamp');
            timestampDiv.textContent = data.timestamp;
            messageDiv.appendChild(timestampDiv);
            messageContainer.appendChild(messageDiv);
            messageContainer.scrollTop = messageContainer.scrollHeight;
        }

        function storeMessage(data) {
            var messages = JSON.parse(localStorage.getItem('chatMessages')) || [];
            messages.push(data);
            localStorage.setItem('chatMessages', JSON.stringify(messages));
        }

        closeBtn.onclick = function() {
            chatContainer.style.display = 'none';
            openChatBtn.style.display = 'block';
        };

        openChatBtn.onclick = function() {
            chatContainer.style.display = 'flex';
            openChatBtn.style.display = 'none';
        };
    };
</script>

<!-- Футер -->
<footer class="text-center mt-auto mb-3">
    <p>You could contact me whenever you like, if you have any questions! <br> artem.filatov@nure.ua</p>
    <a href="https://instagram.com/artem_q.wq" class="text-reset text-decoration-none">
        Artem&nbsp;Filatov&nbsp;
    </a>
    2024
</footer>

<!-- Скрипты для работы календарика -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>
<script>
    $(document).ready(function () {
        $('.datepicker').datepicker({
            format: 'dd.mm.yyyy',
            todayHighlight: true,
            autoclose: true
        });

        // Функция для проверки просроченных задач
        function checkTasks() {
            $.getJSON('check_tasks.php', function(data) {
                if (data.hasOverdueTasks) {
                    showNotification('Hello, you already have assignments with fired deadlines.');
                }
            });
        }

        // Показать уведомление
        function showNotification(message) {
            var notification = $('<div class="notification"><button class="close-btn">&times;</button><p>' + message + '</p></div>');
            $('body').append(notification);
            setTimeout(function() {
                notification.addClass('show');
            }, 100); // Плавное появление

            notification.find('.close-btn').click(function() {
                notification.removeClass('show');
                setTimeout(function() {
                    notification.remove();
                }, 500); // Плавное исчезновение
            });

            setTimeout(function() {
                notification.removeClass('show');
                setTimeout(function() {
                    notification.remove();
                }, 500); // Плавное исчезновение
            }, 30000); // Уведомление показывается 30 секунд
        }

        // Проверять задачи каждые 5 минут
        setInterval(checkTasks, 5 * 60 * 1000);
        // Проверка задач при загрузке страницы
        checkTasks();
    });
</script>
</body>
</html>