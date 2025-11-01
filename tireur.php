<?php



require_once("config.php");

// Get the fencer ID from the URL
$id = isset($_GET['id']) ? $_GET['id'] : null;

if ($id === null) {
    die("Error: No ID provided.");
}

// Charger le fichier XML
if (!isset($xml)) {
    die("Error: XML data not loaded.");
}

// Function to obtain a value with existence check
function getValue($xml, $field) {
    return isset($xml[$field]) ? htmlentities($xml[$field], ENT_QUOTES | ENT_XML1, 'UTF-8') : ''; 
}

function formatDate($date) {
    $months = [
        "01" => "Janvier", "02" => "Février", "03" => "Mars", "04" => "Avril",
        "05" => "Mai", "06" => "Juin", "07" => "Juillet", "08" => "Août",
        "09" => "Septembre", "10" => "Octobre", "11" => "Novembre", "12" => "Décembre"
    ];

    $dateParts = explode(".", $date);
    if (count($dateParts) !== 3) return ''; // Vérifie que la date a bien 3 parties.
    $day = ltrim($dateParts[0], '0'); // Remove leading zeros
    $month = $months[$dateParts[1]] ?? ''; // Vérifie que le mois existe dans le tableau.
    $year = $dateParts[2];

    return "$day $month $year";
}

$hasPoules = false;
if (isset($xml->Phases->TourDePoules)) {
    $hasPoules = true;
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

function getFencerById($xml, $id, $isTeamCompetition) {
    if($isTeamCompetition){
        foreach($xml->Equipes->Equipe as $equipe){
            foreach($equipe->Tireur as $tireur){
                if ((string)$tireur['ID'] === $id) {
                    return $tireur;
                }
            }
        }
    } else {
        foreach ($xml->Tireurs->Tireur as $tireur) {
            if ((string)$tireur['ID'] === $id) {
                return $tireur;
            }
        }
    }
    return null; // Retourne null si le tireur n'est pas trouvé.
}

function getTeamByFencerId($xml, $id) {
    foreach ($xml->Equipes->Equipe as $equipe) {
        foreach ($equipe->Tireur as $tireur) {
            if ((string)$tireur['ID'] === $id) {
                return $equipe;
            }
        }
    }
    return null; // Retourne null si l'équipe n'est pas trouvée.
}

function getTeamIdByFencerId($xml, $id) {
    foreach ($xml->Equipes->Equipe as $equipe) {
        foreach ($equipe->Tireur as $tireur) {
            if ((string)$tireur['ID'] === $id) {
                return (string)$equipe['ID'];
            }
        }
    }
    return null; // Retourne null si l'ID de l'équipe n'est pas trouvé.
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

        foreach ($assault->Tireur as $tireur) {
            $ttireur = getTeamID($tireur['REF'], $xml);
            if ($ttireur === $teamId) {
                $tireur0 = $tireur;
            } else {
                $tireur1 = $tireur;
            }
        }

        if (!$tireur0 || !$tireur1) {
            continue; // Sauter l'assaut si les tireurs ne sont pas trouvés
        }

        $score0 = (int)$tireur0['Score'];
        $score1 = (int)$tireur1['Score'];

        if (!isset($touches[$matchId])) {
            $touches[$matchId] = ['TD' => 0, 'TR' => 0];
        }

        $td = $score0 - $prevTD;
        $tr = $score1 - $prevTR;

        $touches[$matchId]['TD'] += $td;
        $touches[$matchId]['TR'] += $tr;

        $prevTD = $score0;
        $prevTR = $score1;
    }

    return $touches;
}

function getFencerPoulesMatches($xml, $teamId, $id, $isTeamCompetition) {
    $matches = [];
    foreach ($xml->Phases->TourDePoules as $tour) {
        foreach($tour->Poule as $poule){
            foreach ($poule->Match as $match) {
                if ($isTeamCompetition) {
                    if ((string)$match->Equipe[0]['REF'] === $teamId || (string)$match->Equipe[1]['REF'] === $teamId) {
                        foreach ($match->Assaut as $assaut) {
                            if ((string)$assaut->Tireur[0]['REF'] === $id || (string)$assaut->Tireur[1]['REF'] === $id) {
                                $assautid = getValue($assaut,'ID');
                                $points = calculateTouches($match, $teamId, $xml);
                                $assaut->addAttribute('TD', (string)$points[$assautid]['TD'] ?? 0); // Ajout d'une vérification pour éviter une erreur si $points[$assautid] est null
                                $assaut->addAttribute('TR', (string)$points[$assautid]['TR'] ?? 0);
                                $assaut->addAttribute('PouleID', (string)$poule['ID']);
                                $assaut->addAttribute('TourID', (string)$tour['ID']);
                                $assaut->addAttribute('MatchID', (string)$match['ID']);
                                $matches[] = $assaut;
                            }
                        }
                    }
                } else {
                    if ((string)$match->Tireur[0]['REF'] === $id || (string)$match->Tireur[1]['REF'] === $id) {
                        $match->addAttribute('PouleID', (string)$poule['ID']);
                        $match->addAttribute('TourID', (string)$tour['ID']);
                        $matches[] = $match;
                    }
                }
            }
        }
    }
    return $matches;
}

function getFencerTableauMatches($xml, $teamId, $id, $isTeamCompetition) {
    $matches = [];
    foreach ($xml->Phases->PhaseDeTableaux->SuiteDeTableaux as $suite) {
        foreach($suite->Tableau as $tableau)
        foreach ($tableau->Match as $match) {
            if ($isTeamCompetition) {
                if ((string)$match->Equipe[0]['REF'] === $teamId || (string)$match->Equipe[1]['REF'] === $teamId) {
                    foreach ($match->Assaut as $assaut) {
                        if ((string)$assaut->Tireur[0]['REF'] === $id || (string)$assaut->Tireur[1]['REF'] === $id) {
                            $assautid = getValue($assaut,'ID');
                            $points = calculateTouches($match, $teamId, $xml);
                            $assaut->addAttribute('TD', (string)$points[$assautid]['TD'] ?? 0);
                            $assaut->addAttribute('TR', (string)$points[$assautid]['TR'] ?? 0);
                            $assaut->addAttribute('TableauID', (string)$tableau['ID']);
                            $assaut->addAttribute('SuiteID', (string)$suite['ID']);
                            $assaut->addAttribute('MatchID', (string)$match['ID']);
                            $matches[] = $assaut;
                        }
                    }
                }
            } else {
                if ((string)$match->Tireur[0]['REF'] === $id || (string)$match->Tireur[1]['REF'] === $id) {
                    $match->addAttribute('TableauID', (string)$tableau['ID']);
                    $match->addAttribute('SuiteID', (string)$suite['ID']);
                    $matches[] = $match;
                }
            }
        }
    }
    return $matches;
}

$isTeamCompetition = isset($xml->Equipes);

$fencer = getFencerById($xml, $id, $isTeamCompetition);
$team = null;
$teamId = '';
if ($isTeamCompetition) {
    $team = getTeamByFencerId($xml, $id);
    $teamId = getTeamIdByFencerId($xml, $id);
    if ($team === null) {
        die("Error: Team not found.");
    }
} else {
    if ($fencer === null) {
        die("Error: Fencer not found.");
    }
}

$poulesMatches = getFencerPoulesMatches($xml, $teamId, $id, $isTeamCompetition);
$tableauMatches = getFencerTableauMatches($xml, $teamId, $id, $isTeamCompetition);

$ranking = '';
$status = '';
foreach ($xml->Phases->TourDePoules as $poule) {
    foreach ($poule->Tireur as $equipe) {
        if ((string)$equipe['REF'] === $id) {
            $ranking = (string)$equipe['RangFinal'];
            $status = (string)$equipe['Statut'];
            break 2;
        }
    }
}

function getMemberStats($xml, $teamId, $memberId) {
    $touchesGiven = 0;
    $touchesReceived = 0;
    $vic = 0;
    $def = 0;

    foreach ($xml->Phases->TourDePoules as $tour) {
        foreach ($tour->Poule as $poule) {
            foreach ($poule->Match as $match) {
                if ((string)$match->Equipe[0]['REF'] === $teamId || (string)$match->Equipe[1]['REF'] === $teamId) {
                    $touches = calculateTouches($match, $teamId, $xml);
                    foreach ($match->Assaut as $assault) {
                        $currentAssautId = (string)$assault['ID'];
                        foreach($assault->Tireur as $tireur){
                            if ((string)$tireur['REF'] === $memberId){
                                $touchesGiven += $touches[$currentAssautId]['TD'] ?? 0;
                                $touchesReceived += $touches[$currentAssautId]['TR'] ?? 0;
                                if($touches[$currentAssautId]['TD'] > $touches[$currentAssautId]['TR']){
                                    $vic++;
                                } else {
                                    $def++;
                                }
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
                                $touchesGiven += $touches[$currentAssautId]['TD'] ?? 0;
                                $touchesReceived += $touches[$currentAssautId]['TR'] ?? 0;
                                if($touches[$currentAssautId]['TD'] > $touches[$currentAssautId]['TR']){
                                    $vic++;
                                } else {
                                    $def++;
                                }
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
        'IND' => $index,
        'VIC' => $vic,
        'DEF' => $def
    ];
}

$totalTouchesGiven = 0;
$totalTouchesGivenTab = 0;
$totalTouchesGivenPoule = 0;
$totalTouchesReceived = 0;
$totalTouchesReceivedPoule = 0;
$totalTouchesReceivedTab = 0;
$totalVictories = 0;
$totalVictoriesPoules = 0;
$totalDefeats = 0;
$totalDefeatsPoules = 0;

foreach ($poulesMatches as $match) {
    if ($isTeamCompetition) {
        if ((string)$match->Tireur[0]['REF'] === $id) {
            $totalTouchesGivenPoule += (int)$match->Tireur[0]['Score'];
            $totalTouchesReceivedPoule += (int)$match->Tireur[1]['Score'];
            if ((string)$match->Tireur[0]['Statut'] === 'V') {
                $totalVictories++;
                $totalVictoriesPoules++;
            } else {
                $totalDefeats++;
                $totalDefeatsPoules++;
            }
        } else {
            $totalTouchesGivenPoule += (int)$match->Tireur[1]['Score'];
            $totalTouchesReceivedPoule += (int)$match->Tireur[0]['Score'];
            if ((string)$match->Tireur[1]['Statut'] === 'V') {
                $totalVictories++;
                $totalVictoriesPoules++;
            } else {
                $totalDefeats++;
                $totalDefeatsPoules++;
            }
        }
    } else {
        if ((string)$match->Tireur[0]['REF'] === $id) {
            $totalTouchesGivenPoule += (int)$match->Tireur[0]['Score'];
            $totalTouchesReceivedPoule += (int)$match->Tireur[1]['Score'];
            if ((string)$match->Tireur[0]['Statut'] === 'V') {
                $totalVictories++;
                $totalVictoriesPoules++;
            } else {
                $totalDefeats++;
                $totalDefeatsPoules++;
            }
        } else {
            $totalTouchesGivenPoule += (int)$match->Tireur[1]['Score'];
            $totalTouchesReceivedPoule += (int)$match->Tireur[0]['Score'];
            if ((string)$match->Tireur[1]['Statut'] === 'V') {
                $totalVictories++;
                $totalVictoriesPoules++;
            } else {
                $totalDefeats++;
                $totalDefeatsPoules++;
            }
        }
    }
}

foreach ($tableauMatches as $match) {
    if ($isTeamCompetition) {
        if ((string)$match->Tireur[0]['REF'] === $id) {
            $totalTouchesGivenTab += (int)$match->Tireur[0]['Score'];
            $totalTouchesReceivedTab += (int)$match->Tireur[1]['Score'];
            if ((string)$match->Tireur[0]['Statut'] === 'V') {
                $totalVictories++;
            } else {
                $totalDefeats++;
            }
        } else {
            $totalTouchesGivenTab += (int)$match->Tireur[1]['Score'];
            $totalTouchesReceivedTab += (int)$match->Tireur[0]['Score'];
            if ((string)$match->Tireur[1]['Statut'] === 'V') {
                $totalVictories++;
            } else {
                $totalDefeats++;
            }
        }
    } else {
        if ((string)$match->Tireur[0]['REF'] === $id) {
            $totalTouchesGivenTab += (int)$match->Tireur[0]['Score'];
            $totalTouchesReceivedTab += (int)$match->Tireur[1]['Score'];
            if ((string)$match->Tireur[0]['Statut'] === 'V') {
                $totalVictories++;
            } else {
                $totalDefeats++;
            }
        } else {
            $totalTouchesGivenTab += (int)$match->Tireur[1]['Score'];
            $totalTouchesReceivedTab += (int)$match->Tireur[0]['Score'];
            if ((string)$match->Tireur[1]['Statut'] === 'V') {
                $totalVictories++;
            } else {
                $totalDefeats++;
            }
        }
    }
}
$totalTouchesGiven = $totalTouchesGivenPoule + $totalTouchesGivenTab;
$totalTouchesReceived = $totalTouchesReceivedPoule + $totalTouchesReceivedTab;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiche <?php echo $isTeamCompetition ? 'Équipe' : 'Tireur'; ?></title>
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

        /* Conteneur des informations du tireur */
        .fencer-info {
            width: 100%;
            max-width: 800px;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            padding: 20px;
            text-align: center;
        }

        .fencer-info h2 {
            font-size: 22px;
            margin-bottom: 10px;
        }

        .fencer-info p {
            font-size: 18px;
            color: #333;
        }

        /* Conteneur des matchs */
        .fencer-matches {
            width: 100%;
            max-width: 800px;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-top: 20px;
        }

        .fencer-matches h3 {
            font-size: 20px;
            margin-bottom: 15px;
            color: #333;
        }

        .fencer-matches .poules-matches,
        .fencer-matches .tableau-matches {
            margin-bottom: 20px;
        }

        .fencer-matches h4 {
            font-size: 18px;
            margin-bottom: 10px;
            color: #555;
        }

        .fencer-matches ul {
            list-style-type: none;
            padding: 0;
        }

        .fencer-matches li {
            padding: 10px 0;
            border-bottom: 1px solid #ddd;
            font-size: 16px;
            color: #333;
            cursor: pointer;
        }

        .fencer-matches li:hover {
            background-color: #f0f0f0;
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
    </style>
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
            <h1><?php echo htmlentities($xml['TitreLong']); ?></h1>
            <h2>
                <?php
                echo '<a href="' . htmlspecialchars(htmlentities($xml['URLorganisateur']), ENT_QUOTES, 'UTF-8') . '">' . htmlentities($xml['Organisateur']) . '</a>';
                ?>
            </h2>
            <h3><?php echo formatDate(htmlentities($xml['Date'])); ?></h3>
        </section>
    </header>
    <main>
        <?php if ($isTeamCompetition): ?>
            <section class="fencer-info">
                <h2><?php echo htmlentities($fencer['Nom']) . ' ' . htmlentities($fencer['Prenom']); ?></h2>
                <p>Équipe: <?php echo htmlentities($team['Nom']); ?></p>
                <p>Classement final de l'équipe: <?php echo htmlentities($team['Classement']); ?></p>
            </section>
            <section class="fencer-matches">
                <h3>Parcours dans la compétition</h3>
                <div class="poules-matches">
                    <h4>Relais en poules</h4>
                    <ul>
                        <?php foreach ($poulesMatches as $match): ?>
                            <li onclick="window.location.href='match.php?tour_id=<?php echo $match['TourID']; ?>&poule_id=<?php echo $match['PouleID']; ?>&match_id=<?php echo $match['MatchID']; ?>'">
                                <?php
                                $opponentId = (string)$match->Tireur[0]['REF'] == $id ? $match->Tireur[1]['REF'] : $match->Tireur[0]['REF'];
                                $opponent = $xml->xpath("//Tireur[@ID='{$opponentId}']")[0];
                                echo htmlentities($fencer['Nom']) . ' ' . getValue($fencer, 'Prenom') . ' ' .  (int)$match['TD']  . ' - ' . (int)$match['TR'] . ' ' . getValue($opponent, 'Nom'). ' ' . getValue($opponent, 'Prenom') ;
                                ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    
                </div>
                <div class="tableau-matches">
                    <h4>Relais en tableau</h4>
                    <ul>
                        <?php foreach ($tableauMatches as $match): ?>
                            <li onclick="window.location.href='match.php?suite_id=<?php echo $match['SuiteID']; ?>&tableau_id=<?php echo $match['TableauID']; ?>&match_id=<?php echo $match['MatchID']; ?>'">
                                <?php
                                
                                $opponentId = (string)$match->Tireur[0]['REF'] == $id ? $match->Tireur[1]['REF'] : $match->Tireur[0]['REF'];
                                $opponent = $xml->xpath("//Tireur[@ID='{$opponentId}']")[0];
                                $tableauTitle = getTableauTitleById($xml, (string)$match['TableauID']);
                                if ($opponentId == 0) {
                                    echo $tableauTitle . ' : Exempté';
                                } else {
                                    echo $tableauTitle . ' : ' . getValue($fencer, 'Nom') . ' ' . getValue($fencer, 'Prenom') . ' ' .  (int)$match['TD']  . ' - ' . (int)$match['TR'] . ' ' . getValue($opponent, 'Nom'). ' ' . getValue($opponent, 'Prenom') ;
                                }
                                ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </section>
            <section class="fencer-matches">
            <?php $stats = getMemberStats($xml, getTeamID($id, $xml), $id); ?>
                <h3>Statistiques</h3>
                <p>Total des touches données: <?php echo $stats['TD']; ?></p>
                <p>Total des touches reçues: <?php echo $stats['TR']; ?></p>
                <p>Indice: <?php echo $stats['IND']; ?></p>
                <p>Total des victoires: <?php echo $stats['VIC']; ?></p>
                <p>Total des défaites: <?php echo $stats['DEF']; ?></p>
            </section>
        <?php else: ?>
            <section class="fencer-info">
                <h2><?php echo htmlentities($fencer['Nom']) . ' ' . htmlentities($fencer['Prenom']); ?></h2>
                <p>Classement final: <?php echo htmlentities($fencer['Classement']); ?></p>
            </section>
            <section class="fencer-matches">
                <h3>Parcours dans la compétition</h3>
                <div class="poules-matches">
                    <h4>Résultats en poules</h4>
                    <ul>
                        <?php foreach ($poulesMatches as $match): ?>
                            <li onclick="window.location.href='match.php?tour_id=<?php echo $match['TourID']; ?>&poule_id=<?php echo $match['PouleID']; ?>&match_id=<?php echo $match['ID']; ?>'">
                                <?php
                                $opponentId = (string)$match->Tireur[0]['REF'] == $id ? $match->Tireur[1]['REF'] : $match->Tireur[0]['REF'];
                                $opponent = $xml->xpath("//Tireur[@ID='{$opponentId}']")[0];
                                echo htmlentities($fencer['Nom']) . ' ' . htmlentities($fencer['Prenom']) . ' ' .((string)$match->Tireur[0]['REF'] === $id ? (int)$match->Tireur[0]['Score'] : (int)$match->Tireur[1]['Score']) . ' - ' .  ((string)$match->Tireur[0]['REF'] !== $id ? (int)$match->Tireur[0]['Score'] : (int)$match->Tireur[1]['Score']). ' ' .htmlentities($opponent['Nom']). ' ' .htmlentities($opponent['Prenom']) ;
                                ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if (!empty($poulesMatches)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>RG</th>
                                    <th>Nom</th>
                                    <th>Prénom</th>
                                    <th>Club</th>
                                    <th>Vic/match</th>
                                    <th>Ind</th>
                                    <th>TD</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr onclick="window.location.href='classement_p.php';">
                                    <td><?php echo $ranking; ?></td>
                                    <td><?php echo getValue($fencer, 'Nom'); ?></td>
                                    <td><?php echo getValue($fencer, 'Prenom'); ?></td>
                                    <td><?php echo getValue($fencer, 'Club'); ?></td>
                                    <td><?php echo $totalVictoriesPoules / (count($poulesMatches) ?: 1); ?></td>
                                    <td><?php echo $totalTouchesGivenPoule - $totalTouchesReceivedPoule; ?></td>
                                    <td><?php echo $totalTouchesGivenPoule; ?></td>
                                    <td><?php echo $status; ?></td>
                                </tr>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                <div class="tableau-matches">
                    <h4>Résultats en tableau</h4>
                    <ul>
                        <?php foreach ($tableauMatches as $match): ?>
                            <li onclick="window.location.href='match.php?suite_id=<?php echo $match['SuiteID']; ?>&tableau_id=<?php echo $match['TableauID']; ?>&match_id=<?php echo $match['ID']; ?>'">
                                <?php
                                $opponentId = (string)$match->Tireur[0]['REF'] == $id ? $match->Tireur[1]['REF'] : $match->Tireur[0]['REF'];
                                $opponent = $xml->xpath("//Tireur[@ID='{$opponentId}']")[0];
                                $tableauTitle = getTableauTitleById($xml, (string)$match['TableauID']);
                                if ($opponentId == 0) {
                                    echo $tableauTitle . ' : Exempté';
                                } else {
                                    echo $tableauTitle . ' : ' . getValue($fencer, 'Nom') . ' ' . ((string)$match->Tireur[0]['REF'] === $id ? (int)$match->Tireur[0]['Score'] : (int)$match->Tireur[1]['Score']) . ' - ' . ((string)$match->Tireur[0]['REF'] !== $id ? (int)$match->Tireur[0]['Score'] : (int)$match->Tireur[1]['Score']) . ' ' . getValue($opponent, 'Nom');
                                }
                                ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </section>
            <section class="fencer-matches">
                <h3>Statistiques</h3>
                <p>Total des touches données: <?php echo $totalTouchesGiven; ?></p>
                <p>Total des touches reçues: <?php echo $totalTouchesReceived; ?></p>
                <p>Indice: <?php echo $totalTouchesGiven-$totalTouchesReceived; ?></p>
                <p>Total des victoires: <?php echo $totalVictories; ?></p>
                <p>Total des défaites: <?php echo $totalDefeats; ?></p>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>
