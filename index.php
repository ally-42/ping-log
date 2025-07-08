<?php
/**
 * Service Monitor in PHP
 * Script to monitor service status and send alerts via webhook
 * 
 * Usage: php index.php
 */

// Global configurations
define('TIMEOUT', 30); // Timeout in seconds for cURL requests
define('USER_AGENT', 'SiteMonitor/1.0');

/**
 * Load environment variables from .env file
 */
function loadEnv($envFile = null) {
    if ($envFile === null) {
        $envFile = __DIR__ . '/.env';
    }

    if (!file_exists($envFile)) {
        die("Error: .env file not found at $envFile\n");
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $env = [];

    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            $env[$key] = $value;
        }
    }

    return $env;
}

/**
 * Extract monitor configurations from environment variables array
 */
function extractMonitorConfigs($env) {
    $monitors = [];
    $monitorCount = 0;
    
    // Count how many monitors exist
    foreach ($env as $key => $value) {
        if (preg_match('/^MONITOR_(\d+)_NAME$/', $key, $matches)) {
            $monitorCount = max($monitorCount, (int)$matches[1]);
        }
    }
    
    // Extract configurations for each monitor
    for ($i = 1; $i <= $monitorCount; $i++) {
        $nameKey = "MONITOR_{$i}_NAME";
        $urlKey = "MONITOR_{$i}_URL";
        $webhookKey = "MONITOR_{$i}_WEBHOOK";
        $monitorKey = "MONITOR_{$i}_KEY";
        
        if (isset($env[$nameKey]) && isset($env[$urlKey]) && 
            isset($env[$webhookKey]) && isset($env[$monitorKey])) {
            
            $monitors[] = [
                'name' => $env[$nameKey],
                'url' => $env[$urlKey],
                'webhook' => $env[$webhookKey],
                'monitor_key' => $env[$monitorKey]
            ];
        }
    }
    
    return $monitors;
}

/**
 * Check service status via cURL
 */
function checkServiceStatus($url) {
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $curlInfo = curl_getinfo($ch);
    $responseSize = isset($curlInfo['size_download']) ? $curlInfo['size_download'] : (is_string($response) ? strlen($response) : 0);
    
    curl_close($ch);
    
    return [
        'success' => $response !== false && in_array($httpCode, [200, 204, 301, 302]),
        'http_code' => $httpCode,
        'error' => $error,
        'response_time' => $curlInfo['total_time'] ?? 0,
        'url' => $url,
        'response_size' => $responseSize
    ];
}

/**
 * Send alert to Discord webhook
 */
function sendWebhookAlert($webhookUrl, $serviceName, $status, $details) {
    $timestamp = date('c');
    
    $embed = [
        'title' => $status === 'down' ? '🚨 Service Offline' : ($status === 'up' ? '✅ Service Online' : '✅ Service Online'),
        'description' => "**{$serviceName}** - {$details}",
        'color' => $status === 'down' ? 0xFF0000 : 0x00FF00,
        'timestamp' => $timestamp,
        'footer' => [
            'text' => 'Service Monitor'
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
            'User-Agent: ServiceMonitor/1.0'
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
 * Write log for a specific monitor
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
 * Get last status from log analysis and check if notification was recently sent
 */
function getLastStatusFromLog($monitorKey) {
    $logDir = "logs/{$monitorKey}";
    $logFile = $logDir . '/' . date('Y-m-d') . '.log';
    
    // Se não existe log hoje, verificar logs anteriores
    if (!file_exists($logFile)) {
        // Procurar o log mais recente
        $logFiles = glob($logDir . '/*.log');
        if (empty($logFiles)) {
            return ['status' => 'unknown', 'last_notification' => null];
        }
        
        // Ordenar por data de modificação (mais recente primeiro)
        usort($logFiles, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        $logFile = $logFiles[0];
    }
    
    // Ler as últimas linhas do log
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (empty($lines)) {
        return ['status' => 'unknown', 'last_notification' => null];
    }
    
    // Analisar as últimas 20 linhas para encontrar o último status e notificação
    $recentLines = array_slice($lines, -20);
    $lastStatus = 'unknown';
    $lastNotification = null;
    
    foreach (array_reverse($recentLines) as $line) {
        // Procurar por padrões que indiquem status
        if (strpos($line, 'Status: online') !== false) {
            $lastStatus = 'online';
        }
        if (strpos($line, 'Status: offline') !== false) {
            $lastStatus = 'offline';
        }
        // Verificar por padrões de ALERT (offline) e OK (online)
        if (strpos($line, '[ALERT]') !== false) {
            $lastStatus = 'offline';
        }
        if (strpos($line, '[OK]') !== false) {
            $lastStatus = 'online';
        }
        
        // Verificar se foi enviada notificação recentemente
        if (strpos($line, '[WEBHOOK]') !== false) {
            // Extrair timestamp da linha
            if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
                $lastNotification = $matches[1];
            }
            break; // Parar na primeira notificação encontrada (mais recente)
        }
    }
    
    return ['status' => $lastStatus, 'last_notification' => $lastNotification];
}

/**
 * Main monitoring function
 */
function monitorService($monitor) {
    echo "Checking: {$monitor['name']} ({$monitor['url']})... ";
    
    $status = checkServiceStatus($monitor['url']);
    
    // Adiciona debug: mostra código HTTP e erro, se houver
    echo "[HTTP: {$status['http_code']}] ";
    if ($status['error']) {
        echo "[cURL error: {$status['error']}] ";
    }

    // Obter IP de destino
    $host = parse_url($monitor['url'], PHP_URL_HOST);
    $ip = gethostbyname($host);

    // User-Agent usado
    $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36';

    // Tipo de requisição
    $requestType = 'GET';

    // Tamanho da resposta
    $responseSize = isset($status['response_size']) ? $status['response_size'] : '-';

    // Obter último status através da análise do log
    $logDir = "logs/{$monitor['monitor_key']}";
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    $lastStatusInfo = getLastStatusFromLog($monitor['monitor_key']);

    if ($status['success']) {
        $message = "[OK] {$monitor['name']} | URL: {$monitor['url']} | HTTP: {$status['http_code']} | Tempo: " . round($status['response_time'], 2) . "s | User-Agent: {$userAgent} | IP: {$ip} | Tamanho: {$responseSize} bytes | Tipo: {$requestType} | Status: online";
        echo "✅ OK\n";
        writeLog($monitor['monitor_key'], $message);
        // Se estava offline antes, notificar recuperação
        if ($lastStatusInfo['status'] === 'offline') {
            $webhookMessage = "Service {$monitor['name']} está online novamente. HTTP {$status['http_code']}";
            $webhookSent = sendWebhookAlert($monitor['webhook'], $monitor['name'], 'up', $webhookMessage);
            if ($webhookSent) {
                writeLog($monitor['monitor_key'], "[WEBHOOK] Recovery alert sent successfully");
            } else {
                writeLog($monitor['monitor_key'], "[WEBHOOK] Error sending recovery alert");
            }
        }
    } else {
        $errorDetails = $status['error'] ? "Erro: {$status['error']}" : "HTTP {$status['http_code']}";
        $message = "[ALERT] {$monitor['name']} | URL: {$monitor['url']} | HTTP: {$status['http_code']} | Tempo: " . round($status['response_time'], 2) . "s | User-Agent: {$userAgent} | IP: {$ip} | Tamanho: {$responseSize} bytes | Tipo: {$requestType} | Status: offline | {$errorDetails}";
        echo "❌ FAILED\n";
        writeLog($monitor['monitor_key'], $message);
        
        // Verificar se deve enviar notificação (evitar spam)
        $shouldSendNotification = true;
        
        // Se já estava offline, verificar se a última notificação foi há mais de 30 minutos
        if ($lastStatusInfo['status'] === 'offline' && $lastStatusInfo['last_notification']) {
            $lastNotificationTime = strtotime($lastStatusInfo['last_notification']);
            $currentTime = time();
            $timeDiff = $currentTime - $lastNotificationTime;
            
            // Só enviar notificação se passou mais de 30 minutos desde a última
            if ($timeDiff < 1800) { // 30 minutos = 1800 segundos
                $shouldSendNotification = false;
                writeLog($monitor['monitor_key'], "[WEBHOOK] Skipping notification - last alert was " . round($timeDiff / 60, 1) . " minutes ago");
            }
        }
        
        if ($shouldSendNotification) {
            $webhookMessage = "Service {$monitor['name']} is offline. {$errorDetails}";
            $webhookSent = sendWebhookAlert($monitor['webhook'], $monitor['name'], 'down', $webhookMessage);
            if ($webhookSent) {
                writeLog($monitor['monitor_key'], "[WEBHOOK] Alert sent successfully");
            } else {
                writeLog($monitor['monitor_key'], "[WEBHOOK] Error sending alert");
            }
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
    $monitors = extractMonitorConfigs($env);
    
    if (empty($monitors)) {
        die("Error: No monitors configured in .env file!\n");
    }
    
    echo "Configured monitors: " . count($monitors) . "\n\n";
    
    // Monitor each service
    foreach ($monitors as $monitor) {
        monitorService($monitor);
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