<?php
// Charger le fichier XML
///$xml = simplexml_load_file('lille.xml') or die("Error: Cannot create object");
require("config.php");
// Déterminer le type de compétition
$isIndividual = isset($xml->Tireurs);
$isTeam = isset($xml->Equipes);

// Fonction pour obtenir une valeur avec vérification de l'existence
function getValue($xml, $field) {
    return isset($xml[$field]) ? htmlentities($xml[$field], ENT_QUOTES | ENT_XML1, 'UTF-8') : '';
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
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des tireurs</title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.4/js/jquery.dataTables.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.4/css/jquery.dataTables.min.css">
    <style>
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
            $('#tireurs-table').DataTable({
                "paging": false,
                "searching": true,
                "ordering": true
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
                window.location.href = 'arbitre.php';
            });

            // Redirect to classement_f.php when button is clicked
            $('#classement-rang-button').on('click', function() {
                window.location.href = 'inscrits.php';
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
        <button id="classement-rang-button">Tireurs</button>
        <button id="classement-indice-button">Arbitres</button>
        
    </div>
    <main>
        <div class="results-table-container">
            <section class="results-table">
                <?php
                if ($isIndividual) {
                    echo '<table id="tireurs-table" class="display">';
                    echo '<thead>';
                    echo '<tr>';
                    echo '<th>Rang d\'entrée</th>';
                    echo '<th>Nom</th>';
                    echo '<th>Prénom</th>';
                    echo '<th>' .(getValue($xml,'Domaine') == 'I' ? 'Pays' : 'Club'). '</th>';
                    echo '</tr>';
                    echo '</thead>';
                    echo '<tbody>';
                    foreach($xml->Tireurs->Tireur as $tireur) {
                        $id = getValue($tireur, 'ID');
                        $rangInit = getValue($xml->xpath("//Phases//TourDePoules//Tireur[@REF='$id']")[0], 'RangInitial');
                        echo '<tr data-id="' . $id . '" data-type="Tireur">';
                        echo '<td>' . $rangInit .  '</td>';
                        echo '<td>' . getValue($tireur, 'Nom') . '</td>';
                        echo '<td>' . getValue($tireur, 'Prenom') . '</td>';
                        echo '<td>' . (getValue($xml,'Domaine') == 'I' ? getValue($tireur, 'Nation') : getValue($tireur, 'Club')) . '</td>';
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
                    echo '<th>Club</th>';
                    echo '</tr>';
                    echo '</thead>';
                    echo '<tbody>';
                    foreach($xml->Equipes->Equipe as $equipe) {
                        $id = getValue($equipe, 'ID');
                        $rangInitial = getValue($xml->xpath("//Phases//TourDePoules//Equipe[@REF='$id']")[0], 'RangInitial')?getValue($xml->xpath("//Phases//TourDePoules//Equipe[@REF='$id']")[0], 'RangInitial') :getValue($xml->xpath("//Phases//PhaseDeTableaux//Equipe[@REF='$id']")[0], 'RangInitial') ;
                        echo '<tr data-id="' . $id . '" data-type="Equipe">';
                        echo '<td>' . $rangInitial . '</td>';
                        echo '<td>';
                        echo '<strong>' . getValue($equipe, 'Nom') . '</strong><br>';
                        foreach ($equipe->Tireur as $membre) {
                            echo getValue($membre, 'Nom') . ' ' . getValue($membre, 'Prenom') . '<br>';
                        }
                        echo '</td>';
                        echo '<td>' . (getValue($xml,'Domaine') == 'I' ? getValue($equipe, 'Nation') : getValue($equipe, 'Club')) . '</td>';
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
