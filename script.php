<?php
declare(strict_types=1);

// Open the CSV file
$file = fopen('data.csv', 'r');

// Check if the file was opened successfully
if ($file) {
    // Read the CSV file line by line
    while (($line = fgetcsv($file)) !== false) {
        // Check if the line has at least two columns
        if (count($line) >= 2) {
            $external_id = $line[0];
            $id = $line[1];

            // Generate the SQL UPDATE query
            $query = '$this->addSql(\'UPDATE coaster SET external_id = ' . $external_id . ' WHERE id = ' . $id . '\');';

            // Print the query
            echo $query . "\n";
        }
    }

    // Close the file
    fclose($file);
} else {
    echo "Error opening the file.";
}
?>
