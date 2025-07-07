<?php
/**
 * Site Monitor in PHP
 * Script to monitor site status and send alerts via webhook
 * 
 * Usage: php index.php
 */

// Global configurations
define('TIMEOUT', 30); // Timeout in seconds for cURL requests
define('USER_AGENT', 'SiteMonitor/1.0');

/**
 * Load environment variables from .env file
 */
function loadEnv($envFile = '.env') {
    if (!file_exists($envFile)) {
        die("Error: .env file not found!\n");
    }
    
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $env = [];
    
    foreach ($lines as $line) {
        // Ignore comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Check if line contains '='
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes if they exist
            if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                $value = substr($value, 1, -1);
            }
            
            $env[$key] = $value;
        }
    }
    
    return $env;
}

/**
 * Extract site configurations from environment variables array
 */
function extractSiteConfigs($env) {
    $sites = [];
    $siteCount = 0;
    
    // Count how many sites exist
    foreach ($env as $key => $value) {
        if (preg_match('/^MONITOR_(\d+)_NAME$/', $key, $matches)) {
            $siteCount = max($siteCount, (int)$matches[1]);
        }
    }
    
    // Extract configurations for each site
    for ($i = 1; $i <= $siteCount; $i++) {
        $nameKey = "MONITOR_{$i}_NAME";
        $urlKey = "MONITOR_{$i}_URL";
        $webhookKey = "MONITOR_{$i}_WEBHOOK";
        $monitorKey = "MONITOR_{$i}_KEY";
        
        if (isset($env[$nameKey]) && isset($env[$urlKey]) && 
            isset($env[$webhookKey]) && isset($env[$monitorKey])) {
            
            $sites[] = [
                'name' => $env[$nameKey],
                'url' => $env[$urlKey],
                'webhook' => $env[$webhookKey],
                'monitor_key' => $env[$monitorKey]
            ];
        }
    }
    
    return $sites;
}

/**
 * Check site status via cURL
 */
function checkSiteStatus($url) {
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERAGENT => USER_AGENT,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_NOBODY => true, // Only check status, don't download content
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $curlInfo = curl_getinfo($ch);
    
    curl_close($ch);
    
    return [
        'success' => $response !== false && $httpCode === 200,
        'http_code' => $httpCode,
        'error' => $error,
        'response_time' => $curlInfo['total_time'] ?? 0,
        'url' => $url
    ];
}

/**
 * Send alert to Discord webhook
 */
function sendWebhookAlert($webhookUrl, $siteName, $status, $details) {
    $timestamp = date('c');
    
    $embed = [
        'title' => $status === 'down' ? '🚨 Site Offline' : '✅ Site Online',
        'description' => "**{$siteName}** - {$details}",
        'color' => $status === 'down' ? 0xFF0000 : 0x00FF00,
        'timestamp' => $timestamp,
        'footer' => [
            'text' => 'Site Monitor'
        ]
    ];
    
    $data = [
        'embeds' => [$embed]
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $webhookUrl,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'User-Agent: SiteMonitor/1.0'
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode === 204; // Discord returns 204 on success
}

/**
 * Write log for a specific site
 */
function writeLog($monitorKey, $message) {
    $logDir = "logs/{$monitorKey}";
    $logFile = $logDir . '/' . date('Y-m-d') . '.log';
    
    // Create directory if it doesn't exist
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] {$message}" . PHP_EOL;
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Main monitoring function
 */
function monitorSite($site) {
    echo "Checking: {$site['name']} ({$site['url']})... ";
    
    $status = checkSiteStatus($site['url']);
    
    if ($status['success']) {
        $message = "[OK] {$site['name']} is online. HTTP {$status['http_code']} - Time: " . round($status['response_time'], 2) . "s";
        echo "✅ OK\n";
        writeLog($site['monitor_key'], $message);
    } else {
        $errorDetails = $status['error'] ? "Error: {$status['error']}" : "HTTP {$status['http_code']}";
        $message = "[ALERT] {$site['name']} is down. {$errorDetails}";
        echo "❌ FAILED\n";
        
        // Log the error
        writeLog($site['monitor_key'], $message);
        
        // Send webhook alert
        $webhookMessage = "Site {$site['name']} is offline. {$errorDetails}";
        $webhookSent = sendWebhookAlert($site['webhook'], $site['name'], 'down', $webhookMessage);
        
        if ($webhookSent) {
            writeLog($site['monitor_key'], "[WEBHOOK] Alert sent successfully");
        } else {
            writeLog($site['monitor_key'], "[WEBHOOK] Error sending alert");
        }
    }
}

/**
 * Main function
 */
function main() {
    echo "=== PHP Site Monitor ===\n";
    echo "Starting check: " . date('Y-m-d H:i:s') . "\n\n";
    
    // Load configurations
    $env = loadEnv();
    $sites = extractSiteConfigs($env);
    
    if (empty($sites)) {
        die("Error: No sites configured in .env file!\n");
    }
    
    echo "Configured sites: " . count($sites) . "\n\n";
    
    // Monitor each site
    foreach ($sites as $site) {
        monitorSite($site);
    }
    
    echo "\n=== Check completed ===\n";
    echo "Logs saved in: logs/{monitor_key}/YYYY-MM-DD.log\n";
}

// Execute script if called directly
if (php_sapi_name() === 'cli' || !isset($_SERVER['HTTP_HOST'])) {
    main();
} else {
    echo "This script must be executed via command line: php index.php";
}