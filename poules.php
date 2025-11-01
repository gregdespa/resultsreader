<?php
// Charger le fichier XML
/*$xml = simplexml_load_file('lille.xml') or die("Error: Cannot create object");
*/
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



// Fonction pour afficher les arbitres d'une poule
function displayArbitres($poule, $xml) {
    $arbitres = [];
    foreach ($poule->Arbitre as $arbitre) {
        $arbitreDetails = $xml->xpath("//Arbitre[@ID='{$arbitre['REF']}']")[0];
        $arbitres[] = getValue($arbitreDetails, 'Nom') . ' ' . getValue($arbitreDetails, 'Prenom');
    }
    return implode(' et ', $arbitres);
}

// Fonction pour calculer le classement dans la poule
function calculateRanking($poule, $isTeam) {
    $ranking = [];
    if ($isTeam) {
        foreach ($poule->Equipe as $equipe) {
            $id = (string)$equipe['REF'];
            $stats = [
                'NbVictoires' => (int)$equipe['NbVictoires'],
                'TD' => (int)$equipe['TD'],
                'TR' => (int)$equipe['TR'],
                'Indice' => (int)$equipe['TD'] - (int)$equipe['TR']
            ];
            $ranking[$id] = $stats;
        }
    } else {
        foreach ($poule->Tireur as $tireur) {
            $id = (string)$tireur['REF'];
            $stats = [
                'NbVictoires' => (int)$tireur['NbVictoires'],
                'TD' => (int)$tireur['TD'],
                'TR' => (int)$tireur['TR'],
                'Indice' => (int)$tireur['TD'] - (int)$tireur['TR']
            ];
            $ranking[$id] = $stats;
        }
    }

    uasort($ranking, function ($a, $b) {
        if ($a['NbVictoires'] === $b['NbVictoires']) {
            return $b['Indice'] - $a['Indice'];
        }
        return $b['NbVictoires'] - $a['NbVictoires'];
    });

    $ranked = [];
    $rank = 1;
    foreach ($ranking as $id => $stats) {
        $ranked[$id] = $rank++;
    }
    return $ranked;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Poules</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .black-cell {
            background-color: black;
            width: 30px;
            height: 30px;
            display: inline-block;
        }
        .search-container {
            margin-bottom: 20px;
        }
        .search-container input {
            padding: 10px;
            width: 100%;
            box-sizing: border-box;
        }
        tr.clickable-row {
            cursor: pointer;
        }
    </style>
    <script>
        function filterPools() {
            var input, filter, sections, tables, trs, i, j, txtValue;
            input = document.getElementById('searchInput');
            filter = input.value.toUpperCase();
            sections = document.querySelectorAll('.poule');

            for (i = 0; i < sections.length; i++) {
                sections[i].style.display = "none"; // Hide all sections initially
                tables = sections[i].getElementsByTagName('table');
                for (j = 0; j < tables.length; j++) {
                    trs = tables[j].getElementsByTagName('tr');
                    for (var k = 1; k < trs.length; k++) { // Start from 1 to skip the header row
                        txtValue = trs[k].textContent || trs[k].innerText;
                        if (txtValue.toUpperCase().indexOf(filter) > -1) {
                            sections[i].style.display = "";
                            break;
                        }
                    }
                }
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            var rows = document.querySelectorAll('tr.clickable-row');
            rows.forEach(function(row) {
                row.addEventListener('click', function() {
                    var id = this.dataset.id;
                    var isTeam = this.dataset.isTeam === 'true';
                    var url = isTeam ? 'equipe.php?id=' + id : 'tireur.php?id=' + id;
                    window.location.href = url;
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
                <li><a href="poules.php">Poules</a></li>
                <li><a href="classement_p.php">Classement Intermédiaire</a></li>
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
        <div class="search-container">
            <?php 
            $ekip = $xml->Phases->TourDePoules->Poule;
            $isEkip = isset($ekip->Equipe);
            $recherche = $isEkip ? "Rechercher une équipe ou un club" : "Rechercher un tireur ou un club";
            ?>

            <input type="text" id="searchInput" onkeyup="filterPools()" placeholder="<?php echo $recherche ?>">
        </div>
        <div class="poules-table-container">
            <?php
            $phases = $xml->Phases->TourDePoules;

            foreach ($phases->Poule as $poule) {
                echo '<section class="poule">';
                echo '<h2>POULE ' . $poule['ID'] . '</h2>';
                echo '<h3>Piste ' . $poule['Piste'] . ' à ' . $poule['Heure'] . '</h3>';
                $isref = isset($poule->Arbitre);
                if ($isref) {
                    echo '<h4>Arbitre(s) : ' . displayArbitres($poule, $xml) . '</h4>';
                }
                echo '<table>';
                echo '<thead><tr><th></th><th>Nom</th><th>Club</th>';

                $isTeam = isset($poule->Equipe);
                $numCompetitors = $isTeam ? count($poule->Equipe) : count($poule->Tireur);
                for ($i = 1; $i <= $numCompetitors; $i++) {
                    echo "<th>$i</th>";
                }
                echo '<th>V / M</th><th>TD</th><th>Ind</th><th>Classement</th></tr></thead>';
                echo '<tbody>';

                $ranking = calculateRanking($poule, $isTeam);

                if ($isTeam) {
                    foreach ($poule->Equipe as $equipe) {
                        $equipeID = (string)$equipe['REF'];
                        $equipeDetails = $xml->xpath("//Equipe[@ID='{$equipeID}']")[0];
                        echo '<tr class="clickable-row" data-id="' . $equipeID . '" data-is-team="true">';
                        echo '<td>' . getValue($equipe, 'NoDansLaPoule') . '</td>';
                        echo '<td>' . getValue($equipeDetails, 'Nom') . '</td>';
                        echo '<td>' . getValue($equipeDetails, 'Club') . '</td>';

                        for ($i = 1; $i <= $numCompetitors; $i++) {
                            $cellContent = '';
                            if ($i == getValue($equipe, 'NoDansLaPoule')) {
                                $cellContent = '<div class="black-cell"></div>';
                            } else {
                                foreach ($poule->Match as $match) {
                                    if ($match->Equipe[0]['REF'] == $equipeID && getValue($xml->xpath("//Poule/Equipe[@REF='{$match->Equipe[1]['REF']}']")[0], 'NoDansLaPoule') == $i) {
                                        $cellContent = $match->Equipe[0]['Statut'] == 'V' ? 'V' . $match->Equipe[0]['Score'] : '' . $match->Equipe[0]['Score'];
                                        break;
                                    } elseif ($match->Equipe[1]['REF'] == $equipeID && getValue($xml->xpath("//Poule/Equipe[@REF='{$match->Equipe[0]['REF']}']")[0], 'NoDansLaPoule') == $i) {
                                        $cellContent = $match->Equipe[1]['Statut'] == 'V' ? 'V' . $match->Equipe[1]['Score'] : '' . $match->Equipe[1]['Score'];
                                        break;
                                    }
                                }
                            }
                            echo '<td>' . $cellContent . '</td>';
                        }
                        echo '<td>' . round(getValue($equipe, 'NbVictoires') / getValue($equipe, 'NbMatches'), 3) . '</td>';
                        echo '<td>' . getValue($equipe, 'TD') . '</td>';
                        echo '<td>' . (getValue($equipe, 'TD') - getValue($equipe, 'TR')) . '</td>';
                        echo '<td>' . $ranking[$equipeID] . '</td>';
                        echo '</tr>';
                    }
                } else {
                    foreach ($poule->Tireur as $tireur) {
                        $tireurID = (string)$tireur['REF'];
                        $tireurDetails = $xml->xpath("//Tireur[@ID='{$tireurID}']")[0];
                        echo '<tr class="clickable-row" data-id="' . $tireurID . '" data-is-team="false">';
                        echo '<td>' . getValue($tireur, 'NoDansLaPoule') . '</td>';
                        echo '<td>' . getValue($tireurDetails, 'Nom') . ' ' . getValue($tireurDetails, 'Prenom') . '</td>';
                        echo '<td>' . getValue($tireurDetails, 'Club') . '</td>';

                        for ($i = 1; $i <= $numCompetitors; $i++) {
                            $cellContent = '';
                            if ($i == getValue($tireur, 'NoDansLaPoule')) {
                                $cellContent = '<div class="black-cell"></div>';
                            } else {
                                foreach ($poule->Match as $match) {
                                    if ($match->Tireur[0]['REF'] == $tireurID && getValue($xml->xpath("//Poule/Tireur[@REF='{$match->Tireur[1]['REF']}']")[0], 'NoDansLaPoule') == $i) {
                                        $cellContent = $match->Tireur[0]['Statut'] == 'V' ? 'V' . $match->Equipe[0]['Score'] : '' . $match->Tireur[0]['Score'];
                                        break;
                                    } elseif ($match->Tireur[1]['REF'] == $tireurID && getValue($xml->xpath("//Poule/Tireur[@REF='{$match->Tireur[0]['REF']}']")[0], 'NoDansLaPoule') == $i) {
                                        $cellContent = $match->Tireur[1]['Statut'] == 'V' ? 'V' . $match->Equipe[1]['Score'] : '' . $match->Tireur[1]['Score'];
                                        break;
                                    }
                                }
                            }
                            echo '<td>' . $cellContent . '</td>';
                        }
                        echo '<td>' . round(getValue($tireur, 'NbVictoires') / getValue($tireur, 'NbMatches'), 3) . '</td>';
                        echo '<td>' . getValue($tireur, 'TD') . '</td>';
                        echo '<td>' . (getValue($tireur, 'TD') - getValue($tireur, 'TR')) . '</td>';
                        echo '<td>' . $ranking[$tireurID] . '</td>';
                        echo '</tr>';
                    }
                }

                echo '</tbody></table></section>';
            }
            ?>
        </div>
    </main>
</body>
</html>
