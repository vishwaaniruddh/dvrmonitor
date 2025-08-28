<?php
// Set timezone to Asia/Kolkata
date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/dvr_activity_logger.php';

// Get encoded data from command line argument
if ($argc != 2) {
    exit(1);
}

$encodedData = $argv[1];
$dvrData = json_decode(base64_decode($encodedData), true);

if (!$dvrData) {
    exit(1);
}

// Save to database
save_dvr_activity($dvrData); 