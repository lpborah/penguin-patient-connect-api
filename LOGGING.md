# Logging Documentation

## Overview
The API uses Monolog for logging with automatic daily rotation. All logs are stored in the `logs/` directory with 30-day retention.

## Log Files
- **Location**: `logs/app-YYYY-MM-DD.log`
- **Rotation**: Daily (automatically creates new file each day)
- **Retention**: 30 days (older logs are automatically deleted)
- **Format**: `[YYYY-MM-DD HH:MM:SS] LEVEL: message {context}`

## Log Levels
1. **DEBUG**: Detailed debugging information
2. **INFO**: General informational messages (successful operations)
3. **WARNING**: Warning messages (validation failures, non-critical issues)
4. **ERROR**: Error messages (database errors, exceptions)

## Usage in Controllers

The `AppLogger` class provides static methods for logging:

```php
use App\AppLogger;

// Info log - successful operations
AppLogger::info('Message saved successfully', [
    'id' => $id,
    'email' => $email
]);

// Warning log - validation issues
AppLogger::warning('Validation failed', [
    'missing_fields' => $missing
]);

// Error log - exceptions and database errors
AppLogger::error('Database error', [
    'error' => $e->getMessage(),
    'query' => $query
]);

// Debug log - detailed debugging info
AppLogger::debug('Request data', [
    'params' => $params
]);
```

## What's Being Logged

### MessageController
- Message retrieval (count)
- Message submissions (id, name, email)
- Message status updates (id, status, affected rows)
- Email sending (to, from, subject)
- Validation failures
- Database errors
- PHPMailer errors

### ResourceController
- Resource retrieval (count)
- Resource CRUD operations (id, resourceName)
- Validation failures
- Database errors

### ClientController
- Client retrieval (count)
- Client CRUD operations (client_id, client_name)
- CSV imports (filename, inserted count, row errors)
- Soft deletes (status change to 0)
- Validation failures
- Database errors

## Testing Logging

Run the test script to verify logging is working:

```bash
php test-logging.php
```

Then check the log file:

```bash
cat logs/app-$(date +%Y-%m-%d).log
```

## Production Deployment

1. Ensure `logs/` directory exists and is writable by the web server:
   ```bash
   sudo mkdir -p /var/www/html/api/logs
   sudo chown -R www-data:www-data /var/www/html/api/logs
   sudo chmod -R 755 /var/www/html/api/logs
   ```

2. The `logs/.gitignore` file prevents log files from being committed to version control

3. Consider setting up log monitoring or aggregation tools to track errors in production

## Viewing Logs

### On Local Development (Windows/XAMPP)
```powershell
Get-Content logs\app-2025-12-24.log -Tail 50
```

### On Server (Linux)
```bash
tail -f logs/app-$(date +%Y-%m-%d).log
```

### View All Recent Errors
```bash
grep "ERROR" logs/app-$(date +%Y-%m-%d).log
```

### View Specific API Activity
```bash
grep "client_id" logs/app-$(date +%Y-%m-%d).log
```

## Log Context

All logs include contextual information:
- **Request IDs**: Track specific operations
- **User data**: Relevant fields (emails, IDs, names)
- **Error details**: Exception messages, stack traces
- **Counts**: Number of records affected

Example log entry:
```
[2025-12-24 08:07:44] INFO: Message saved successfully {"id":123,"name":"John Doe","email":"john@example.com"}
[2025-12-24 08:08:15] ERROR: Database error saving message {"error":"Connection timeout"}
```

## Benefits

1. **Debugging**: Quickly identify issues in production
2. **Audit Trail**: Track all API operations
3. **Performance**: Monitor which operations are being called
4. **Security**: Log suspicious activities
5. **Compliance**: Maintain records of data access

## Best Practices

1. **Info Logs**: Log successful operations with key identifiers
2. **Warning Logs**: Log validation failures and non-critical issues
3. **Error Logs**: Always log exceptions with full context
4. **Avoid Sensitive Data**: Don't log passwords, full credit cards, etc.
5. **Use Context**: Always include relevant data in the context array
6. **Be Concise**: Keep log messages clear and actionable
