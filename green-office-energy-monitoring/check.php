<?php
// Self-test for the calculations in index.php.
// Uses fixed sample values and does not touch the database.
// In the browser:  http://localhost/green-office-energy-monitoring/check.php
// In a terminal:   php check.php

$CO2_PER_KWH        = 0.71;
$MONTHLY_TARGET_KWH = 900;

// Same calculation as index.php, kept in one place so it can be checked.
function monthlyUsage(array $readings): array
{
    $values   = [];
    $total    = 0;
    $problems = 0;
    $previous = null;

    foreach ($readings as $r) {
        if ($previous !== null) {
            if ($r['meter_reading'] < $previous) {
                $problems++;                    // meter went backwards - skip it
            } else {
                $used     = $r['meter_reading'] - $previous;
                $values[] = $used;
                $total   += $used;
            }
        }
        $previous = $r['meter_reading'];
    }

    return ['values' => $values, 'total' => $total, 'problems' => $problems];
}

function percentChange(array $values)
{
    if (count($values) < 2) {
        return null;
    }
    $lastMonth = $values[count($values) - 2];
    if ($lastMonth <= 0) {
        return null;                            // cannot divide by zero
    }
    return ($values[count($values) - 1] - $lastMonth) / $lastMonth * 100;
}

$problems = [];

function expect($label, $got, $want) {
    global $problems;
    if ($got !== $want) {
        $problems[] = "$label: expected " . json_encode($want) . ", got " . json_encode($got);
    }
}

// --- Normal readings. The id gaps are intentional: that is what a deleted row
// --- leaves behind, and the result must still be correct.
$normal = monthlyUsage([
    ['reading_id' => 1, 'reading_date' => '2025-04-01', 'meter_reading' => 12000],
    ['reading_id' => 5, 'reading_date' => '2025-05-01', 'meter_reading' => 12850],
    ['reading_id' => 9, 'reading_date' => '2025-06-01', 'meter_reading' => 13790],
]);
expect('monthly usage',   $normal['values'],   [850, 940]);
expect('total usage',     $normal['total'],    1790);
expect('flagged rows',    $normal['problems'], 0);
expect('CO2 total',       round($normal['total'] * $CO2_PER_KWH, 1), 1270.9);
expect('percent change',  round(percentChange($normal['values']), 2), 10.59);

// --- A reading lower than the one before it: meter replaced, or a typo.
// --- It must be flagged and left out of the total, not counted as negative.
$backwards = monthlyUsage([
    ['reading_id' => 1, 'reading_date' => '2025-04-01', 'meter_reading' => 12000],
    ['reading_id' => 2, 'reading_date' => '2025-05-01', 'meter_reading' => 12850],
    ['reading_id' => 3, 'reading_date' => '2025-06-01', 'meter_reading' => 400],
    ['reading_id' => 4, 'reading_date' => '2025-07-01', 'meter_reading' => 1250],
]);
expect('usage around a backwards reading', $backwards['values'],   [850, 850]);
expect('total skips the bad month',        $backwards['total'],    1700);
expect('one row flagged',                  $backwards['problems'], 1);

// --- The monthly target: which months went over, and by how much the latest one missed.
$overTarget = array_values(array_filter($normal['values'], fn($v) => $v > $MONTHLY_TARGET_KWH));
expect('months over the target', $overTarget, [940]);
expect('latest month against the target',
    $normal['values'][count($normal['values']) - 1] - $MONTHLY_TARGET_KWH, 40);

// --- Not enough data to compare, and a zero previous month.
expect('no readings',          monthlyUsage([])['values'], []);
expect('change with one value', percentChange([850]),      null);
expect('change after a zero',   percentChange([0, 820]),   null);

if (count($problems) === 0) {
    echo "OK - all checks passed\n";
} else {
    echo "FAILED:\n - " . implode("\n - ", $problems) . "\n";
}
