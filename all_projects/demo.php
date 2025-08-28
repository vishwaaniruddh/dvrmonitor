<?php
require 'config.php'; // Include your DB connection

try {
    // Fetch top 10 records with non-null Base64 data
    $stmt = $pdo->query("SELECT File_loc FROM ai_alerts WHERE File_loc IS NOT NULL ");
    $rows = $stmt->fetchAll();

    if ($rows) {
        // Optional: Create folder if needed
        $save_path = __DIR__ . '/images/';
        if (!file_exists($save_path)) {
            mkdir($save_path, 0777, true);
        }

        $count = 0;

        foreach ($rows as $row) {
            $base64_string = $row['File_loc'];

            if (empty($base64_string)) {
                continue; // skip if empty
            }

            // Detect extension from base64 prefix (optional)
            preg_match("/^data:image\/(.*);base64/i", $base64_string, $match);
            $extension = $match[1] ?? 'png';

            // Remove the prefix if it exists
            if (strpos($base64_string, 'base64,') !== false) {
                $base64_string = explode('base64,', $base64_string)[1];
            }

            // Decode and save image
            $image_data = base64_decode($base64_string);
            $filename = 'image_' . time() . "_$count.$extension";
            file_put_contents($save_path . $filename, $image_data);

            echo "✅ Saved: <b>images/$filename</b><br>";
            $count++;
        }
    } else {
        echo "⚠️ No Base64 image data found.";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
