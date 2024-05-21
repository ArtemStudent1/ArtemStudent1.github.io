<?php
function startTag($parser, $tag, $attributes, &$dataTable) {
    // Додаємо рядок у таблицю для відкриваючого тегу
    $attrText = '';
    foreach ($attributes as $key => $value) {
        $attrText .= "$key='$value' ";
    }
    $dataTable .= "<tr><td>Відкриваючий тег: $tag</td><td>$attrText</td></tr>";
}

function endTag($parser, $tag, &$dataTable) {
    // Додаємо рядок у таблицю для закриваючого тегу
    $dataTable .= "<tr><td>Закриваючий тег: $tag</td><td></td></tr>";
}

function characterData($parser, $data, &$dataTable) {
    $trimmedData = trim($data);
    if (!empty($trimmedData)) {
        // Додаємо рядок у таблицю для текстового вмісту
        $dataTable .= "<tr><td>Текст:</td><td>" . htmlspecialchars($trimmedData) . "</td></tr>";
    }
}

// Створення парсера
$parser = xml_parser_create();
$dataTable = "<table border='1'>";

// Реєстрація обробників
xml_set_element_handler($parser, function($parser, $tag, $attributes) use (&$dataTable) {
    startTag($parser, $tag, $attributes, $dataTable);
}, function($parser, $tag) use (&$dataTable) {
    endTag($parser, $tag, $dataTable);
});

xml_set_character_data_handler($parser, function($parser, $data) use (&$dataTable) {
    characterData($parser, $data, $dataTable);
});

// HTML, який потрібно обробити
$htmlContent = "<html><head><title>Example</title></head><body><p>Hello, world!</p></body></html>";

// Парсинг HTML
if (!xml_parse($parser, $htmlContent, true)) {
    $dataTable .= "<tr><td>Error:</td><td>" . xml_error_string(xml_get_error_code($parser)) . "</td></tr>";
}

$dataTable .= "</table>";
xml_parser_free($parser);

// Вивід результатів
echo $dataTable;
?>