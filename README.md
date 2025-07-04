# PHP Site Monitor

A simple and efficient PHP script to monitor the status of multiple sites and send alerts via Discord webhook.

## 🚀 Features

- ✅ Monitor multiple sites simultaneously
- ✅ HTTP status verification via cURL
- ✅ Automatic alerts via Discord webhook
- ✅ Organized logging system by site
- ✅ Configurable timeout for requests
- ✅ CLI or cronjob execution

## 📋 Requirements

- PHP 7.4 or higher
- cURL extension enabled
- Write permissions to create log directories

## ⚙️ Configuration

### 1. Create the `.env` file

Copy the example below and configure your sites:

```env
# Site 1
MONITOR_1_NAME="My Main Site"
MONITOR_1_URL="https://example.com"
MONITOR_1_WEBHOOK="https://discord.com/api/webhooks/your_webhook_here"
MONITOR_1_KEY="main_site"

# Site 2
MONITOR_2_NAME="Blog"
MONITOR_2_URL="https://blog.example.com"
MONITOR_2_WEBHOOK="https://discord.com/api/webhooks/another_webhook_here"
MONITOR_2_KEY="blog"

# Site 3
MONITOR_3_NAME="API"
MONITOR_3_URL="https://api.example.com"
MONITOR_3_WEBHOOK="https://discord.com/api/webhooks/api_webhook_here"
MONITOR_3_KEY="api"
```

### 2. Configure Discord Webhook

1. Access your Discord server
2. Go to Channel Settings → Integrations → Webhooks
3. Create a new webhook
4. Copy the webhook URL to the `.env` file

## 🎯 Usage

### Manual Execution

```bash
php index.php
```

### Cronjob Configuration

To run every 5 minutes:

```bash
*/5 * * * * /usr/bin/php /path/to/ping-log/index.php
```

To run every hour:

```bash
0 * * * * /usr/bin/php /path/to/ping-log/index.php
```

## 📁 Log Structure

Logs are organized as follows:

```
logs/
├── main_site/
│   ├── 2025-01-15.log
│   └── 2025-01-16.log
├── blog/
│   ├── 2025-01-15.log
│   └── 2025-01-16.log
└── api/
    ├── 2025-01-15.log
    └── 2025-01-16.log
```

### Log Example

```
[2025-01-15 14:30:01] [OK] My Main Site is online. HTTP 200 - Time: 0.45s
[2025-01-15 14:35:12] [ALERT] My Main Site is down. HTTP 502 - Bad Gateway
[2025-01-15 14:35:12] [WEBHOOK] Alert sent successfully
```

## 🔧 Advanced Configuration

### Request Timeout

Edit the `TIMEOUT` constant at the beginning of `index.php`:

```php
define('TIMEOUT', 30); // Timeout in seconds
```

### User Agent

Customize the User Agent for requests:

```php
define('USER_AGENT', 'SiteMonitor/1.0');
```

## 📊 Monitoring

The script checks:

- ✅ HTTP 200 status (success)
- ❌ Any other HTTP code (failure)
- ❌ Connection errors (timeout, DNS, etc.)
- ❌ SSL/TLS errors

## 🚨 Alerts

When a site goes down, the script:

1. Logs the error
2. Sends alert to Discord via webhook
3. Includes error details (HTTP code, error message)

## 📝 License

This project is under MIT license. See the `LICENSE` file for more details.
