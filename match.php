<?php
require_once("config.php");

// Charger le fichier XML
/*$xml_file = '1.xml'; // Path to the XML file
$xml = simplexml_load_file($xml_file) or die("Error: Cannot create object");
*/
// Fonction pour obtenir une valeur avec vérification de l'existence
function getValue($xml, $field) {
    return isset($xml[$field]) ? htmlentities($xml[$field], ENT_QUOTES | ENT_XML1, 'UTF-8') : '';
}

$hasPoules = false;
if (isset($xml->Phases->TourDePoules)) {
    $hasPoules = true;
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



// Récupérer les IDs de la suite, du tableau, de la poule et du match à partir de l'URL
$suite_id = isset($_GET['suite_id']) ? $_GET['suite_id'] : null;
$tableau_id = isset($_GET['tableau_id']) ? $_GET['tableau_id'] : null;
$tour_poule_id = isset($_GET['tour_id']) ? $_GET['tour_id'] : null;
$poule_id = isset($_GET['poule_id']) ? $_GET['poule_id'] : null;
$match_id = isset($_GET['match_id']) ? $_GET['match_id'] : die("Error: Match ID not specified");



// Fonction pour obtenir les détails d'un arbitre
function getRefereeDetails($xml, $refereeId) {
    $referee = $xml->xpath("//Arbitre[@ID='$refereeId']")[0];
    return $referee ? 'Arbitre: ' . $referee['Nom'] . ' ' . $referee['Prenom'] : '';
}

function getteam($team) {
    return $team ? $team : 'Exempté';
}

// Variables pour stocker les informations du match
$match = null;
$isTeamCompetition = false;
$isPouleMatch = false;

// Rechercher le match dans le tableau si l'ID de la suite et du tableau sont fournis
if ($suite_id && $tableau_id) {
    $phase = $xml->Phases->PhaseDeTableaux;
    $suite = null;
    $tableau = null;

    foreach ($phase->SuiteDeTableaux as $s) {
        if ((string)$s['ID'] === $suite_id) {
            $suite = $s;
            break;
        }
    }

    if ($suite) {
        foreach ($suite->Tableau as $t) {
            if ((string)$t['ID'] === $tableau_id) {
                $tableau = $t;
                break;
            }
        }

        if ($tableau) {
            foreach ($tableau->Match as $m) {
                if ((string)$m['ID'] === $match_id) {
                    $match = $m;
                    $isTeamCompetition = ($xml->getName() == 'CompetitionParEquipes');
                    break;
                }
            }
        }
    }
}

// Rechercher le match dans les poules si l'ID du tour de poule et de la poule est fourni
if ($tour_poule_id && $poule_id && !$match) {
    $tourDePoules = $xml->Phases;
    $tour_poule = null;
    $poule = null;

    foreach ($tourDePoules->TourDePoules as $tp) {
        if ((string)$tp['ID'] === $tour_poule_id) {
            $tour_poule = $tp;
            break;
        }
    }

    if ($tour_poule) {
        foreach ($tour_poule->Poule as $p) {
            if ((string)$p['ID'] === $poule_id) {
                $poule = $p;
                break;
            }
        }

        if ($poule) {
            foreach ($poule->Match as $m) {
                if ((string)$m['ID'] === $match_id) {
                    $match = $m;
                    $isTeamCompetition = isset($match->Equipe); // Vérifie si c'est un match d'équipe
                    $isPouleMatch = true;
                    break;
                }
            }
        }
    }
}

if (!$match) {
    die("Error: Match not found");
}

if ($isTeamCompetition) {
    $team1 = $match->Equipe[0]['REF'];
    $team1_rank = $match->Equipe[0]['Place'];
    $team2 = isset($match->Equipe[1]['REF']) ? $match->Equipe[1]['REF'] : null;
    $team2_rank = $match->Equipe[1]['Place'];

    $team1_details = $xml->xpath("//Equipe[@ID='$team1']")[0];
    $team2_details = $team2 ? $xml->xpath("//Equipe[@ID='$team2']")[0] : null;

    $team1_name = $team1_details ? $team1_rank .' '. getValue($team1_details, 'Nom') : 'Exempté';
    $team2_name = $team2_details ? $team2_rank.' '. getValue($team2_details, 'Nom') : 'Exempté';
} else {
    $tireur1 = $match->Tireur[0]['REF'];
    $tireur1_rank = $match->Tireur[0]['Place'];
    $tireur2 = isset($match->Tireur[1]['REF']) ? $match->Tireur[1]['REF'] : null;
    $tireur2_rank = $match->Tireur[1]['Place'];

    $tireur1_details = $xml->xpath("//Tireur[@ID='$tireur1']")[0];
    $tireur2_details = $tireur2 ? $xml->xpath("//Tireur[@ID='$tireur2']")[0] : null;

    $tireur1_name = $tireur1_details ? $tireur1_rank.' ' . getValue($tireur1_details, 'Nom').' ' . getValue($tireur1_details, 'Prenom') : 'Exempté';
    $tireur2_name = $tireur2_details ? $tireur2_rank .' ' . getValue($tireur2_details, 'Nom').' ' . getValue($tireur2_details, 'Prenom') : 'Exempté';
}

$arbitre = getRefereeDetails($xml, $match->Arbitre['REF']);
$arbitreid=getValue($match->Arbitre,'REF');
               
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails du Match</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .match-details {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            border: 1px solid #ddd;
            background-color: #f9f9f9;
        }
        .match-details h1 {
            text-align: center;
        }
        .team {
            display: flex;
            justify-content: space-between;
            padding: 10px;
            background-color: #f1f1f1;
            border: 1px solid #ddd;
            margin-bottom: 10px;
        }
        .team .team-name {
            flex: 1;
        }
        .team .team-score {
            text-align: center;
            width: 50px;
        }
        .match-info {
            text-align: center;
            margin-bottom: 20px;
        }
        .assaults {
            margin-top: 20px;
            text-align: center; /* Centrer les détails des assauts */
        }
        .assault {
            border: 1px solid #ddd;
            padding: 10px;
            margin-bottom: 10px;
            background-color: #f9f9f9;
            display: flex;
            justify-content: space-between; /* Justifier les noms des équipes et centrer les scores */
        }
        .assault p {
            margin: 0 10px;
            flex: 1;
            text-align: center;
        }
        .positive {
            color: green;
        }
        .negative {
            color: red;
        }
        .neutral {
            color: black;
        }
        .assault .team-name {
            flex: 1;
            text-align: left;
        }
        .assault .team-name-right {
            text-align: right;
        }
        .team-name a, .assault .team-name a {
            text-decoration: none;
            color: inherit;
        }
        .tireur-summary {
            max-width: 800px; /* Ajuster la largeur du bloc pour correspondre aux autres sections */
            margin-top: 20px;
            padding: 20px;
            background-color: #fff;
            border: 1px solid #ddd;
            text-align: center;
        }
        .tireur-summary h2 {
            text-align: center;
        }
        .tireur-summary .club {
            margin-bottom: 15px;
            text-align: justify; /* Justifier le texte */
        }
        .tireur-summary .tireur {
            margin-bottom: 5px;
            background-color: #fff;
            padding: 10px;
            border: 1px solid #ddd;
        }
        .tireur-summary .positive {
            color: green;
        }
        .tireur-summary .negative {
            color: red;
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
        <div class="match-details">
            <h1>Détails du Match</h1>
            <div class="match-info">
                <span class="match-date">Date: <?php echo getValue($match, 'Date') ? getValue($match, 'Date'): getValue($poule, 'Date') ; ?></span>
                <span class="match-time">Heure: <?php echo getValue($match, 'Heure') ? getValue($match, 'Heure') : getValue($poule, 'Heure'); ?></span>
                <span class="match-track">Piste: <?php echo getValue($match, 'Piste') ? getValue($match, 'Piste') : getValue($poule, 'Piste'); ?></span>
                <span class="match-referee">
                    <a href="ref.php?id=<?php echo $arbitreid; ?>"><?php echo $arbitre; ?></a>
                </span>

            </div>
            <?php 
            if ($isTeamCompetition) {
                $score_team1 = intval(getValue($match->Equipe[0], 'Score'));
                $score_team2 = intval(getValue($match->Equipe[1], 'Score'));
                $score1_class = $score_team1 > $score_team2 ? 'positive' : ($score_team1 < $score_team2 ? 'negative' : 'neutral');
                $score2_class = $score_team2 > $score_team1 ? 'positive' : ($score_team2 < $score_team1 ? 'negative' : 'neutral');
                ?>
                <div class="team">
                    <span class="team-name"><a href="equipe.php?id=<?php echo $team1; ?>"><?php echo $team1_name; ?></a></span>
                    <span class="team-score <?php echo $score1_class; ?>"><?php echo $score_team1; ?></span>
                    <span class="team-score">-</span>
                    <span class="team-score <?php echo $score2_class; ?>"><?php echo $score_team2; ?></span>
                    <span class="team-name"><a href="equipe.php?id=<?php echo $team2; ?>"><?php echo $team2_name; ?></a></span>
                </div>
                <div class="assaults">
                    <h2>Détails des Assauts</h2>
                    <?php if (count($match->Assaut) > 0): ?>
                        <?php 
                        $prevScore1 = 0;
                        $prevScore2 = 0;
                        $tireurScores = [];
                        foreach ($match->Assaut as $assault): 
                            $tireur1 = $xml->xpath("//Tireur[@ID='{$assault->Tireur[0]['REF']}']")[0];
                            $tireur2 = $xml->xpath("//Tireur[@ID='{$assault->Tireur[1]['REF']}']")[0];
                            $score1 = intval(getValue($assault->Tireur[0], 'Score'));
                            $score2 = intval(getValue($assault->Tireur[1], 'Score'));
                            $point1 = $score1 - $prevScore1;
                            $point2 = $score2 - $prevScore2;
                            $diff1 = $point1 - $point2;
                            $diff2 = $point2 - $point1;
                            $prevScore1 = $score1;
                            $prevScore2 = $score2;

                            // Ajouter les scores au tableau des tireurs
                            if (!isset($tireurScores[(string)$assault->Tireur[0]['REF']])) {
                                $tireurScores[(string)$assault->Tireur[0]['REF']] = ['score' => 0, 'club' => getValue($tireur1, 'Club')];
                            }
                            if (!isset($tireurScores[(string)$assault->Tireur[1]['REF']])) {
                                $tireurScores[(string)$assault->Tireur[1]['REF']] = ['score' => 0, 'club' => getValue($tireur2, 'Club')];
                            }
                            $tireurScores[(string)$assault->Tireur[0]['REF']]['Score'] += $diff1;
                            $tireurScores[(string)$assault->Tireur[1]['REF']]['Score'] += $diff2;
                        ?>
                            <div class="assault">
                                <span class="team-name"><a href="tireur.php?id=<?php echo $assault->Tireur[0]['REF']; ?>"><?php echo getValue($tireur1, 'Nom'); ?></a></span>
                                <p>
                                    <span class="<?php echo $diff1 > 0 ? 'positive' : ($diff1 < 0 ? 'negative' : 'neutral'); ?>">
                                        (<?php echo $diff1 > 0 ? '+' . $diff1 : ($diff1 < 0 ? $diff1 : '0'); ?>)
                                    </span>
                                    <?php echo $score1; ?> 
                                    - 
                                    <?php echo $score2; ?>
                                    <span class="<?php echo $diff2 > 0 ? 'positive' : ($diff2 < 0 ? 'negative' : 'neutral'); ?>">
                                        (<?php echo $diff2 > 0 ? '+' . $diff2 : ($diff2 < 0 ? $diff2 : '0'); ?>)
                                    </span>
                                </p>
                                <span class="team-name team-name-right"><a href="tireur.php?id=<?php echo $assault->Tireur[1]['REF']; ?>"><?php echo getValue($tireur2, 'Nom'); ?></a></span>
                            </div>
                        <?php endforeach; ?>
                        <!-- Affichage des indices des tireurs par club -->
                        <div class="tireur-summary">
                            <h2>Indices des Tireurs</h2>
                            <?php 
                            // Organiser les tireurs par club
                            $clubs = [];
                            foreach ($tireurScores as $tireurId => $data) {
                                $clubs[$data['Nom']][$tireurId] = $data['Score'];
                            }

                            // Affichage des tireurs par club
                            foreach ($clubs as $clubName => $tireurs) {
                                echo '<div class="club"><h3>' . htmlentities($clubName, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</h3>';
                                foreach ($tireurs as $tireurId => $indice) {
                                    $tireurDetails = $xml->xpath("//Tireur[@ID='$tireurId']")[0];
                                    $tireurName = getValue($tireurDetails, 'Nom') . ' ' . getValue($tireurDetails, 'Prenom');
                                    $indice_class = $indice > 0 ? 'positive' : ($indice < 0 ? 'negative' : 'neutral');
                                    echo '<div class="tireur ' . $indice_class . '"><a href="tireur.php?id=' . $tireurId . '" style="text-decoration: none;">' . htmlentities($tireurName, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</a>: ' . $indice . '</div>';
                                }
                                echo '</div>';
                            }
                            ?>
                        </div>
                    <?php else: ?>
                        <p>Pas de détail</p>
                    <?php endif; ?>
                </div>
            <?php } else {
                $score_tireur1 = intval(getValue($match->Tireur[0], 'Score'));
                $score_tireur2 = intval(getValue($match->Tireur[1], 'Score'));
                $score1_class = $score_tireur1 > $score_tireur2 ? 'positive' : ($score_tireur1 < $score_tireur2 ? 'negative' : 'neutral');
                $score2_class = $score_tireur2 > $score_tireur1 ? 'positive' : ($score_tireur2 < $score_tireur1 ? 'negative' : 'neutral');
                ?>
                <div class="team">
                    <span class="team-name"><a href="tireur.php?id=<?php echo $tireur1; ?>"><?php echo $tireur1_name; ?></a></span>
                    <span class="team-score <?php echo $score1_class; ?>"><?php echo $score_tireur1; ?></span>
                    <span class="team-score">-</span>
                    <span class="team-score <?php echo $score2_class; ?>"><?php echo $score_tireur2; ?></span>
                    <span class="team-name"><a href="tireur.php?id=<?php echo $tireur2; ?>"><?php echo $tireur2_name; ?></a></span>
                </div>
            <?php } ?>

            
        </div>
    </main>
</body>
</html>
