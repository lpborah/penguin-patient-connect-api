# Deployment Checklist - Monolog Integration

## ✅ Pre-Deployment Verification

### Local Testing
- [x] Monolog 2.10.0 installed via composer
- [x] AppLogger.php created and tested
- [x] All controllers updated with logging
- [x] Test script executed successfully
- [x] Log files created in logs/ directory
- [x] PHP syntax check passed for all files
- [x] Documentation created (LOGGING.md, MONOLOG-INTEGRATION.md)

### Files to Upload
```
src/AppLogger.php (NEW)
src/Controllers/MessageController.php (MODIFIED)
src/Controllers/ResourceController.php (MODIFIED)
src/Controllers/ClientController.php (MODIFIED)
composer.json (MODIFIED)
composer.lock (MODIFIED)
LOGGING.md (NEW)
MONOLOG-INTEGRATION.md (NEW)
logs/.gitignore (NEW)
vendor/monolog/ (NEW - entire directory)
```

## 🚀 Deployment Steps

### 1. Backup Current Production
```bash
# On server
cd /var/www/html/api
cp -r . ../api-backup-$(date +%Y%m%d-%H%M%S)
```

### 2. Upload Files via WinSCP
- Connect to server via SFTP
- Upload all modified files
- Upload new AppLogger.php
- Upload new vendor/monolog directory
- Upload documentation files

### 3. Create Logs Directory with Permissions
```bash
# On server
cd /var/www/html/api
sudo mkdir -p logs
sudo chown -R www-data:www-data logs
sudo chmod -R 755 logs
```

### 4. Verify Composer Dependencies
```bash
# On server (optional, but recommended)
cd /var/www/html/api
composer install --no-dev --optimize-autoloader
```

### 5. Test API Endpoints
```bash
# Test a simple endpoint to trigger logging
curl -X GET "https://your-domain.com/api/getMessages"

# Check if log file was created
ls -la logs/

# View recent logs
tail -f logs/app-$(date +%Y-%m-%d).log
```

## 📝 Post-Deployment Verification

### Check Log Files
```bash
# Verify logs directory exists and is writable
ls -la logs/
# Expected: drwxr-xr-x www-data www-data

# Check log file content
cat logs/app-$(date +%Y-%m-%d).log
# Expected: Log entries with timestamps
```

### Test Each Endpoint
1. **Messages API**
   - GET /getMessages → Should log "Fetching all messages"
   - POST /saveMessage → Should log "New message submission"
   
2. **Resources API**
   - GET /getResources → Should log "Fetching all resources"
   - POST /saveResource → Should log "New resource submission"
   
3. **Clients API**
   - GET /getClients → Should log "Fetching all clients"
   - POST /importClients → Should log "Client CSV import initiated"

### Monitor for Errors
```bash
# Watch logs in real-time
tail -f logs/app-$(date +%Y-%m-%d).log

# Check for ERROR entries
grep "ERROR" logs/app-$(date +%Y-%m-%d).log

# Check Apache error logs if API fails
tail -f /var/log/apache2/error.log
```

## 🔧 Troubleshooting

### Issue: Logs directory not writable
```bash
sudo chown -R www-data:www-data /var/www/html/api/logs
sudo chmod -R 755 /var/www/html/api/logs
```

### Issue: Log files not being created
```bash
# Check PHP error log
tail -f /var/log/apache2/error.log

# Verify AppLogger.php exists
ls -la /var/www/html/api/src/AppLogger.php

# Check permissions on src directory
ls -la /var/www/html/api/src/
```

### Issue: Class not found errors
```bash
# Regenerate autoload files
cd /var/www/html/api
composer dump-autoload
```

## 📊 Success Criteria

- [ ] API responds to requests normally
- [ ] Log files are created in logs/ directory
- [ ] Log entries contain timestamps and proper formatting
- [ ] INFO logs appear for successful operations
- [ ] ERROR logs appear for failures (test with invalid data)
- [ ] Log files rotate daily (verify after 24 hours)
- [ ] No new PHP errors in Apache error log

## 🔄 Rollback Plan (if needed)

```bash
# If deployment fails, restore backup
cd /var/www/html
sudo rm -rf api
sudo mv api-backup-YYYYMMDD-HHMMSS api
sudo systemctl restart apache2
```

## 📞 Support Commands

### View Today's Logs
```bash
cat /var/www/html/api/logs/app-$(date +%Y-%m-%d).log
```

### View Last 50 Log Entries
```bash
tail -50 /var/www/html/api/logs/app-$(date +%Y-%m-%d).log
```

### Search for Specific Errors
```bash
grep -r "ERROR" /var/www/html/api/logs/
```

### Check Disk Space (logs directory)
```bash
du -sh /var/www/html/api/logs/
```

## 📚 Documentation

- **Logging Guide**: `/var/www/html/api/LOGGING.md`
- **Integration Summary**: `/var/www/html/api/MONOLOG-INTEGRATION.md`
- **Monolog Docs**: https://github.com/Seldaek/monolog

## ✨ Features Added

1. **Automatic Daily Rotation** - New log file created each day
2. **30-Day Retention** - Old logs automatically deleted after 30 days
3. **Contextual Logging** - All logs include relevant data (IDs, counts, errors)
4. **Multiple Log Levels** - INFO, WARNING, ERROR, DEBUG
5. **Exception Handling** - All database operations wrapped in try-catch
6. **CSV Import Tracking** - Track upload progress and failures

---

**Deployment Date**: _________________
**Deployed By**: _________________
**Verified By**: _________________
**Status**: [ ] Success [ ] Rolled Back [ ] Issues (describe):

_________________________________________________________________
