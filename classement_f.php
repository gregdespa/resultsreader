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



function calculerIndiceindiv($xml, $Id, $hasPoules) {
    $TD = 0;
    $TR = 0;
    $isIndividual = isset($xml->Tireurs);

    // Parcourir tous les tours de poules
    if ($hasPoules) {
        foreach ($xml->Phases->TourDePoules as $tour) {
            foreach ($tour->Poule as $poule) {
                foreach ($poule->Match as $match) {                
                    if ($isIndividual ? (string)$match->Tireur[0]['REF'] === $Id : (string)$match->Equipe[0]['REF'] === $Id) {
                        $TD += $isIndividual ? (int)$match->Tireur[0]['Score'] : (int)$match->Equipe[0]['Score'];
                        $TR += $isIndividual ? (int)$match->Tireur[1]['Score'] : (int)$match->Equipe[1]['Score'];
                    } elseif ($isIndividual ? (string)$match->Tireur[1]['REF'] === $Id : (string)$match->Equipe[1]['REF'] === $Id) {
                        $TD += $isIndividual ? (int)$match->Tireur[1]['Score'] : (int)$match->Equipe[1]['Score'];
                        $TR += $isIndividual ? (int)$match->Tireur[0]['Score'] : (int)$match->Equipe[0]['Score'];
                    }
                }
            }
        }
    }

    // Parcourir les matchs de tableau
    foreach ($xml->Phases->PhaseDeTableaux as $phase) {
        foreach ($phase->SuiteDeTableaux as $suite) {
            foreach ($suite->Tableau as $tableau) {
                foreach ($tableau->Match as $match) {                
                    if ($isIndividual ? (string)$match->Tireur[0]['REF'] === $Id : (string)$match->Equipe[0]['REF'] === $Id) {
                        $TD += $isIndividual ? (int)$match->Tireur[0]['Score'] : (int)$match->Equipe[0]['Score'];
                        $TR += $isIndividual ? (int)$match->Tireur[1]['Score'] : (int)$match->Equipe[1]['Score'];
                    } elseif ($isIndividual ? (string)$match->Tireur[1]['REF'] === $Id : (string)$match->Equipe[1]['REF'] === $Id) {
                        $TD += $isIndividual ? (int)$match->Tireur[1]['Score'] : (int)$match->Equipe[1]['Score'];
                        $TR += $isIndividual ? (int)$match->Tireur[0]['Score'] : (int)$match->Equipe[0]['Score'];
                    }
                }
            }
        }
    }

    return $TD - $TR;
}


?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Classement Final</title>
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
                "ordering": true
            });

            function applyMedalColors() {
                $('#tireurs-table tbody tr').each(function() {
                    var rank = parseInt($(this).data('rank'));
                    $(this).removeClass('gold-medal silver-medal bronze-medal');
                    $(this).find('td').removeClass('gold-medal silver-medal bronze-medal');
                    if (rank === 1) {
                        $(this).addClass('gold-medal');
                        $(this).find('td').addClass('gold-medal');
                    } else if (rank === 2) {
                        $(this).addClass('silver-medal');
                        $(this).find('td').addClass('silver-medal');
                    } else if (rank === 3) {
                        $(this).addClass('bronze-medal');
                        $(this).find('td').addClass('bronze-medal');
                    }
                });
            }

            // Apply medal colors on initial load
            applyMedalColors();

            // Apply medal colors after each draw event
            table.on('draw', function() {
                applyMedalColors();
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
    <?php if ($isTeam): ?>
    <div class="button-container">
        <button id="classement-rang-button">Classement par Rang</button>
        <button id="classement-indice-button">Classement individuel par Indice</button>
        
    </div>
    <?php endif; ?>

    <main>
        <div class="results-table-container">
            
            <section class="results-table">
                <?php
                if ($isIndividual) {
                    echo '<table id="tireurs-table" class="display">';
                    echo '<thead>';
                    echo '<tr>';
                    echo '<th>Classement</th>';
                    echo '<th>Nom</th>';
                    echo '<th>Prénom</th>';
                    echo '<th>' . (getValue($xml, 'Domaine') == 'I' ? 'Pays' : 'Club') . '</th>';
                    echo '<th>Indice</th>';
                    echo '</tr>';
                    echo '</thead>';
                    echo '<tbody>';
                   

                    foreach($xml->Tireurs->Tireur as $tireur) {
                        $id = getValue($tireur, 'ID');
                        $rangFinal = getValue($tireur, 'Classement');
                        echo '<tr data-id="' . $id . '" data-type="Tireur" data-rank="' . $rangFinal . '">';
                        echo '<td>' . $rangFinal . '</td>';
                        echo '<td>' . getValue($tireur, 'Nom') . '</td>';
                        echo '<td>' . getValue($tireur, 'Prenom') . '</td>';
                        echo '<td>' . (getValue($xml, 'Domaine') == 'I' ? getValue($tireur, 'Nation') : getValue($tireur, 'Club')) . '</td>';
                        echo '<td>' . calculerIndiceindiv($xml, $id, $hasPoules) . '</td>';
                        echo '</tr>';
                    }
                    echo '</tbody>';
                    echo '</table>';
                } elseif ($isTeam) {
                    echo '<table id="tireurs-table" class="display">';
                    echo '<thead>';
                    echo '<tr>';
                    echo '<th>RG</th>';
                    echo '<th>Nom de l\'équipe</th>';
                    echo '<th>' . (getValue($xml, 'Domaine') == 'I' ? 'Pays' : 'Club') . '</th>';
                    echo '<th>Indice</th>';
                    echo '</tr>';
                    echo '</thead>';
                    echo '<tbody>';
    
                    foreach($xml->Equipes->Equipe as $equipe) {
                        $id = getValue($equipe, 'ID');
                        $rangFinal = getValue($equipe,'Classement');
                        echo '<tr data-id="' . $id . '" data-type="Equipe" data-rank="' . $rangFinal . '">';
                        echo '<td>' . $rangFinal . '</td>';
                        echo '<td>';
                        echo '<strong>' . getValue($equipe, 'Nom') . '</strong><br>';
                        foreach ($equipe->Tireur as $membre) {
                            echo getValue($membre, 'Nom') . ' ' . getValue($membre, 'Prenom') . '<br>';
                        }
                        echo '</td>';
                        echo '<td>' . getValue($equipe, 'Club') . '</td>';
                        echo '<td>' . calculerIndiceindiv($xml, $id, $hasPoules) . '</td>';
                        echo '</tr>';
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
