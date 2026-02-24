# Installation & Setup Guide

## Quick Start

### Prerequisites

- PHP 7.4+ with SQLite3 support
- Web server (Apache/Nginx)
- cURL extension
- SSH access to your server

### Installation Steps

#### 1. Clone Repository

```bash
cd /var/www
git clone https://github.com/ma9za/Mo.git
```

#### 2. Create Directory Structure

```bash
mkdir -p /var/www/private/logs
chmod 755 /var/www/private
chmod 755 /var/www/private/logs
```

#### 3. Configure Environment

Copy `.env` template and edit:

```bash
cp /var/www/Mo/private/.env /var/www/private/.env
nano /var/www/private/.env
```

Generate admin password hash:

```bash
php -r "echo password_hash('your_password', PASSWORD_BCRYPT, ['cost' => 10]);"
```

Update `.env` with the hash.

#### 4. Set Permissions

```bash
chmod 644 /var/www/private/.env
chmod 755 /var/www/Mo/public
chown -R www-data:www-data /var/www/Mo
chown -R www-data:www-data /var/www/private
```

#### 5. Configure Web Server

**Apache:**

Create `/var/www/Mo/public/.htaccess`:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php [QSA,L]
</IfModule>
```

**Nginx:**

Add to server block:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    fastcgi_pass unix:/run/php/php-fpm.sock;
    fastcgi_index index.php;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;
}
```

#### 6. Set Up Cron Job

Add to crontab:

```bash
* * * * * curl -s https://yourdomain.com/cron/run.php > /dev/null 2>&1
```

Or using PHP directly:

```bash
* * * * * /usr/bin/php /var/www/Mo/public/cron/run.php
```

#### 7. Access Application

Navigate to: `https://yourdomain.com/admin/login.php`

Login with credentials from `.env`

## Configuration Details

### .env Variables

| Variable | Purpose | Example |
|----------|---------|---------|
| `APP_ENV` | Environment mode | `production` |
| `APP_DEBUG` | Debug mode | `false` |
| `ADMIN_USERNAME` | Admin login | `turki` |
| `ADMIN_PASSWORD_HASH` | Hashed password | `$2y$10$...` |
| `BASE_URL` | Application URL | `https://domain.com` |
| `DB_PATH` | Database location | `/var/www/private/database.sqlite` |
| `DEEPSEEK_API_KEY` | AI API key | `sk-...` |
| `TELEGRAM_WEBHOOK_BASE` | Webhook URL | `https://domain.com/webhook.php` |
| `TIMEZONE` | Server timezone | `Asia/Riyadh` |

### Getting API Keys

**Telegram Bot Token:**
1. Message @BotFather on Telegram
2. Send `/newbot`
3. Follow instructions
4. Copy the token

**DeepSeek API Key:**
1. Visit https://platform.deepseek.com
2. Create account
3. Generate API key
4. Add to `.env`

## Troubleshooting

### Database Errors

```bash
rm /var/www/private/database.sqlite
# Reload app to recreate
```

### Permission Issues

```bash
sudo chown -R www-data:www-data /var/www/Mo
sudo chown -R www-data:www-data /var/www/private
sudo chmod -R 755 /var/www/Mo/public
sudo chmod -R 755 /var/www/private
```

### Webhook Not Working

1. Verify `BASE_URL` is correct
2. Test URL accessibility: `curl https://yourdomain.com/webhook.php`
3. Check bot token is valid
4. Review logs in `/var/www/private/logs/`

### Cron Not Executing

```bash
# Test manually
curl https://yourdomain.com/cron/run.php

# Check cron logs
grep CRON /var/log/syslog

# Verify cron is running
sudo service cron status
```

## Security Checklist

- [ ] `.env` is outside web root
- [ ] Database file is outside web root
- [ ] Permissions are set correctly (755 for dirs, 644 for files)
- [ ] HTTPS is enabled
- [ ] Admin password is strong
- [ ] Firewall blocks direct access to `/private/`
- [ ] Regular backups are scheduled

## Backup Strategy

### Daily Backup

```bash
#!/bin/bash
BACKUP_DIR="/backups/mo"
mkdir -p $BACKUP_DIR
cp /var/www/private/database.sqlite $BACKUP_DIR/db-$(date +%Y%m%d).sqlite
cp /var/www/private/.env $BACKUP_DIR/env-$(date +%Y%m%d).backup
```

### Restore from Backup

```bash
cp /backups/mo/db-20240224.sqlite /var/www/private/database.sqlite
cp /backups/mo/env-20240224.backup /var/www/private/.env
```

## Performance Optimization

### Database Maintenance

```bash
# Optimize SQLite database
sqlite3 /var/www/private/database.sqlite "VACUUM;"
```

### Log Rotation

```bash
# Archive old logs
find /var/www/private/logs/ -name "*.log" -mtime +30 -exec gzip {} \;
```

## Support

For issues or questions:
- Check logs: `/var/www/private/logs/`
- Review README.md
- Check GitHub issues

## Version Info

- **Version**: 1.0.0
- **PHP Requirement**: 7.4+
- **Database**: SQLite3

---

**Last Updated**: February 2024
