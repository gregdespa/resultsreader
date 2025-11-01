<?php

require_once("config.php");

// Get the referee ID from the URL
$id = isset($_GET['id']) ? $_GET['id'] : null;

if ($id === null) {
    die("Error: No ID provided.");
}



// Function to get value with existence check
function getValue($xml, $field) {
    return isset($xml[$field]) ? htmlentities($xml[$field], ENT_QUOTES | ENT_XML1, 'UTF-8') : '';
}

// Function to format date
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

// Function to retrieve the referee by ID
function getRefereeById($xml, $id) {
    foreach ($xml->Arbitres->Arbitre as $arbitre) {
        if ((string)$arbitre['ID'] === $id) {
            return $arbitre;
        }
    }
    return null;
}

// Function to retrieve the matches refereed by the referee
function getRefereeMatches($xml, $id) {
    $matches = [];
    foreach ($xml->Phases as $phase) {
        foreach($phase->TourDePoules as $tour){
            foreach($tour->Poule as $poule){
                foreach ($poule->Match as $match) {
                    if ((string)$poule->Arbitre['REF'] === $id) {
                        $match->addAttribute('PouleID', (string)$poule['ID']);
                        $match->addAttribute('TourID', (string)$tour['ID']);
                        $matches[] = $match;
                    }
                }

            }
        }
       
    }
    foreach ($xml->Phases->PhaseDeTableaux->SuiteDeTableaux as $suite) {
        foreach ($suite->Tableau as $tableau) {
            foreach ($tableau->Match as $match) {
                foreach ($match->Arbitre as $arbitre) {
                    if ((string)$arbitre['REF'] === $id) {
                        $match->addAttribute('TableauID', (string)$tableau['ID']);
                        $match->addAttribute('SuiteID', (string)$suite['ID']);
                        $matches[] = $match;
                    }
                }
            }
        }
    }
    return $matches;
}

// Function to get the level of the referee
function getRefereeLevel($referee) {
    switch ((string)$referee['Categorie']) {
        case 'I':
            return 'International';
        case 'N':
            return 'National';
        case 'FN':
            return 'Formation National';
        case 'R':
            return 'Régional';
        case 'FR':
            return 'Formation Régional';
        case 'D':
            return 'Départemental';
        case 'FD':
            return 'Formation Départemental';
        default:
            return 'Unknown';
    }
}

// Retrieve the referee's information
$referee = getRefereeById($xml, $id);
if ($referee === null) {
    die("Error: Referee not found.");
}

// Retrieve the referee's matches
$matches = getRefereeMatches($xml, $id);

// Count the number of matches refereed
$matchesCount = count($matches);

// Define $hasPoules based on whether there are poules matches or not
$hasPoules = isset($xml->Phases->TourDePoules);

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiche Arbitre</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        /* Styles globaux */
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            min-height: 100vh;
            padding: 20px;
        }

        /* En-tête */
        header {
            width: 100%;
            text-align: center;
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            background-color: #ffffff;
        }

        header h1, header h2, header h3 {
            margin: 0;
            padding: 10px 0;
        }

        .subtitle h1 {
            font-size: 24px;
        }

        .subtitle h2 {
            font-size: 20px;
            color: #555;
        }

        .subtitle h3 {
            font-size: 16px;
            color: #777;
        }

        /* Barre de menu */
        .menu-bar {
            background-color: #004080;
            padding: 10px 0;
        }

        .menu-bar ul {
            list-style-type: none;
            display: flex;
            justify-content: center;
            gap: 20px;
        }

        .menu-bar li {
            display: inline;
        }

        .menu-bar a {
            text-decoration: none;
            color: #fff;
            font-size: 18px;
            font-weight: bold;
        }

        /* Conteneur principal */
        main {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* Conteneur des informations de l'arbitre */
        .ref-info {
            width: 100%;
            max-width: 800px;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            padding: 20px;
            text-align: center;
        }

        .ref-info h2 {
            font-size: 22px;
            margin-bottom: 10px;
        }

        .ref-info p {
            font-size: 18px;
            color: #333;
        }

        /* Conteneur des matchs */
        .ref-matches {
            width: 100%;
            max-width: 800px;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-top: 20px;
        }

        .ref-matches h3 {
            font-size: 20px;
            margin-bottom: 15px;
            color: #333;
        }

        .ref-matches .poules-matches,
        .ref-matches .tableau-matches {
            margin-bottom: 20px;
        }

        .ref-matches h4 {
            font-size: 18px;
            margin-bottom: 10px;
            color: #555;
        }

        .ref-matches ul {
            list-style-type: none;
            padding: 0;
        }

        .ref-matches li {
            padding: 10px 0;
            border-bottom: 1px solid #ddd;
            font-size: 16px;
            color: #333;
            cursor: pointer; /* Pointer cursor to indicate clickable items */
        }

        /* Styles pour le tableau */
        .results-table-container {
            width: 100%;
            max-width: 800px;
            overflow-x: auto;
            cursor: pointer; /* For responsive table */
        }

        .results-table table {
            width: 100%;
            border-collapse: collapse;
            cursor: pointer;
        }

        .results-table th, .results-table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
            cursor: pointer;
        }

        .results-table th {
            background-color: #004080;
            color: #ffffff;
        }

        .results-table td {
            background-color: #ffffff;
            color: #333;
        }

        .results-table td:hover {
            background-color: #f4f4f9;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var matches = document.querySelectorAll('.ref-matches li');
            matches.forEach(function (match) {
                match.addEventListener('click', function () {
                    var matchId = this.getAttribute('data-match-id');
                    var tableauId = this.getAttribute('data-tableau-id');
                    var suiteId = this.getAttribute('data-suite-id');
                    var pouleId = this.getAttribute('data-poule-id');
                    var tourId = this.getAttribute('data-tour-id');
                    if (pouleId && tourId) {
                        window.location.href = 'match.php?tour_id=' + tourId + '&poule_id=' + pouleId + '&match_id=' + matchId;
                    } else {
                        window.location.href = 'match.php?suite_id=' + suiteId + '&tableau_id=' + tableauId + '&match_id=' + matchId;
                    }
                });
            });
        });
    </script>
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
                <li><a href="tableau.php">Tableau</a></li>
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
        <section class="ref-info">
            <h2><?php echo getValue($referee, 'Nom') . ' ' . getValue($referee, 'Prenom'); ?></h2>
            <p>Niveau: <?php echo getRefereeLevel($referee); ?></p>
            <p>Club: <?php echo getValue($referee, 'Club'); ?></p>
            <p>Nation: <?php echo getValue($referee, 'Nation'); ?></p>
            <p>Nombre de matchs arbitrés: <?php echo $matchesCount; ?></p>
        </section>

        <section class="ref-matches">
            <h3>Matchs arbitrés</h3>
            <div class="poules-matches">
                <?php if ($hasPoules): ?>
                    <h4>Matchs de poule</h4>
                    <ul>
                        <?php foreach ($matches as $match): ?>
                            <?php if (isset($match['PouleID'])): ?>
                                <li data-match-id="<?php echo $match['ID']; ?>" data-poule-id="<?php echo $match['PouleID']; ?>" data-tour-id="<?php echo $match['TourID']; ?>">
                                    <?php
                                    if (isset($match->Equipe[0])) {
                                        $team1 = $xml->xpath("//Equipe[@ID='{$match->Equipe[0]['REF']}']")[0];
                                        $team2 = $xml->xpath("//Equipe[@ID='{$match->Equipe[1]['REF']}']")[0];
                                        echo getValue($team1, 'Nom') . ' ' . (int)$match->Equipe[0]['Score'] . ' - ' . (int)$match->Equipe[1]['Score'] . ' ' . getValue($team2, 'Nom');
                                    } else {
                                        $tireur1 = $xml->xpath("//Tireur[@ID='{$match->Tireur[0]['REF']}']")[0];
                                        $tireur2 = $xml->xpath("//Tireur[@ID='{$match->Tireur[1]['REF']}']")[0];
                                        echo getValue($tireur1, 'Nom') . ' ' . (int)$match->Tireur[0]['Score'] . ' - ' . (int)$match->Tireur[1]['Score'] . ' ' . getValue($tireur2, 'Nom');
                                    }
                                    ?>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <div class="tableau-matches">
                <h4>Matchs de tableau</h4>
                <ul>
                    <?php foreach ($matches as $match): ?>
                        <?php if (isset($match['TableauID'])): ?>
                            <li data-match-id="<?php echo $match['ID']; ?>" data-tableau-id="<?php echo $match['TableauID']; ?>" data-suite-id="<?php echo $match['SuiteID']; ?>">
                                <?php
                                if (isset($match->Equipe[0])) {
                                    $team1 = $xml->xpath("//Equipe[@ID='{$match->Equipe[0]['REF']}']")[0];
                                    $team2 = $xml->xpath("//Equipe[@ID='{$match->Equipe[1]['REF']}']")[0];
                                    $tableauTitle = getValue($xml->xpath("//PhaseDeTableaux//Tableau[@ID='{$match['TableauID']}']")[0], 'Titre');
                                    echo $tableauTitle . ' : ' . getValue($team1, 'Nom') . ' ' . (int)$match->Equipe[0]['Score'] . ' - ' . (int)$match->Equipe[1]['Score'] . ' ' . getValue($team2, 'Nom');
                                } else {
                                    $tireur1 = $xml->xpath("//Tireur[@ID='{$match->Tireur[0]['REF']}']")[0];
                                    $tireur2 = $xml->xpath("//Tireur[@ID='{$match->Tireur[1]['REF']}']")[0];
                                    $tableauTitle = getValue($xml->xpath("//PhaseDeTableaux//Tableau[@ID='{$match['TableauID']}']")[0], 'Titre');
                                    echo $tableauTitle . ' : ' . getValue($tireur1, 'Nom') . ' ' . (int)$match->Tireur[0]['Score'] . ' - ' . (int)$match->Tireur[1]['Score'] . ' ' . getValue($tireur2, 'Nom');
                                }
                                ?>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>
    </main>
</body>
</html>
