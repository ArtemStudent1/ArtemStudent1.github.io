<?php
// Подключение к базе данных
try {
    $pdo = new PDO("sqlite:todos_feedback.db");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $currentTime = time();
    $stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM Todos WHERE Complete = 0 AND DueDate IS NOT NULL AND DueDate < :currentTime AND Notified = 0");
    $stmt->execute([':currentTime' => $currentTime]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    if ($count > 0) {
        // Обновление поля Notified для просроченных задач
        $update = $pdo->prepare("UPDATE Todos SET Notified = 1 WHERE Complete = 0 AND DueDate IS NOT NULL AND DueDate < :currentTime AND Notified = 0");
        $update->execute([':currentTime' => $currentTime]);
    }

    echo json_encode(['hasOverdueTasks' => $count > 0]);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>