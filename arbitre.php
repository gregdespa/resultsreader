<?php
// Charger le fichier XML
require("config.php");

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

if (isset($xml->Phases->TourDePoules)) {
    $hasPoules = true;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des arbitres</title>
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
            $('#arbitres-table').DataTable({
                "paging": false,
                "searching": true,
                "ordering": true
            });

            // Add click event to each row
            $('#arbitres-table tbody').on('click', 'tr', function() {
                var id = $(this).data('id');
                window.location.href = 'ref.php?id=' + id;
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
        <button id="classement-rang-button"> Tireurs</button>
        <button id="classement-indice-button">Arbitres</button>
        
    </div>
    <main>
        <div class="results-table-container">
            <section class="results-table">
                <table id="arbitres-table" class="display">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Prénom</th>
                            <th>Club</th>
                            <th>Niveau</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach($xml->Arbitres->Arbitre as $arbitre) {
                            $id = getValue($arbitre, 'ID');
                            $niveauCodes = [
                                'I' => 'International',
                                'N' => 'National',
                                'FN' => 'Formation National',
                                'R' => 'Régional',
                                'FR' => 'Formation Régional',
                                'D' => 'Départemental',
                                'FD' => 'Formation Départemental'
                            ];
                            $niveau = getValue($arbitre, 'Categorie');
                            $niveau = isset($niveauCodes[$niveau]) ? $niveauCodes[$niveau] : $niveau;

                            echo '<tr data-id="' . $id . '">';
                            echo '<td>' . getValue($arbitre, 'Nom') . '</td>';
                            echo '<td>' . getValue($arbitre, 'Prenom') . '</td>';
                            echo '<td>' . (getValue($xml,'Domaine') == 'I' ? getValue($arbitre, 'Nation') : getValue($arbitre, 'Club')) . '</td>';
                            echo '<td>' . $niveau . '</td>';
                            echo '</tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </section>
        </div>
    </main>
</body>
</html>
