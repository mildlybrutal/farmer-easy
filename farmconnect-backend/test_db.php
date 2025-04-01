<?php
include 'api/database.php';

try {
    $stmt = $pdo->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo $row['Tables_in_farmconnect'] . "<br>";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
