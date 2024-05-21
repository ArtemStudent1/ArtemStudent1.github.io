<?php
function startElement($parser, $name, $attrs) {
    $result = "<tr><td>Start tag: $name</td><td>Attributes:</td>";
    foreach ($attrs as $attr => $value) {
        $result .= "<td>$attr = $value</td>";
    }
    $result .= "</tr>";
    return $result;
}

function endElement($parser, $name) {
    return "<tr><td>End tag: $name</td></tr>";
}

function characterData($parser, $data) {
    $trimmedData = trim($data);
    if ($trimmedData) {
        return "<tr><td colspan='3'>Content: $trimmedData</td></tr>";
    }
    return '';
}

$xmlParser = xml_parser_create();
xml_set_element_handler($xmlParser, "startElement", "endElement");
xml_set_character_data_handler($xmlParser, "characterData");

$output = '';
$xmlFile = 'example.xml'; // Change the path to file
if (!($fp = fopen($xmlFile, "r"))) {
    die("Cannot open XML file $xmlFile");
}

while ($data = fread($fp, 4096)) {
    if (!xml_parse($xmlParser, $data, feof($fp))) {
        die(sprintf("XML Error: %s at line %d",
            xml_error_string(xml_get_error_code($xmlParser)),
            xml_get_current_line_number($xmlParser)));
    }
}

xml_parser_free($xmlParser);
fclose($fp);
?>

<table border="1">
    <?= $output ?>
</table>