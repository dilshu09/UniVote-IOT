<?php
$logFile = 'notifications.log';

$lastReadTime = isset($_GET['lastReadTime']) ? intval($_GET['lastReadTime']) : 0;
$notifications = [];

if (file_exists($logFile)) {
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $log = json_decode($line, true);
        if ($log['timestamp'] > $lastReadTime) {
            $notifications[] = $log;
        }
    }
}

echo json_encode(['notifications' => $notifications, 'lastReadTime' => time()]);
?>
