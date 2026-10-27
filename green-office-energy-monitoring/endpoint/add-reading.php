<?php
// Saves one new meter reading, then sends the user back to the home page.
include("../conn/conn.php");

// Only accept submissions from the form.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../index.php");
    exit();
}

$readingDate  = trim($_POST['reading_date']  ?? '');
$meterReading = trim($_POST['meter_reading'] ?? '');

// isset() is not enough here: an empty text input is still "set",
// so check for empty strings and non-numeric input explicitly.
if ($readingDate === '' || $meterReading === '' || !is_numeric($meterReading)) {
    show_message(
        "Nothing was saved",
        "Fill in both the date and the meter reading, and make sure the reading is a number.",
        "../index.php"
    );
}

// The form uses <input type="date">, but a request made by hand can carry
// anything. Without this MySQL would store a bad date as 0000-00-00.
$parsedDate = DateTime::createFromFormat('Y-m-d', $readingDate);
if (!$parsedDate || $parsedDate->format('Y-m-d') !== $readingDate) {
    show_message(
        "That date is not valid",
        "Enter the date as YYYY-MM-DD, for example 2026-03-01.",
        "../index.php"
    );
}

$meterReading = (int) $meterReading;

try {
    // A meter only counts upwards, so the new reading has to fit between the
    // readings either side of its date. Checking both sides means a reading
    // added out of order cannot break the sequence either.
    $check = $conn->prepare(
        "SELECT
           (SELECT MAX(meter_reading) FROM tbl_reading WHERE reading_date < :before_date) AS reading_before,
           (SELECT MIN(meter_reading) FROM tbl_reading WHERE reading_date > :after_date)  AS reading_after"
    );
    $check->bindValue(":before_date", $readingDate);
    $check->bindValue(":after_date",  $readingDate);
    $check->execute();
    $limits = $check->fetch(PDO::FETCH_ASSOC);

    if ($limits['reading_before'] !== null && $meterReading < $limits['reading_before']) {
        show_message(
            "That reading is too low",
            "A meter only counts upwards. The reading before this date is "
            . $limits['reading_before'] . ", so this one has to be at least that.",
            "../index.php"
        );
    }

    if ($limits['reading_after'] !== null && $meterReading > $limits['reading_after']) {
        show_message(
            "That reading is too high",
            "The reading after this date is " . $limits['reading_after']
            . ", so this one cannot be higher than that.",
            "../index.php"
        );
    }

    $stmt = $conn->prepare(
        "INSERT INTO tbl_reading (reading_date, meter_reading) VALUES (:reading_date, :meter_reading)"
    );
    $stmt->bindValue(":reading_date",  $readingDate);
    $stmt->bindValue(":meter_reading", $meterReading, PDO::PARAM_INT);
    $stmt->execute();

    header("Location: ../index.php");
    exit();
} catch (PDOException $e) {
    show_message("Could not save the reading", $e->getMessage(), "../index.php", 500);
}
