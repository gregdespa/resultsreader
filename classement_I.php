<?php
require_once("config.php");

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

// Déterminer le type de compétition
$isIndividual = isset($xml->Tireurs);
$isTeam = isset($xml->Equipes);

$hasPoules = false;
if (isset($xml->Phases->TourDePoules)) {
    $hasPoules = true;
}

// Fonction pour obtenir une valeur avec vérification de l'existence
function getValue($xml, $field) {
    return isset($xml[$field]) ? htmlentities($xml[$field], ENT_QUOTES | ENT_XML1, 'UTF-8') : '';
}

function getTeamID($fencerID, $xml) {
    foreach($xml->Equipes->Equipe as $equipe) {
        foreach($equipe->Tireur as $tireur) {
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
    return $index;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Classement par Indice</title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.4/js/jquery.dataTables.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.4/css/jquery.dataTables.min.css">
    <style>
        .gold-medal {
            background-color: gold;
            color: black;
        }
        .silver-medal {
            background-color: silver;
            color: black;
        }
        .bronze-medal {
            background-color: #a67d3d !important;
            color: black;
        }
        .button-container {
            margin-bottom: 20px;
            text-align: center;
        }
        .button-container button {
            padding: 10px 20px;
            font-size: 16px;
            cursor: pointer;
            margin: 0 5px; /* Ajouter un espace entre les boutons */
        }
    </style>
    <script>
        $(document).ready(function() {
            var table = $('#tireurs-table').DataTable({
                "paging": false,
                "searching": true,
                "ordering": true,
                "order": [[0, "desc"]], // Trier par l'indice (colonne 0) en ordre décroissant
                "drawCallback": function(settings) {
                    var api = this.api();
                    var rows = api.rows({page:'current'}).nodes();
                    var last = null;

                    api.column(0, {page:'current'}).data().each(function(group, i) {
                        $(rows).eq(i).removeClass('gold-medal silver-medal bronze-medal');
                        $(rows).eq(i).find('td').removeClass('gold-medal silver-medal bronze-medal');
                        if (i === 0) {
                            $(rows).eq(i).addClass('gold-medal');
                            $(rows).eq(i).find('td').addClass('gold-medal');
                        } else if (i === 1) {
                            $(rows).eq(i).addClass('silver-medal');
                            $(rows).eq(i).find('td').addClass('silver-medal');
                        } else if (i === 2) {
                            $(rows).eq(i).addClass('bronze-medal');
                            $(rows).eq(i).find('td').addClass('bronze-medal');
                        }
                    });
                }
            });

            // Add click event to each row
            $('#tireurs-table tbody').on('click', 'tr', function() {
                var id = $(this).data('id');
                var type = $(this).data('type');
                var url = type === 'Equipe' ? 'equipe.php' : 'tireur.php';
                window.location.href = url + '?id=' + id;
            });

            // Redirect to classement_I.php when button is clicked
            $('#classement-indice-button').on('click', function() {
                window.location.href = 'classement_I.php';
            });

            // Redirect to classement_f.php when button is clicked
            $('#classement-rang-button').on('click', function() {
                window.location.href = 'classement_f.php';
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
    <div class="button-container">
                <button id="classement-indice-button">Classement par Indice</button>
                <button id="classement-rang-button">Classement par Rang</button>
     </div>
    <main>
        <div class="results-table-container">
            
            <section class="results-table">
                <?php
                if ($isIndividual) {
                    
                } elseif ($isTeam) {
                    echo '<table id="tireurs-table" class="display">';
                    echo '<thead>';
                    echo '<tr>';
                    echo '<th>IND</th>';
                    echo '<th>Nom de l\'équipe</th>';
                    echo '<th>Nom</th>';
                    echo '<th>Prénom</th>';
                    echo '<th>' . (getValue($xml, 'Domaine') == 'I' ? 'Pays' : 'Club') . '</th>';                    
                    echo '</tr>';
                    echo '</thead>';
                    echo '<tbody>';
    
                    foreach($xml->Equipes->Equipe as $equipe) { 
                        $teamid=getValue($equipe,'ID');                       
                        foreach ($equipe->Tireur as $membre) {
                            $id = getValue($membre, 'ID');
                            $indice = getMemberStats($xml, $teamid, $id);
                            echo '<tr data-id="' . $id . '" data-type="Equipe" data-rank="' . $indice . '">';
                            echo '<td>' . $indice . '</td>';
                            echo '<td>';
                            echo   getValue($equipe, 'Nom') ;
                            echo '</td>';
                            echo '<td>' .getValue($membre, 'Nom') . '</td> ';
                            echo '<td>'. getValue($membre, 'Prenom'). '</td>';
                            echo '<td>' . getValue($membre, 'Club') . '</td>';                           
                            echo '</tr>';
                        }
                    }
                    echo '</tbody>';
                    echo '</table>';
                }
                ?>
            </section>
        </div>
    </main>
</body>
</html>
