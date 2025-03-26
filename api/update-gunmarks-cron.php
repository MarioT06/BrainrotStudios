<?php
/**
 * Gun Marks Update Cron Job Script
 * 
 * This script is designed to be run by a cron job at a scheduled time (preferably 9 AM daily)
 * It forces the fetch-gunmarks.php script to update its data regardless of the last update time
 * 
 * Example cron entry (runs at 9 AM daily):
 * 0 9 * * * /usr/bin/php /path/to/your/website/api/update-gunmarks-cron.php > /dev/null 2>&1
 */

// Set error reporting and disable output buffering
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_implicit_flush(true);

// Define log file path
$logFile = __DIR__ . '/../data/cron_gunmarks_log.txt';

// Function to log activity
function logActivity($message) {
    global $logFile;
    $timestamp = date('[Y-m-d H:i:s]');
    file_put_contents($logFile, "$timestamp $message" . PHP_EOL, FILE_APPEND);
}

logActivity("Starting scheduled Gun Marks update");

// Create data directory if it doesn't exist
if (!file_exists(__DIR__ . '/../data')) {
    mkdir(__DIR__ . '/../data', 0755, true);
    logActivity("Created data directory");
}

try {
    // URL for poliroid.me API
    $url = 'https://poliroid.me/api/wot/gunmarks';
    
    logActivity("Fetching data from $url");
    
    // Initialize cURL session
    $ch = curl_init();
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    
    // Execute cURL session
    $result = curl_exec($ch);
    
    // Check for errors
    if (curl_errno($ch)) {
        throw new Exception('cURL error: ' . curl_error($ch));
    }
    
    // Close cURL session
    curl_close($ch);
    
    // Decode JSON response
    $data = json_decode($result, true);
    
    // Check if we got valid data
    if (!$data || !isset($data['data'])) {
        throw new Exception('Invalid data received');
    }
    
    logActivity("Received valid data from API, processing " . count($data['data']) . " tanks");
    
    // Process the data into the format we need
    $processedData = [];
    
    foreach ($data['data'] as $tank) {
        // Skip tanks without proper marks data
        if (!isset($tank['marks'])) {
            logActivity("Skipping tank ID {$tank['id']} - no marks data");
            continue;
        }
        
        // Create a clean tank object with only the data we need
        $processedTank = [
            'id' => $tank['id'] ?? '',
            'name' => $tank['name'] ?? '',
            'short_name' => $tank['short_name'] ?? '',
            'nation' => $tank['nation'] ?? '',
            'type' => $tank['type'] ?? '',
            'tier' => $tank['tier'] ?? 0,
            'is_premium' => $tank['is_premium'] ?? false,
            'values' => [
                '65' => $tank['marks']['65'] ?? 0,
                '85' => $tank['marks']['85'] ?? 0,
                '95' => $tank['marks']['95'] ?? 0
            ]
        ];
        
        $processedData[] = $processedTank;
    }
    
    // Save processed data to file
    if (!empty($processedData)) {
        $dataFile = __DIR__ . '/../data/gunmarks.json';
        
        $jsonData = json_encode([
            'lastUpdated' => time(),
            'data' => $processedData
        ]);
        
        file_put_contents($dataFile, $jsonData);
        logActivity("Successfully updated data file with " . count($processedData) . " tanks");
        
        echo "Update completed successfully: " . count($processedData) . " tanks processed\n";
    } else {
        throw new Exception('No valid tank data found to save');
    }
    
} catch (Exception $e) {
    logActivity("ERROR: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

logActivity("Gun Marks update completed successfully");
echo "Gun Marks data update completed successfully.\n";
exit(0); 