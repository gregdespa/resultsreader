<?php
// Charger le fichier XML
//$xml = simplexml_load_file('lille.xml') or die("Error: Cannot create object");
require_once("config.php");
// Fonction pour formater les armes
function formatArme($arme) {
    switch ($arme) {
        case 'F':
            return 'Fleuret';
        case 'E':
            return 'Épée';
        case 'S':
            return 'Sabre';
        default:
            return $arme;
    }
}

// Fonction pour formater le sexe
function formatSexe($sexe) {
    switch (strtolower($sexe)) {
        case 'm':
            return 'Masculin';
        case 'f':
            return 'Féminin';
        default:
            return $sexe;
    }
}

// Fonction pour formater la date
function formatDate($date) {
    $months = [
        "01" => "Janvier", "02" => "Février", "03" => "Mars", "04" => "Avril",
        "05" => "Mai", "06" => "Juin", "07" => "Juillet", "08" => "Août",
        "09" => "Septembre", "10" => "Octobre", "11" => "Novembre", "12" => "Décembre"
    ];

    $dateParts = explode(".", $date);
    $day = ltrim($dateParts[0], '0'); // Supprimer les zéros initiaux
    $month = $months[$dateParts[1]];
    $year = $dateParts[2];

    return "$day $month $year";
}

// Fonction pour obtenir une valeur avec vérification de l'existence
function getValue($xml, $field) {
    return isset($xml[$field]) ? htmlentities($xml[$field], ENT_QUOTES | ENT_XML1, 'UTF-8') : '';
}

// Fonction pour obtenir le domaine
function getDomaine($xml) {
    if (isset($xml->Tireurs)) {
        return 'Individuel';
    } elseif (isset($xml->Equipes)) {
        return 'Équipe';
    } else {
        return '';
    }
}

// Calcul des informations pour la formule
$tireursCount = 0;
$poulesCount = 0;
$qualifiesCount = 0;
$poulesSizes = [];
$decalage = '';
$hasPoules = false;
$hasTableauDirect = false;
$competitorType = '';

if (isset($xml->Phases->TourDePoules)) {
    $hasPoules = true;
    if (isset($xml->Phases->TourDePoules->Poule)) {
        foreach ($xml->Phases->TourDePoules->Poule as $poule) {
            $size = count($poule->Tireur);
            if (!isset($poulesSizes[$size])) {
                $poulesSizes[$size] = 0;
            }
            $poulesSizes[$size]++;
        }
    }
    if (isset($xml->Phases->TourDePoules->Tireur)) {
        $tireursCount = count($xml->Phases->TourDePoules->Tireur);
        foreach ($xml->Phases->TourDePoules->Tireur as $tireur) {
            if ((string) $tireur['Statut'] === 'Q') {
                $qualifiesCount++;
            }
        }
        $competitorType = 'Tireur';
    } elseif (isset($xml->Phases->TourDePoules->Equipe)) {
        $tireursCount = count($xml->Phases->TourDePoules->Equipe);
        foreach ($xml->Phases->TourDePoules->Equipe as $equipe) {
            if ((string) $equipe['Statut'] === 'Q') {
                $qualifiesCount++;
            }
        }
        $competitorType = 'Equipe';
    }
    $decalage = getValue($xml->Phases->TourDePoules, 'Decalage');
}

if (isset($xml->Phases->PhaseDeTableaux) && !$hasPoules) {
    $hasTableauDirect = true;
    if (isset($xml->Phases->PhaseDeTableaux->Tireur)) {
        $tireursCount = count($xml->Phases->PhaseDeTableaux->Tireur);
        $competitorType = 'Tireur';
    } elseif (isset($xml->Phases->PhaseDeTableaux->Equipe)) {
        $tireursCount = count($xml->Phases->PhaseDeTableaux->Equipe);
        $competitorType = 'Equipe';
    }
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Résultat de la compétition</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <header>
        <nav class="menu-bar">
            <ul>
                <li><a href="index.php">Accueil</a></li>
                <li><a href="inscrits.php">Inscrits</a></li>
                <?php if ($hasPoules): ?>
                    <li><a href="poules.php">Poules</a></li>
                    <li><a href="classement_p.php">Classement Intermédiaire</a></li>
                <?php endif; ?>
                <li><a href="tableau.php#">Tableau</a></li>
                <li><a href="classement_f.php">Classement Final</a></li>
            </ul>
        </nav>
        <section class="subtitle">
            <h1><?php echo getValue($xml, 'TitreLong'); ?></h1>
            <h2>
                <?php
                // Affichage du lien
                    echo '<a href="' . htmlspecialchars(getValue($xml, 'URLorganisateur'), ENT_QUOTES, 'UTF-8') . '">' . getValue($xml, 'Organisateur') . '</a>';
                ?>
            </h2>
            <h3><?php echo formatDate(getValue($xml, 'Date')); ?></h3>
        </section>
    </header>
    <main>
        <div class="results-table-container">
            <section class="results-table">
                <table>
                    <thead>
                        <tr>
                            <th>Titre</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Catégorie</th>
                            <th>Arme</th>
                            <th>Sexe</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?php echo getValue($xml, 'TitreLong'); ?></td>
                            <td><?php echo formatDate(getValue($xml, 'Date')); ?></td>
                            <td><?php echo getDomaine($xml); ?></td>
                            <td><?php echo getValue($xml, 'Categorie'); ?></td>
                            <td><?php echo formatArme(getValue($xml, 'Arme')); ?></td>
                            <td><?php echo formatSexe(getValue($xml, 'Sexe')); ?></td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </div>
        <div class="formula-container">
            <section class="formula">
                <h2>Formule de l'épreuve</h2>
                <?php if ($hasPoules): ?>
                    <p><strong><?php echo $tireursCount; ?> <?php echo strtolower($competitorType) . 's'; ?></strong></p>
                    <p>1 tour de poules</p>
                    <?php foreach ($poulesSizes as $size => $count): ?>
                        <p><?php echo $count; ?> poules </p>
                    <?php endforeach; ?>
                    <p><?php echo $qualifiesCount; ?> qualifiés</p>
                    <hr>
                    <p>Élimination directe : <?php echo $qualifiesCount; ?> <?php echo strtolower($competitorType) . 's'; ?></p>
                    <p>Tableau direct</p>
                <?php elseif ($hasTableauDirect): ?>
                    <p>Tableau direct : <?php echo $tireursCount; ?> <?php echo strtolower($competitorType) . 's'; ?></p>
                <?php else: ?>
                    <p>Aucune information sur les poules ou les tableaux trouvée.</p>
                <?php endif; ?>
            </section>
        </div>
    </main>
</body>
</html>
