<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Лабораторна №3</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f8ff;
            color: #333;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .container {
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            width: 80%;
            max-width: 600px;
            text-align: center;
        }
        h1, h2 {
            color: #007acc;
        }
        form {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin: 10px 0 5px;
        }
        textarea, input[type="number"], input[type="submit"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        input[type="submit"] {
            background-color: #007acc;
            color: #fff;
            border: none;
            cursor: pointer;
        }
        input[type="submit"]:hover {
            background-color: #005b99;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>PHP Завдання</h1>

        <!-- Форма для пошуку e-mail адрес у тексті -->
        <h2>Знайти e-mail адреси у тексті</h2>
        <form method="post">
            <label for="emailText">Введіть текст:</label>
            <textarea id="emailText" name="emailText" rows="4" cols="50"></textarea>
            <input type="submit" name="findEmails" value="Знайти e-mail адреси">
        </form>

        <?php
        // Функція для пошуку e-mail адрес у тексті
        function findEmails($text) {
            preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $text, $matches);
            return $matches[0];
        }

        // Обробка форми для пошуку e-mail адрес
        if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['findEmails'])) {
            $emailText = $_POST['emailText'];
            $emails = findEmails($emailText);
            if (!empty($emails)) {
                echo "<h3>Знайдені e-mail адреси:</h3>";
                echo "<ul>";
                foreach ($emails as $email) {
                    echo "<li>$email</li>";
                }
                echo "</ul>";
            } else {
                echo "<p>Не знайдено жодної e-mail адреси.</p>";
            }
        }
        ?>

        <!-- Форма для визначення дня тижня за датою -->
        <h2>Визначення дня тижня за датою</h2>
        <form method="post">
            <label for="day">День:</label>
            <input type="number" id="day" name="day" min="1" max="31" required>
            <label for="month">Місяць:</label>
            <input type="number" id="month" name="month" min="1" max="12" required>
            <label for="year">Рік:</label>
            <input type="number" id="year" name="year" required>
            <input type="submit" name="findDayOfWeek" value="Визначити день тижня">
        </form>

        <?php
        // Функція для визначення дня тижня за датою
        function dayOfWeek($day, $month, $year) {
            $date = DateTime::createFromFormat('d m Y', "$day $month $year");
            return $date->format('l');
        }

        // Обробка форми для визначення дня тижня за датою
        if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['findDayOfWeek'])) {
            $day = $_POST['day'];
            $month = $_POST['month'];
            $year = $_POST['year'];
            $dayOfWeek = dayOfWeek($day, $month, $year);
            echo "<p>Дата: $day/$month/$year - це $dayOfWeek.</p>";
        }
        ?>
    </div>
</body>
</html>