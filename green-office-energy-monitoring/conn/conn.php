<?php
// Database login details.
// These are the XAMPP defaults: user "root" with no password.
require_once(__DIR__ . '/../message.php');
$servername = "localhost";
$username   = "root";
$password   = "";
$db         = "green_office_db";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$db", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Stop here instead of carrying on with a broken $conn.
    show_message(
        "Could not reach the database",
        "Check that MySQL is running in XAMPP and that green_office_db has been imported. "
        . "The database said: " . $e->getMessage(),
        null,
        500
    );
}
