<?php
// One styled page for the messages that stop a request: bad input, a reading
// that would break the meter sequence, or the database being unreachable.
// Included by conn/conn.php, so every page that talks to the database has it.

function show_message(string $heading, string $detail, ?string $backLink = null, int $status = 400): void
{
    if (!headers_sent()) {
        http_response_code($status);
    }

    $heading = htmlspecialchars($heading, ENT_QUOTES, 'UTF-8');
    $detail  = htmlspecialchars($detail,  ENT_QUOTES, 'UTF-8');
    $back    = $backLink === null ? '' :
        '<a class="back" href="' . htmlspecialchars($backLink, ENT_QUOTES, 'UTF-8') . '">Back to the readings</a>';

    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>$heading</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap');

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }

        body {
            background-image: linear-gradient(-225deg, #1b5e20 0%, #2e7d32 52%, #66bb6a 100%);
            background-attachment: fixed;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .message-box {
            background-color: #ffffff;
            border-radius: 10px;
            border-top: 5px solid #c62828;
            padding: 32px;
            max-width: 520px;
            box-shadow: rgba(0, 0, 0, 0.35) 0px 5px 15px;
        }

        h1 { font-size: 1.2rem; font-weight: 600; color: #c62828; margin-bottom: 10px; }
        p  { font-size: 0.95rem; color: #444444; line-height: 1.55; }

        .back {
            display: inline-block;
            margin-top: 22px;
            background-color: #2e7d32;
            color: #ffffff;
            text-decoration: none;
            padding: 9px 18px;
            border-radius: 6px;
            font-size: 0.9rem;
        }

        .back:hover { background-color: #1b5e20; }
    </style>
</head>
<body>
    <div class="message-box">
        <h1>$heading</h1>
        <p>$detail</p>
        $back
    </div>
</body>
</html>
HTML;

    exit();
}
