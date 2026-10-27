<?php
// ============================================================
//  Online Green Office Energy Monitoring System
//  This one page does three things:
//    1. reads the meter readings out of the database
//    2. works out how much energy was used each month
//    3. shows it as a table and a chart
// ============================================================

include('./conn/conn.php');

// How much CO2 is released per unit of electricity, in kg per kWh.
// 0.71 is roughly the India grid average. Change it for your country.
$CO2_PER_KWH = 0.71;

// The office aims to stay under this many kWh in a month.
// Set it to whatever target the office has agreed on.
$MONTHLY_TARGET_KWH = 900;

// --- 1. Get every reading, oldest date first ---
$stmt = $conn->prepare("SELECT * FROM tbl_reading ORDER BY reading_date");
$stmt->execute();
$readings = $stmt->fetchAll();

// --- 2. Work out the usage between one reading and the next ---
$rows        = [];   // what the table shows
$chartLabels = [];   // dates along the bottom of the chart
$chartValues = [];   // usage numbers for the chart bars
$totalUsed   = 0;

$previousReading = null;

$problemCount = 0;

foreach ($readings as $r) {
    $used       = null;    // null means the usage cannot be worked out
    $goesBackwards = false;

    if ($previousReading === null) {
        // The very first reading has nothing before it,
        // so we cannot know how much was used yet.
    } elseif ($r['meter_reading'] < $previousReading) {
        // A meter only counts upwards. A smaller number than the one before
        // it means bad data - a typo, or the meter was replaced. Flag the row
        // and leave it out of the totals rather than showing negative usage.
        $goesBackwards = true;
        $problemCount++;
    } else {
        $used = $r['meter_reading'] - $previousReading;

        $chartLabels[] = $r['reading_date'];
        $chartValues[] = $used;
        $totalUsed    += $used;
    }

    $rows[] = [
        'id'         => $r['reading_id'],
        'date'       => $r['reading_date'],
        'reading'    => $r['meter_reading'],
        'used'       => $used,
        'backwards'  => $goesBackwards,
        'over'       => $used !== null && $used > $MONTHLY_TARGET_KWH,
    ];

    // Always move on to the current reading, even a flagged one, so the dates
    // stay in step. A flagged row therefore inflates the row after it.
    $previousReading = $r['meter_reading'];
}

$totalCo2 = $totalUsed * $CO2_PER_KWH;

// --- 3. How did the latest month do against the target? ---
$latestUsed = count($chartValues) > 0 ? $chartValues[count($chartValues) - 1] : null;
$overBy     = $latestUsed === null ? null : $latestUsed - $MONTHLY_TARGET_KWH;

// --- 4. Is the office doing better or worse than last month? ---
$change = null;   // null means there is not enough data to compare yet
if (count($chartValues) >= 2) {
    $thisMonth = $chartValues[count($chartValues) - 1];
    $lastMonth = $chartValues[count($chartValues) - 2];
    if ($lastMonth > 0) {
        $change = ($thisMonth - $lastMonth) / $lastMonth * 100;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Green Office Energy Monitoring System</title>

    <!-- Bootstrap: table, buttons, modal -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-image: linear-gradient(-225deg, #1b5e20 0%, #2e7d32 52%, #66bb6a 100%);
            background-attachment: fixed;
            padding: 24px;
        }

        .card-box {
            background-color: #ffffff;
            border-radius: 10px;
            padding: 28px 32px;
            max-width: 1200px;
            margin: 0 auto;
            box-shadow: rgba(0, 0, 0, 0.35) 0px 5px 15px;
        }

        .header {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #dddddd;
            padding-bottom: 14px;
        }

        .header h3 { font-size: 1.35rem; font-weight: 600; }
        .header small { color: #666666; }

        /* The three summary boxes */
        .summary {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            margin: 20px 0;
        }

        .summary-item {
            flex: 1;
            min-width: 180px;
            background-color: #f1f8f2;
            border-left: 5px solid #2e7d32;
            border-radius: 6px;
            padding: 14px 18px;
        }

        .summary-item .label { font-size: 0.8rem; color: #555555; }
        .summary-item .value { font-size: 1.6rem; font-weight: 600; color: #1b5e20; }

        .down { color: #1b5e20; }   /* using less energy - good */
        .up   { color: #c62828; }   /* using more energy - bad */
        .none { color: #999999; }   /* not enough readings to compare yet */

        .content {
            display: flex;
            flex-wrap: wrap;
            gap: 24px;
        }

        .table-container { flex: 1 1 420px; }

        .graph-container {
            flex: 1 1 420px;
            min-width: 0;
            height: 340px;
        }

        .btn-primary {
            background-color: #2e7d32 !important;
            border-color: #2e7d32 !important;
        }
    </style>
</head>
<body>
    <div class="card-box">

        <div class="header">
            <div>
                <h3>Online Green Office Energy Monitoring System</h3>
                <small>Log the office electricity meter each month and see if usage is going up or down.</small>
            </div>
            <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#addReadingModal">
                + Add Meter Reading
            </button>
        </div>

        <!-- Add-reading form (Bootstrap modal) -->
        <div class="modal fade" id="addReadingModal" tabindex="-1" aria-labelledby="addReading" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addReading">Add Meter Reading</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <form action="./endpoint/add-reading.php" method="POST">
                        <div class="modal-body">
                            <div class="form-group">
                                <label for="readingDate">Date you read the meter</label>
                                <input type="date" class="form-control" id="readingDate" name="reading_date" required>
                            </div>
                            <div class="form-group">
                                <label for="meterReading">Meter reading (kWh)</label>
                                <input type="number" min="0" class="form-control" id="meterReading" name="meter_reading" required>
                                <small class="form-text text-muted">
                                    The total number showing on the meter, not the amount used this month.
                                </small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Three summary numbers -->
        <div class="summary">
            <div class="summary-item">
                <div class="label">Total energy used</div>
                <div class="value"><?php echo number_format($totalUsed); ?> kWh</div>
            </div>
            <div class="summary-item">
                <div class="label">Carbon footprint</div>
                <div class="value"><?php echo number_format($totalCo2); ?> kg CO&#8322;</div>
            </div>
            <div class="summary-item">
                <div class="label">Change since last month</div>
                <div class="value <?php echo ($change === null || $change == 0) ? 'none' : ($change > 0 ? 'up' : 'down'); ?>">
                    <?php
                        if ($change === null) {
                            echo '&ndash;';
                        } elseif ($change == 0) {
                            echo 'no change';
                        } else {
                            echo ($change > 0 ? '&uarr; ' : '&darr; ') . number_format(abs($change), 1) . '%';
                        }
                    ?>
                </div>
            </div>
            <div class="summary-item">
                <div class="label">Against the <?php echo number_format($MONTHLY_TARGET_KWH); ?> kWh target</div>
                <div class="value <?php echo ($overBy === null || $overBy === 0) ? 'none' : ($overBy > 0 ? 'up' : 'down'); ?>">
                    <?php
                        if ($overBy === null) {
                            echo '&ndash;';
                        } elseif ($overBy === 0) {
                            echo 'on target';
                        } elseif ($overBy > 0) {
                            echo number_format($overBy) . ' kWh over';
                        } else {
                            echo number_format(abs($overBy)) . ' kWh under';
                        }
                    ?>
                </div>
            </div>
        </div>

        <?php if ($problemCount > 0) { ?>
            <div class="alert alert-danger py-2">
                <?php echo $problemCount; ?> reading(s) are lower than the reading before them,
                so their usage cannot be worked out. Check the marked rows.
            </div>
        <?php } ?>

        <div class="content">
            <div class="table-container">
                <table class="table table-sm text-center">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Meter (kWh)</th>
                            <th>Used (kWh)</th>
                            <th>CO&#8322; (kg)</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($rows) === 0) { ?>
                            <tr>
                                <td colspan="5" class="text-muted">No readings yet. Add your first meter reading.</td>
                            </tr>
                        <?php } ?>

                        <?php foreach ($rows as $row) { ?>
                            <tr>
                                <td><?php echo $row['date']; ?></td>
                                <td><?php echo number_format($row['reading']); ?></td>
                                <td>
                                    <?php
                                        if ($row['backwards']) {
                                            // Lower than the reading before it.
                                            echo '<span class="text-danger" '
                                               . 'title="Lower than the previous reading">check</span>';
                                        } elseif ($row['used'] === null) {
                                            // The first reading has no earlier one to compare against.
                                            echo '&ndash;';
                                        } elseif ($row['over']) {
                                            echo '<span class="text-danger" title="Over the '
                                               . number_format($MONTHLY_TARGET_KWH) . ' kWh target">'
                                               . number_format($row['used']) . '</span>';
                                        } else {
                                            echo number_format($row['used']);
                                        }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                        echo $row['used'] === null
                                            ? '&ndash;'
                                            : number_format($row['used'] * $CO2_PER_KWH, 1);
                                    ?>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-danger"
                                            onclick="removeReading(<?php echo $row['id']; ?>)">X</button>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <div class="graph-container">
                <canvas id="usageChart"></canvas>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js"></script>

    <script>
        function removeReading(id) {
            if (confirm("Delete this meter reading?")) {
                window.location = "./endpoint/delete-reading.php?reading=" + id;
            }
        }

        // Chart data is printed from the PHP arrays built above.
        new Chart(document.getElementById('usageChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chartLabels); ?>,
                datasets: [
                    {
                        label: 'Energy used each month (kWh)',
                        data: <?php echo json_encode($chartValues); ?>,
                        backgroundColor: '#2e7d32'
                    },
                    {
                        // Flat dashed line across the chart at the target.
                        type: 'line',
                        label: 'Monthly target (<?php echo $MONTHLY_TARGET_KWH; ?> kWh)',
                        data: <?php echo json_encode(array_fill(0, count($chartValues), $MONTHLY_TARGET_KWH)); ?>,
                        borderColor: '#c62828',
                        borderDash: [6, 4],
                        borderWidth: 2,
                        pointRadius: 0,
                        fill: false
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    </script>
</body>
</html>
