<?php
require_once("config.php");



// Fonction pour obtenir une valeur avec vérification de l'existence
function getValue($xml, $field) {
    return isset($xml[$field]) ? htmlentities($xml[$field], ENT_QUOTES | ENT_XML1, 'UTF-8') : '';
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

// Fonction pour afficher les matchs individuels ou par équipe
function displayIndividualOrTeamMatches($xml, $tableau, $column, $baseMargin, $tableauTitle, $suiteId, $isTeam = false) {
    $tableauSize = count($tableau->Match);
    $colors = ['#69A7C4', '#F5C359', '#76D7B0', '#E96758']; // New colors
    $sectionSize = $tableauSize > 4 ? ceil($tableauSize / 4) : 1;
    $color = $tableauSize < 4 ? '#EE82EE' : null; // Violet if less than 4 matches

    echo "<div class='column column-$column'>";
    echo "<h3 class='tableau-title'>$tableauTitle</h3>";

    $matchIndex = 0;
    $currentMargin = $baseMargin;
    foreach ($tableau->Match as $match) {
        $matchId = $match['ID'];
        $exempte = false;
        if ($isTeam) {
            $team1 = $xml->xpath("//Equipe[@ID='{$match->Equipe[0]['REF']}']");
            $team2 = isset($match->Equipe[1]['REF']) ? $xml->xpath("//Equipe[@ID='{$match->Equipe[1]['REF']}']") : null;

            $team1Name = $team1 ? getValue($team1[0], 'Nom') : 'Exempté';
            $team2Name = $team2 ? getValue($team2[0], 'Nom') : 'Exempté';

            if ($team1Name === 'Exempté' || $team2Name === 'Exempté') {
                $exempte = true;
            }

            $team1Classement = $team1 ? getValue($match->Equipe[0], 'Place') : ($tableauSize * 2 - getValue($match->Equipe[1], 'Place') + 1);
            $team2Classement = $team2 ? getValue($match->Equipe[1], 'Place') : ($tableauSize * 2 - getValue($match->Equipe[0], 'Place') + 1);

            $team1Score = getValue($match->Equipe[0], 'Score');
            $team2Score = isset($match->Equipe[1]['Score']) ? getValue($match->Equipe[1], 'Score') : '';
        } else {
            $tireur1 = $xml->xpath("//Tireur[@ID='{$match->Tireur[0]['REF']}']");
            $tireur1 = $tireur1[0];
            $tir1 = $tireur1 ? getValue($tireur1, 'Nom') . ' ' . getValue($tireur1, 'Prenom') : 'Exempté';
            $classement1 = $tireur1 ? getValue($match->Tireur[0], 'Place') : ($tableauSize * 2 - getValue($match->Tireur[1], 'Place') + 1);
            $score1 = getValue($match->Tireur[0], 'Score');

            $tireur2 = isset($match->Tireur[1]['REF']) ? $xml->xpath("//Tireur[@ID='{$match->Tireur[1]['REF']}']") : null;
            $tir2 = $tireur2 ? getValue($tireur2[0], 'Nom') . ' ' . getValue($tireur2[0], 'Prenom') : 'Exempté';
            $classement2 = $tireur2 ? getValue($match->Tireur[1], 'Place') : ($tableauSize * 2 - getValue($match->Tireur[0], 'Place') + 1);
            $score2 = isset($match->Tireur[1]['Score']) ? getValue($match->Tireur[1], 'Score') : '';

            if ($tir1 === 'Exempté' || $tir2 === 'Exempté') {
                $exempte = true;
            }
        }

        // Ajouter un "V" au gagnant
        $score1=getValue($match->Tireur[0],'Statut') === 'V' ? "V" . $score1: $score1;
        $score2=getValue($match->Tireur[1],'Statut') === 'V' ? "V" . $score2: $score2;

        // Calcul de la marge supérieure
        if ($column == 1) {
            $marginTop = $matchIndex == 0 ? 0 : ($matchIndex % 2 == 0 ? 20 : 5);
        } else {
            $marginTop = $matchIndex == 0 ? $baseMargin / 2 : $baseMargin;
        }

        $backgroundColor = $color ? $color : $colors[floor($matchIndex / $sectionSize) % 4];
        if ($matchIndex % $sectionSize == 0) {
            echo "<a name='section-$column-" . floor($matchIndex / $sectionSize) . "'></a>";
        }

        echo "<a href='match.php?suite_id=$suiteId&tableau_id={$tableau['ID']}&match_id=$matchId' class='match-wrapper' style='margin-top: " . $marginTop . "px;'>";
        echo "<div class='match' style='background-color: " . $backgroundColor . ";'>";
        if ($isTeam) {
            echo "<div class='player player-1'>";
            echo "<span class='classement'>" . $team1Classement . "</span>";
            echo "<span class='nom'>" . htmlspecialchars($team1Name, ENT_QUOTES, 'UTF-8') . "</span>";
            echo "<span class='score'>" . $team1Score . "</span>";
            echo "</div>";
            echo "<div class='player player-2'>";
            echo "<span class='classement'>" . $team2Classement . "</span>";
            echo "<span class='nom'>" . htmlspecialchars($team2Name, ENT_QUOTES, 'UTF-8') . "</span>";
            echo "<span class='score'>" . $team2Score . "</span>";
            echo "</div>";
        } else {
            echo "<div class='player player-1'>";
            echo "<span class='classement'>" . $classement1 . "</span>";
            echo "<span class='nom'>" . htmlspecialchars($tir1, ENT_QUOTES, 'UTF-8') . "</span>";
            echo "<span class='score'>" . $score1 .'    ..'. "</span>";
            echo "</div>";
            echo "<div class='player player-2'>";
            echo "<span class='classement'>" . $classement2 . "</span>";
            echo "<span class='nom'>" . htmlspecialchars($tir2, ENT_QUOTES, 'UTF-8') . "</span>";
            echo "<span class='score'>" . $score2 .'    ..'. "</span>";
            echo "</div>";
        }
        echo "</div>";
        if (!$exempte) {
            $piste = isset($match['Piste']) ? 'Piste: ' . $match['Piste'] : '';
            $heure = isset($match['Heure']) ? 'Heure: ' . $match['Heure'] : '';
            $arbitre = isset($match->Arbitre['REF']) ? getValue($xml->xpath("//Arbitre[@ID='{$match->Arbitre['REF']}']")[0], 'Prenom') . ' ' . getValue($xml->xpath("//Arbitre[@ID='{$match->Arbitre['REF']}']")[0], 'Nom') : 'Non défini';

            echo "<div class='details'>";
            echo htmlspecialchars($heure, ENT_QUOTES, 'UTF-8') . " | " . htmlspecialchars($piste, ENT_QUOTES, 'UTF-8') . "<br>";
            echo "Arbitre: " . htmlspecialchars($arbitre, ENT_QUOTES, 'UTF-8');
            echo "</div>";
        }
        echo "</a>";

        $matchIndex++;
    }
    echo "</div>";
}

// Fonction pour afficher tous les tableaux
function displayAllTableaux($phases, $isTeam = false, $selectedSuiteId = null) {
    $column = 1;
    $baseMargin = 100; // Base margin for the second column
    foreach ($phases->PhaseDeTableaux as $phase) {
        foreach ($phase->SuiteDeTableaux as $suite) {
            if ($selectedSuiteId !== null && (string)$suite['ID'] !== $selectedSuiteId) {
                continue;
            }
            foreach ($suite->Tableau as $tableau) {
                displayIndividualOrTeamMatches($suite, $tableau, $column, $baseMargin, (string)$tableau['Titre'], (string)$suite['ID'], $isTeam);
                if ($column > 1) {
                    $baseMargin =$baseMargin*2 +122; // Doubling the margin for the next column
                }
                $column++;
            }
        }
    }
}

// Détection si c'est une compétition par équipe ou individuelle
$isTeam = isset($xml->Equipes);

// Récupérer la suite de tableau sélectionnée
$selectedSuiteId = isset($_GET['suiteid']) ? $_GET['suiteid'] : null;

// Redirection vers la première suite de tableau si aucune suite sélectionnée
if ($selectedSuiteId === null) {
    foreach ($xml->Phases->PhaseDeTableaux as $phase) {
        foreach ($phase->SuiteDeTableaux as $suite) {
            header('Location: ' . $_SERVER['PHP_SELF'] . '?suiteid=' . urlencode($suite['ID']));
            exit();
        }
    }
}

// Affichage des tableaux
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableaux</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            font-family: Arial, sans-serif;
        }
        .bracket-container {
            display: flex;
            flex-direction: row;
            align-items: flex-start;
            overflow-x: auto;
            width: 100%;
        }
        .column {
            display: flex;
            flex-direction: column;
            margin: 20px;
            align-items: flex-start; /* Aligner à gauche */
            position: relative;
        }
        .tableau-title {
            margin-bottom: 10px;
            text-align: center;
        }
        .match-wrapper {
            display: flex;
            flex-direction: column;
            margin: 10px 0;
            position: relative;
            text-decoration: none;
            color: inherit;
        }
        .match {
            display: flex;
            flex-direction: column;
            width: 300px; /* Largeur fixe augmentée */
            height: 80px; /* Hauteur fixe pour chaque match */
            border-radius: 0px;
            margin-bottom: 0px;
            overflow: hidden;
        }
        .player {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            padding: 10px;
            text-align: left;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: clip; /* Supprimer les points de suspension */
        }
        .nom {
            flex: 1 1 auto;
            margin-right: 10px; /* Espace entre le nom et le score */
            text-align: left;
            overflow: hidden;
            text-overflow: ellipsis; /* Si le texte est trop long, il sera coupé */
        }
        .score {
            flex: 0 0 auto;
            text-align: right;
        }
        .player-1 {
            border-bottom: 1px solid #fff; /* Séparation des joueurs */
        }
        .classement {
            flex: 0 0 20%;
            text-align: left;
        }
        .details {
            font-size: 12px;
            color: #555;
            margin-top: 5px;
        }
        .error {
            color: red;
            font-weight: bold;
        }
        .controls {
            display: flex;
            align-items: center;
            margin: 20px 0;
        }
        .section-nav {
            display: flex;
            align-items: center;
            margin-left: 20px;
        }
        .nav-square {
            display: block;
            width: 20px;
            height: 20px;
            margin: 0 5px;
            border-radius: 4px; /* Rounded corners */
        }
        .section-blue {
            background-color: #69A7C4; /* Updated color */
        }
        .section-yellow {
            background-color: #F5C359; /* Updated color */
        }
        .section-green {
            background-color: #76D7B0; /* Updated color */
        }
        .section-red {
            background-color: #E96758; /* Updated color */
        }
    </style>
    <script>
        function onSuiteChange() {
            document.getElementById('suiteForm').submit();
        }
    </script>
</head>
<body>
    <header>
        <nav class="menu-bar">
            <ul>
                <li><a href="index.php">Accueil</a></li>
                <li><a href="inscrits.php">Inscrits</a></li>
                <li><a href="poules.php">Poules</a></li>
                <li><a href="classement_p.php">Classement Intermédiaire</a></li>
                <li><a href="tableau.php#">Tableau</a></li>
                <li><a href="classement_f.php">Classement Final</a></li>
            </ul>
        </nav>
        <section class="subtitle">
            <h1><?php echo getValue($xml, 'TitreLong'); ?></h1>
            <h2>
                <?php
                echo '<a href="' . htmlspecialchars(getValue($xml, 'URLorganisateur'), ENT_QUOTES, 'UTF-8') . '">' . getValue($xml, 'Organisateur') . '</a>';
                ?>
            </h2>
            <h3><?php echo formatDate(getValue($xml, 'Date')); ?></h3>
        </section>
    </header>
    <main>
        <div class="controls">
            <form method="GET" action="" id="suiteForm">
                <label for="suiteid">Choisissez une suite de tableau :</label>
                <select name="suiteid" id="suiteid" onchange="onSuiteChange()">
                    <?php
                    foreach ($xml->Phases->PhaseDeTableaux as $phase) {
                        foreach ($phase->SuiteDeTableaux as $suite) {
                            $selected = ($selectedSuiteId === (string)$suite['ID']) ? 'selected' : '';
                            echo '<option value="' . htmlspecialchars($suite['ID'], ENT_QUOTES, 'UTF-8') . '"' . $selected . '>' . htmlspecialchars($suite['Titre'], ENT_QUOTES, 'UTF-8') . '</option>';
                        }
                    }
                    ?>
                </select>
            </form>
            <div class="section-nav">
                <a href="#section-1-0" class="nav-square section-blue"></a>
                <a href="#section-1-1" class="nav-square section-yellow"></a>
                <a href="#section-1-2" class="nav-square section-green"></a>
                <a href="#section-1-3" class="nav-square section-red"></a>
            </div>
        </div>
        <div class="bracket-container">
            <?php
            displayAllTableaux($xml->Phases, $isTeam, $selectedSuiteId);
            ?>
        </div>
    </main>
</body>
</html>
