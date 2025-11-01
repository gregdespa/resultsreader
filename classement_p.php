<?php

require_once("config.php");
// Charger le fichier XML
/*$xml = simplexml_load_file('1.xml') or die("Error: Cannot create object");*/
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

// Fonction pour obtenir une valeur avec vérification de l'existence
function getValue($xml, $field) {
    return isset($xml[$field]) ? htmlentities($xml[$field], ENT_QUOTES | ENT_XML1, 'UTF-8') : '';
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Classement après les poules</title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.4/js/jquery.dataTables.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.4/css/jquery.dataTables.min.css">
    <style>
        .red-back {
            background-color: red !important;
            color: white;
        }
    </style>
    <script>
        $(document).ready(function() {
            var table = $('#tireurs-table').DataTable({
                "paging": false,
                "searching": true,
                "ordering": true
            });

            function applyRowColors() {
                $('#tireurs-table tbody tr').each(function() {
                    var statut = $(this).data('statut');
                    $(this).removeClass('red-back');
                    $(this).find('td').removeClass('red-back');
                    if (statut == 'N') {
                        $(this).addClass('red-back');
                        $(this).find('td').addClass('red-back');
                    }
                });
            }

            // Apply row colors on initial load
            applyRowColors();

            // Apply row colors after each draw event
            table.on('draw', function() {
                applyRowColors();
            });

            // Add click event to each row
            $('#tireurs-table tbody').on('click', 'tr', function() {
                var id = $(this).data('id');
                var type = $(this).data('type');
                var url = type === 'Equipe' ? 'equipe.php' : 'tireur.php';
                window.location.href = url + '?id=' + id;
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
                    echo '<th>Vic/match</th>';
                    echo '<th>Ind</th>';
                    echo '<th>TD</th>';
                    echo '<th>Statut</th>';
                    echo '</tr>';
                    echo '</thead>';
                    echo '<tbody>';
                    foreach ($xml->Tireurs->Tireur as $tireur) {
                        $id = getValue($tireur, 'ID');
                        $pouleTireur = $xml->xpath("//Poule/Tireur[@REF='$id']")[0];
                        $rangFinal = getValue($xml->xpath("//Phases//TourDePoules//Tireur[@REF='$id']")[0], 'RangFinal');
                        $nbVictoires = (int) getValue($pouleTireur, 'NbVictoires');
                        $nbMatches = max((int) getValue($pouleTireur, 'NbMatches'), 1); // éviter la division par zéro
                        $ratio = round($nbVictoires / $nbMatches, 2);
                        $td = (int) getValue($pouleTireur, 'TD');
                        $tr = (int) getValue($pouleTireur, 'TR');
                        $ind = $td - $tr;
                        $statut = getValue($xml->xpath("//Phases//TourDePoules//Tireur[@REF='$id']")[0], 'Statut');
                        $rowClass = $statut == 'N' ? 'red-back' : '';

                        echo '<tr data-id="' . $id . '" data-type="Tireur" data-statut="' . $statut . '">';
                        echo '<td class="'. $rowClass . '">' . $rangFinal . '</td>';
                        echo '<td class="'. $rowClass . '">' . getValue($tireur, 'Nom') . '</td>';
                        echo '<td class="'. $rowClass . '">' . getValue($tireur, 'Prenom') . '</td>';
                        echo '<td class="'. $rowClass . '">' . (getValue($xml, 'Domaine') == 'I' ? getValue($tireur, 'Nation') : getValue($tireur, 'Club')) . '</td>';
                        echo '<td class="'. $rowClass . '">' . $ratio . '</td>';
                        echo '<td class="'. $rowClass . '">' . $ind . '</td>';
                        echo '<td class="'. $rowClass . '">' . $td . '</td>';
                        echo '<td class="'. $rowClass . '">' . $statut . '</td>';
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
                    echo '<th>Vic/match</th>';
                    echo '<th>Ind</th>';
                    echo '<th>TD</th>';
                    echo '<th>Statut</th>';
                    echo '</tr>';
                    echo '</thead>';
                    echo '<tbody>';
                    foreach ($xml->Equipes->Equipe as $equipe) {
                        $id = getValue($equipe, 'ID');
                        $pouleEquipe = $xml->xpath("//Poule/Equipe[@REF='$id']")[0];
                        $rangFinal = getValue($xml->xpath("//Phases//TourDePoules//Equipe[@REF='$id']")[0], 'RangFinal');
                        $nbVictoires = (int) getValue($pouleEquipe, 'NbVictoires');
                        $nbMatches = max((int) getValue($pouleEquipe, 'NbMatches'), 1); // éviter la division par zéro
                        $ratio = round($nbVictoires / $nbMatches, 2);
                        $td = (int) getValue($pouleEquipe, 'TD');
                        $tr = (int) getValue($pouleEquipe, 'TR');
                        $ind = $td - $tr;
                        $statut = getValue($xml->xpath("//Phases//TourDePoules//Equipe[@REF='$id']")[0], 'Statut');
                        $rowClass = $statut == 'N' ? 'red-back' : '';

                        echo '<tr data-id="' . $id . '" data-type="Equipe" data-statut="' . $statut . '">';
                        echo '<td class="'. $rowClass . '">' . $rangFinal . '</td>';
                        echo '<td class="'. $rowClass . '">';
                        echo '<strong>' . getValue($equipe, 'Nom') . '</strong><br>';
                        foreach ($equipe->Tireur as $membre) {
                            echo getValue($membre, 'Nom') . ' ' . getValue($membre, 'Prenom') . '<br>';
                        }
                        echo '</td>';
                        echo '<td class="'. $rowClass . '">' . (getValue($xml, 'Domaine') == 'I' ? getValue($equipe, 'Nation') : getValue($equipe, 'Club')) . '</td>';
                        echo '<td class="'. $rowClass . '">' . $ratio . '</td>';
                        echo '<td class="'. $rowClass . '">' . $ind . '</td>';
                        echo '<td class="'. $rowClass . '">' . $td . '</td>';
                        echo '<td class="'. $rowClass . '">' . $statut . '</td>';
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
