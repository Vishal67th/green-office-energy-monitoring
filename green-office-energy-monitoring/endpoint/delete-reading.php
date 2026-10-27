<?php
// Deletes one meter reading, then sends the user back to the home page.
include("../conn/conn.php");

$id = $_GET['reading'] ?? '';

if ($id === '' || !is_numeric($id)) {
    header("Location: ../index.php");
    exit();
}

try {
    // Bound as a parameter rather than concatenated into the SQL string,
    // which would otherwise make the query string an injection point.
    $stmt = $conn->prepare("DELETE FROM tbl_reading WHERE reading_id = :id");
    $stmt->bindValue(":id", (int) $id, PDO::PARAM_INT);
    $stmt->execute();

    header("Location: ../index.php");
    exit();
} catch (PDOException $e) {
    show_message("Could not delete the reading", $e->getMessage(), "../index.php", 500);
}
