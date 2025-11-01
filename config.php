<?php

function convertXmlToUtf8($filePath) {
    // Lire le contenu du fichier
    $xmlContent = file_get_contents($filePath);

    if ($xmlContent === false) {
        die("Error: Cannot read the XML file.");
    }

    // Remplacer l'ancienne déclaration d'encodage par UTF-8
    $xmlContent = preg_replace('/<\?xml[^>]+encoding="[^"]+"[^>]*\?>/', '<?xml version="1.0" encoding="UTF-8"?>', $xmlContent);

    // Convertir le contenu de ISO-8859-1 à UTF-8 si nécessaire
    $encoding = mb_detect_encoding($xmlContent, "UTF-8, ISO-8859-1, ISO-8859-15", true);
    if ($encoding === "ISO-8859-1" || $encoding === "ISO-8859-15") {
        $xmlContent = iconv($encoding, 'UTF-8', $xmlContent);
    }

    // Sauvegarder le fichier modifié
    file_put_contents($filePath, $xmlContent);

    return $xmlContent;
}

// Spécifiez le chemin vers votre fichier XML
$filePath = 'lille.xml';
$xmlContent = convertXmlToUtf8($filePath);

// Charger le contenu XML modifié en UTF-8
$xml = simplexml_load_string($xmlContent) or die("Error: Cannot create object");

?>
