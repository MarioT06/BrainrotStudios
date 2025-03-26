<?php
// Set error reporting and CORS headers
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Data storage file path
$dataFile = '../data/gunmarks.json';
$logFile = '../data/gunmarks_log.txt';

// Function to log activity
function logActivity($message) {
    global $logFile;
    $timestamp = date('[Y-m-d H:i:s]');
    file_put_contents($logFile, "$timestamp $message" . PHP_EOL, FILE_APPEND);
}

// Create data directory if it doesn't exist
if (!file_exists('../data')) {
    mkdir('../data', 0755, true);
    logActivity("Created data directory");
}

// Check if we need to update data (only once per day)
$updateNeeded = true;
if (file_exists($dataFile)) {
    $fileModTime = filemtime($dataFile);
    $now = time();
    $dayDiff = floor(($now - $fileModTime) / (60 * 60 * 24));
    
    // If the file is less than 1 day old, don't update
    if ($dayDiff < 1) {
        $updateNeeded = false;
        logActivity("Data is less than 1 day old, no update needed");
    }
}

// Create data array to return
$response = [
    'success' => true,
    'message' => '',
    'data' => [],
    'lastUpdated' => null
];

// Fetch data from poliroid.me if needed
if ($updateNeeded) {
    try {
        logActivity("Fetching new data from poliroid.me");
        
        // URL for EU server data
        $url = 'https://poliroid.me/api/wot/gunmarks';
        
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
        
        // Process the data into the format we need
        $processedData = [];
        
        foreach ($data['data'] as $tank) {
            // Skip tanks without proper marks data
            if (!isset($tank['marks'])) continue;
            
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
            $jsonData = json_encode([
                'lastUpdated' => time(),
                'data' => $processedData
            ]);
            
            file_put_contents($dataFile, $jsonData);
            logActivity("Successfully updated data file with " . count($processedData) . " tanks");
            
            $response['data'] = $processedData;
            $response['lastUpdated'] = time();
            $response['message'] = 'Data updated successfully';
        } else {
            throw new Exception('No valid tank data found');
        }
        
    } catch (Exception $e) {
        logActivity("Error: " . $e->getMessage());
        
        // If we have a data file, use that instead
        if (file_exists($dataFile)) {
            logActivity("Using existing data file as fallback");
            $jsonData = file_get_contents($dataFile);
            $existingData = json_decode($jsonData, true);
            
            if ($existingData && isset($existingData['data'])) {
                $response['data'] = $existingData['data'];
                $response['lastUpdated'] = $existingData['lastUpdated'];
                $response['message'] = 'Using cached data (update failed)';
            } else {
                $response['success'] = false;
                $response['message'] = 'Failed to load cached data';
            }
        } else {
            $response['success'] = false;
            $response['message'] = 'Failed to fetch data: ' . $e->getMessage();
        }
    }
} else {
    // Just return the cached data
    logActivity("Using cached data");
    $jsonData = file_get_contents($dataFile);
    $existingData = json_decode($jsonData, true);
    
    if ($existingData && isset($existingData['data'])) {
        $response['data'] = $existingData['data'];
        $response['lastUpdated'] = $existingData['lastUpdated'];
        $response['message'] = 'Using cached data';
    } else {
        $response['success'] = false;
        $response['message'] = 'Failed to load cached data';
    }
}

// Return JSON response
echo json_encode($response); 