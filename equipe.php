<?php

require_once("config.php");

// Get the team ID from the URL
$id = isset($_GET['id']) ? $_GET['id'] : null;

if ($id === null) {
    die("Error: No ID provided.");
}



// Fonction pour obtenir une valeur avec vérification de l'existence
function getValue($xml, $field) {
    return isset($xml[$field]) ? htmlentities($xml[$field], ENT_QUOTES | ENT_XML1, 'UTF-8') : '';
}

function getTableauTitleById($xml, $tableauId) {
    foreach ($xml->Phases->PhaseDeTableaux->SuiteDeTableaux as $suite) {
        foreach ($suite->Tableau as $tableau) {
            if ((string)$tableau['ID'] === $tableauId) {
                return (string)$tableau['Titre'];
            }
        }
    }
    return '';
}

function getTeamID($fencerID, $xml){
    foreach($xml->Equipes->Equipe as $equipe){
        foreach($equipe->Tireur as $tireur){
            if ((string)$tireur['ID'] === (string)$fencerID) {
                return (string)$equipe['ID'];
            }
        }
    }
    return null;
}

function calculateTouches($match, $teamId, $xml) {
    $touches = []; // Tableau pour stocker les touches par assaut
    $prevTD = 0;
    $prevTR = 0;

    foreach ($match->Assaut as $assault) {
        $matchId = (string)$assault['ID'];

        $tireur0 = null;
        $tireur1 = null;

        // Sélectionner les tireurs et scores pour chaque assaut
        foreach ($assault->Tireur as $tireur) {
            $ttireur = getTeamID($tireur['REF'], $xml);
            if ($ttireur === $teamId) {
                $tireur0 = $tireur;
            } else {
                $tireur1 = $tireur;
            }
        }

        // Vérifier que les tireurs ont été trouvés
        if (!$tireur0 || !$tireur1) {
            continue; // Sauter l'assaut si les tireurs ne sont pas trouvés
        }

        $score0 = (int)$tireur0['Score'];
        $score1 = (int)$tireur1['Score'];

        // Initialiser les touches pour ce matchId si ce n'est pas déjà fait
        if (!isset($touches[$matchId])) {
            $touches[$matchId] = ['TD' => 0, 'TR' => 0];
        }

        // Calculer les touches données et reçues pour cet assaut
        $td = $score0 - $prevTD;
        $tr = $score1 - $prevTR;

        // Mettre à jour les touches du match
        $touches[$matchId]['TD'] += $td;
        $touches[$matchId]['TR'] += $tr;

        // Mettre à jour les scores précédents
        $prevTD = $score0;
        $prevTR = $score1;
    }

    return $touches;
}

function getMemberStats($xml, $teamId, $memberId) {
    $touchesGiven = 0;
    $touchesReceived = 0;

    foreach ($xml->Phases->TourDePoules as $tour) {
        foreach ($tour->Poule as $poule) {
            foreach ($poule->Match as $match) {
                if ((string)$match->Equipe[0]['REF'] === $teamId || (string)$match->Equipe[1]['REF'] === $teamId) {
                    $touches = calculateTouches($match, $teamId, $xml);
                    foreach ($match->Assaut as $assault) {
                        $currentAssautId = (string)$assault['ID'];
                        foreach($assault->Tireur as $tireur){
                            if ((string)$tireur['REF'] === $memberId){
                                $touchesGiven += $touches[$currentAssautId]['TD'];
                                $touchesReceived += $touches[$currentAssautId]['TR'];
                            }
                        }
                    }
                }
            }
        }
    }

    foreach ($xml->Phases->PhaseDeTableaux->SuiteDeTableaux as $suite) {
        foreach ($suite->Tableau as $tableau) {
            foreach ($tableau->Match as $match) {
                if ((string)$match->Equipe[0]['REF'] === $teamId || (string)$match->Equipe[1]['REF'] === $teamId) {
                    $touches = calculateTouches($match, $teamId, $xml);
                    foreach ($match->Assaut as $assault) {
                        $currentAssautId = (string)$assault['ID'];
                        foreach($assault->Tireur as $tireur){
                            if ((string)$tireur['REF'] === $memberId){
                                $touchesGiven += $touches[$currentAssautId]['TD'];
                                $touchesReceived += $touches[$currentAssautId]['TR'];
                            }
                        }
                    }
                }
            }
        }
    }

    $index = $touchesGiven - $touchesReceived;
    return [
        'TD' => $touchesGiven,
        'TR' => $touchesReceived,
        'IND' => $index
    ];
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

// Function to retrieve the team by ID
function getTeamById($xml, $id) {
    foreach ($xml->Equipes->Equipe as $equipe) {
        if ((string)$equipe['ID'] === $id) {
            return $equipe;
        }
    }
    return null;
}

// Function to retrieve the team's matches in poules
function getTeamPoulesMatches($xml, $id) {
    $matches = [];
    foreach ($xml->Phases->TourDePoules as $tour) {
        foreach ($tour->Poule as $poule) {
            foreach ($poule->Match as $match) {
                if ((string)$match->Equipe[0]['REF'] === $id || (string)$match->Equipe[1]['REF'] === $id) {
                    $match->addAttribute('PouleID', (string)$poule['ID']);
                    $match->addAttribute('TourID', (string)$tour['ID']);
                    $matches[] = $match;
                }
            }
        }
    }
    return $matches;
}

// Function to retrieve the team's matches in tableau
function getTeamTableauMatches($xml, $id) {
    $matches = [];
    foreach ($xml->Phases->PhaseDeTableaux->SuiteDeTableaux as $suite) {
        foreach ($suite->Tableau as $tableau) {
            foreach ($tableau->Match as $match) {
                if ((string)$match->Equipe[0]['REF'] === $id || (string)$match->Equipe[1]['REF'] === $id) {
                    $match->addAttribute('TableauID', (string)$tableau['ID']);
                    $match->addAttribute('SuiteID', (string)$suite['ID']);
                    $matches[] = $match;
                }
            }
        }
    }
    return $matches;
}

// Retrieve the team's information
$team = getTeamById($xml, $id);
if ($team === null) {
    die("Error: Team not found.");
}

// Retrieve the team's poules matches
$poulesMatches = getTeamPoulesMatches($xml, $id);

// Retrieve the team's tableau matches
$tableauMatches = getTeamTableauMatches($xml, $id);

// Statistics
$totalTouchesGiven = 0;
$totalTouchesGivenTab = 0;
$totalTouchesGivenPoule = 0;
$totalTouchesReceived = 0;
$totalTouchesReceivedTab = 0;
$totalTouchesReceivedPoule = 0;
$totalVictoriesPoule = 0;
$totalVictoriesTab = 0;
$totalVictories = 0;
$totalDefeats = 0;
$totalDefeatsPoule = 0;
$totalDefeatsTab = 0;

foreach ($poulesMatches as $match) {
    if ((string)$match->Equipe[0]['REF'] === $id) {
        $totalTouchesGivenPoule += (int)$match->Equipe[0]['Score'];
        $totalTouchesReceivedPoule += (int)$match->Equipe[1]['Score'];
        if ((string)$match->Equipe[0]['Statut'] === 'V') {
            $totalVictoriesPoule++;
        } else {
            $totalDefeatsPoule++;
        }
    } else {
        $totalTouchesGivenPoule += (int)$match->Equipe[1]['Score'];
        $totalTouchesReceivedPoule += (int)$match->Equipe[0]['Score'];
        if ((string)$match->Equipe[1]['Statut'] === 'V') {
            $totalVictoriesPoule++;
        } else {
            $totalDefeatsPoule++;
        }
    }
}

foreach ($tableauMatches as $match) {
    if ((string)$match->Equipe[0]['REF'] === $id) {
        $totalTouchesGivenTab += (int)$match->Equipe[0]['Score'];
        $totalTouchesReceivedTab += (int)$match->Equipe[1]['Score'];
        if ((string)$match->Equipe[0]['Statut'] === 'V') {
            $totalVictoriesTab++;
        } else {
            $totalDefeatsTab++;
        }
    } else {
        $totalTouchesGivenTab += (int)$match->Equipe[1]['Score'];
        $totalTouchesReceivedTab += (int)$match->Equipe[0]['Score'];
        if ((string)$match->Equipe[1]['Statut'] === 'V') {
            $totalVictoriesTab++;
        } else {
            $totalDefeatsTab++;
        }
    }
}
$totalDefeats = $totalDefeatsPoule + $totalDefeatsTab;
$totalVictories = $totalVictoriesPoule + $totalVictoriesTab;
$totalTouchesGiven = $totalTouchesGivenPoule + $totalTouchesGivenTab;
$totalTouchesReceived = $totalTouchesReceivedPoule + $totalTouchesReceivedTab;

// Retrieve ranking and status from TourDePoules
$ranking = '';
$status = '';
foreach ($xml->Phases->TourDePoules as $poule) {
    foreach ($poule->Equipe as $equipe) {
        if ((string)$equipe['REF'] === $id) {
            $ranking = (string)$equipe['RangFinal'];
            $status = (string)$equipe['Statut'];
            break 2;
        }
    }
}

// Define $hasPoules based on whether there are poules matches or not
$hasPoules = !empty($poulesMatches);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiche Équipe</title>
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

        /* Conteneur des informations de l'équipe */
        .team-info {
            width: 100%;
            max-width: 800px;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            padding: 20px;
            text-align: center;
        }

        .team-info h2 {
            font-size: 22px;
            margin-bottom: 10px;
        }

        .team-info p {
            font-size: 18px;
            color: #333;
        }

        /* Conteneur des matchs */
        .team-matches {
            width: 100%;
            max-width: 800px;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-top: 20px;
        }

        .team-matches h3 {
            font-size: 20px;
            margin-bottom: 15px;
            color: #333;
        }

        .team-matches .poules-matches,
        .team-matches .tableau-matches {
            margin-bottom: 20px;
        }

        .team-matches h4 {
            font-size: 18px;
            margin-bottom: 10px;
            color: #555;
        }

        .team-matches ul {
            list-style-type: none;
            padding: 0;
        }

        .team-matches li {
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

        .team-members {
            width: 100%;
            max-width: 800px;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-top: 20px;
            text-align: center;
        }

        .members-container {
            width: 100%;
        }

        .member {
            border-bottom: 1px solid #ddd;
            padding: 10px 0;
            text-align: left;
        }

        .member-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .member-header button {
            background: none;
            border: none;
            font-size: 16px;
            cursor: pointer;
            color: #004080;
        }

        .member-details {
            padding: 10px 0;
        }
        .member-header {
            cursor: pointer; /* Cursor changes to hand when hovering over the member-header */
        }


    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var matches = document.querySelectorAll('.team-matches li');
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

        document.addEventListener('DOMContentLoaded', function () {
            var toggles = document.querySelectorAll('.toggle-details');
            toggles.forEach(function (toggle) {
                toggle.addEventListener('click', function () {
                    var details = this.nextElementSibling;
                    if (details.style.display === 'none') {
                        details.style.display = 'block';
                        this.querySelector('button').textContent = '▲'; // Update the button text
                    } else {
                        details.style.display = 'none';
                        this.querySelector('button').textContent = '▼'; // Update the button text
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
        <section class="team-info">
            <h2><?php echo getValue($team, 'Nom'); ?></h2>
            <p>Classement final: <?php echo $ranking; ?></p>
        </section>
        <section class="team-members">
            <h3>Composition de l'équipe</h3>
            <div class="members-container">
                <?php foreach ($team->Tireur as $member): ?>
                    <?php 
                        $memberId = (string)$member['ID'];
                        $stats = getMemberStats($xml, $id, $memberId);
                    ?>
                    <div class="member">
                        <div class="member-header toggle-details"> <!-- Modified class here -->
                            <span><?php echo getValue($member, 'Nom') . ' ' . getValue($member, 'Prenom'); ?></span>
                            <button>▼</button>
                        </div>
                        <div class="member-details" style="display: none;">
                            <p>Touches données: <?php echo $stats['TD']; ?></p>
                            <p>Touches reçues: <?php echo $stats['TR']; ?></p>
                            <p>Indice: <?php echo $stats['IND']; ?></p>
                            <a href="tireur.php?id=<?php echo $memberId; ?>">Voir plus</a>
                        </div>
                    </div>


                <?php endforeach; ?>
            </div>
        </section>

        <section class="team-matches">
            <h3>Parcours dans la compétition</h3>
            <div class="poules-matches">
                <?php if (!empty($poulesMatches)): ?>
                    <h4>Résultats en poules</h4>
                    <ul>
                        <?php foreach ($poulesMatches as $match): ?>
                            <li data-match-id="<?php echo $match['ID']; ?>" data-poule-id="<?php echo $match['PouleID']; ?>" data-tour-id="<?php echo $match['TourID']; ?>">
                                <?php
                                $opponentId = (string)$match->Equipe[0]['REF'] == $id ? $match->Equipe[1]['REF'] : $match->Equipe[0]['REF'];
                                $opponent = $xml->xpath("//Equipe[@ID='{$opponentId}']")[0];
                                echo getValue($team, 'Nom') . ' ' . ((string)$match->Equipe[0]['REF'] === $id ? (int)$match->Equipe[0]['Score'] : (int)$match->Equipe[1]['Score']) . ' - ' . ((string)$match->Equipe[0]['REF'] !== $id ? (int)$match->Equipe[0]['Score'] : (int)$match->Equipe[1]['Score']) . ' ' . getValue($opponent, 'Nom');
                                ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <div class="tableau-matches">
                <h4>Résultats en tableau</h4>
                <ul>
                    <?php foreach ($tableauMatches as $match): ?>
                        <li data-match-id="<?php echo $match['ID']; ?>" data-tableau-id="<?php echo $match['TableauID']; ?>" data-suite-id="<?php echo $match['SuiteID']; ?>">
                            <?php
                            $opponentId = (string)$match->Equipe[0]['REF'] == $id ? $match->Equipe[1]['REF'] : $match->Equipe[0]['REF'];
                            $opponent = $xml->xpath("//Equipe[@ID='{$opponentId}']")[0];
                            $tableauTitle = getTableauTitleById($xml, (string)$match['TableauID']);
                            if ($opponentId==0) {
                                echo $tableauTitle . ' : Exempté';
                            } else {
                                echo $tableauTitle . ' : ' . getValue($team, 'Nom') . ' ' . ((string)$match->Equipe[0]['REF'] === $id ? (int)$match->Equipe[0]['Score'] : (int)$match->Equipe[1]['Score']) . ' - ' . ((string)$match->Equipe[0]['REF'] !== $id ? (int)$match->Equipe[0]['Score'] : (int)$match->Equipe[1]['Score']) . ' ' . getValue($opponent, 'Nom');
                            }
                            ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>
        <section class="team-matches">
            <h3>Statistiques</h3>
            <p>Total des touches données: <?php echo $totalTouchesGiven; ?></p>
            <p>Total des touches reçues: <?php echo $totalTouchesReceived; ?></p>
            <p>Indice sur la compétition: <?php echo $totalTouchesGiven - $totalTouchesReceived; ?></p>
            <p>Total des victoires: <?php echo $totalVictories; ?></p>
            <p>Total des défaites: <?php echo $totalDefeats; ?></p>
        </section>
    </main>
</body>
</html>
