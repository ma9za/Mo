# Telegram AI Bot Manager

A professional, production-ready PHP control panel for managing multiple Telegram bots as AI agents using DeepSeek API. This application provides a secure, feature-rich dashboard for creating, configuring, and scheduling automated posts to Telegram channels.

## Features

- **Multi-Bot Management**: Create and manage multiple Telegram bots from a single dashboard
- **AI Content Generation**: Integrate with DeepSeek API for automatic content generation
- **Scheduled Posting**: Set up multiple posting times per day for each bot
- **Channel Verification**: Verify bot membership and permissions in Telegram channels
- **Webhook Support**: Receive and process Telegram updates in real-time
- **Comprehensive Logging**: Track all bot activities and errors
- **Secure Authentication**: Admin login with CSRF protection
- **Production-Ready Security**: Prepared statements, input validation, output escaping

## Project Structure

```
/
├── public/                    # Web-accessible directory
│   ├── index.php             # Entry point
│   ├── webhook.php           # Telegram webhook handler
│   ├── admin/                # Admin panel pages
│   │   ├── login.php         # Admin login
│   │   ├── dashboard.php     # Main dashboard
│   │   ├── bot_list.php      # List all bots
│   │   ├── bot_edit.php      # Create/edit bots
│   │   ├── settings.php      # Application settings
│   │   └── logout.php        # Logout handler
│   ├── cron/                 # Scheduled tasks
│   │   └── run.php           # Cron job runner
│   ├── lib/                  # Core libraries
│   │   ├── env.php           # Environment loader
│   │   ├── db.php            # Database manager
│   │   ├── auth.php          # Authentication
│   │   ├── telegram.php      # Telegram API
│   │   ├── deepseek.php      # DeepSeek API
│   │   ├── scheduler.php     # Scheduling logic
│   │   └── helpers.php       # Utility functions
│   └── assets/               # Static files
│       └── app.css           # Stylesheet
│
└── private/                  # Non-web-accessible directory
    ├── .env                  # Environment configuration
    ├── database.sqlite       # SQLite database
    └── logs/                 # Application logs
```

## Security Architecture

### Directory Separation

The application uses a strict public/private directory separation:

- **PUBLIC** (`/public/`): Only web-accessible files
  - Entry points (index.php, webhook.php)
  - Admin panel pages
  - Static assets (CSS, JS)
  - Cron runner

- **PRIVATE** (`/private/`): Never accessible via web
  - `.env` configuration file
  - SQLite database
  - Application logs
  - Sensitive data

### Security Features

- ✅ No secrets in PHP code
- ✅ Prepared statements for all database queries
- ✅ CSRF token protection on all forms
- ✅ Session-based authentication with IP validation
- ✅ Webhook secret verification
- ✅ Input validation and output escaping
- ✅ Secure password hashing with bcrypt

## Installation

### Prerequisites

- PHP 7.4 or higher
- SQLite3 support
- cURL extension
- Web server (Apache, Nginx, etc.)

### Step 1: Directory Setup

Create the directory structure:

```bash
# Create directories
mkdir -p /var/www/html/public
mkdir -p /var/www/private/logs

# Copy public files
cp -r public/* /var/www/html/public/

# Copy private files
cp -r private/* /var/www/private/
```

### Step 2: Configure Environment

Edit `/var/www/private/.env`:

```env
APP_ENV=production
APP_DEBUG=false

# Admin credentials (use password_hash() to generate)
ADMIN_USERNAME=turki
ADMIN_PASSWORD_HASH=$2y$10$YourHashedPasswordHere

# Application URL
BASE_URL=https://yourdomain.com

# Database path
DB_PATH=/var/www/private/database.sqlite

# DeepSeek API key
DEEPSEEK_API_KEY=your_api_key_here

# Telegram webhook base
TELEGRAM_WEBHOOK_BASE=https://yourdomain.com/webhook.php

# Timezone
TIMEZONE=Asia/Riyadh
```

### Step 3: Generate Admin Password Hash

Run this PHP command to generate a password hash:

```php
<?php
echo password_hash('your_password', PASSWORD_BCRYPT, ['cost' => 10]);
?>
```

Copy the output and paste it into `.env` as `ADMIN_PASSWORD_HASH`.

### Step 4: Set Permissions

```bash
# Set correct permissions
chmod 755 /var/www/html/public
chmod 755 /var/www/private
chmod 755 /var/www/private/logs
chmod 644 /var/www/private/.env
chmod 644 /var/www/private/database.sqlite
```

### Step 5: Configure Web Server

#### Apache

Create `.htaccess` in `/var/www/html/public/`:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php [QSA,L]
</IfModule>
```

#### Nginx

Add to server block:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

### Step 6: Set Up Cron Job

Add to crontab to run every minute:

```bash
* * * * * curl https://yourdomain.com/cron/run.php
```

Or using PHP directly:

```bash
* * * * * /usr/bin/php /var/www/html/public/cron/run.php
```

## Usage

### Accessing the Admin Panel

1. Navigate to `https://yourdomain.com/admin/login.php`
2. Login with your admin credentials
3. You'll be redirected to the dashboard

### Adding a Bot

1. Click **"Add Bot"** button
2. Fill in the bot details:
   - **Bot Name**: Descriptive name for the bot
   - **Telegram Bot Token**: Get from @BotFather
   - **Channel ID**: Use @channel_name or numeric ID
   - **General Prompt**: Instructions for AI content generation
   - **Schedule**: Comma-separated times (e.g., 09:00, 14:30, 20:00)
3. Click **"Create Bot"**

### Verifying Channel

1. Open the bot you created
2. Click **"Verify Channel"** button
3. The bot will check its membership and permissions
4. Status will update to "Verified"

### Setting Up Webhook

1. Open the bot
2. Click **"Set Webhook"** button
3. Telegram will be configured to send updates to your webhook

### Manual Post

1. Open the bot
2. Click **"Post Now"** button
3. AI will generate content and post to the channel

### Scheduled Posting

The cron job automatically:
1. Checks which bots have scheduled times
2. Generates content using DeepSeek
3. Posts to the channel
4. Logs the action

## Database Schema

### bots table

```sql
CREATE TABLE bots (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    token TEXT NOT NULL UNIQUE,
    webhook_secret TEXT,
    channel_input TEXT,
    channel_id TEXT,
    channel_title TEXT,
    is_verified INTEGER DEFAULT 0,
    is_enabled INTEGER DEFAULT 1,
    general_prompt TEXT,
    deepseek_key_override TEXT,
    model_override TEXT,
    schedule_json TEXT,
    last_post_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

### settings table

```sql
CREATE TABLE settings (
    key TEXT PRIMARY KEY,
    value TEXT
);
```

### logs table

```sql
CREATE TABLE logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    bot_id INTEGER NOT NULL,
    status TEXT NOT NULL,
    message TEXT,
    telegram_message_id TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bot_id) REFERENCES bots(id) ON DELETE CASCADE
);
```

## API Integration

### Telegram Bot API

The application uses the following Telegram API endpoints:

- `setWebhook`: Register webhook URL
- `getMe`: Get bot information
- `getChat`: Get channel information
- `getChatMember`: Check bot membership
- `sendMessage`: Post messages to channel

### DeepSeek API

The application integrates with DeepSeek for content generation:

- **Endpoint**: `https://api.deepseek.com/chat/completions`
- **Model**: `deepseek-chat` (configurable per bot)
- **System Prompt**: "You are an autonomous AI agent posting futuristic, controversial but safe Telegram posts."

## Configuration

### Environment Variables

| Variable | Description | Example |
|----------|-------------|---------|
| `APP_ENV` | Application environment | `production` |
| `APP_DEBUG` | Debug mode | `false` |
| `ADMIN_USERNAME` | Admin username | `turki` |
| `ADMIN_PASSWORD_HASH` | Hashed admin password | `$2y$10$...` |
| `BASE_URL` | Application URL | `https://yourdomain.com` |
| `DB_PATH` | Database file path | `/var/www/private/database.sqlite` |
| `DEEPSEEK_API_KEY` | DeepSeek API key | `sk-...` |
| `TELEGRAM_WEBHOOK_BASE` | Webhook base URL | `https://yourdomain.com/webhook.php` |
| `TIMEZONE` | Server timezone | `Asia/Riyadh` |

### Per-Bot Configuration

Each bot can override:

- **DeepSeek API Key**: Use a different key for this bot
- **Model**: Use a different DeepSeek model
- **General Prompt**: Custom AI instructions

## Troubleshooting

### Database Issues

If the database doesn't initialize:

```bash
# Check permissions
ls -la /var/www/private/database.sqlite

# Recreate database
rm /var/www/private/database.sqlite
# Reload the application to recreate
```

### Webhook Not Working

1. Verify `BASE_URL` in `.env` is correct
2. Check webhook URL is publicly accessible
3. Verify bot token is correct
4. Check logs in `/var/www/private/logs/`

### Cron Job Not Running

1. Verify cron is installed: `crontab -l`
2. Check cron logs: `grep CRON /var/log/syslog`
3. Test manually: `curl https://yourdomain.com/cron/run.php`

### AI Content Not Generating

1. Verify DeepSeek API key in `.env`
2. Test API connection in Settings page
3. Check logs for error messages
4. Verify API key has sufficient credits

## Performance Tips

1. **Database Optimization**: Add indexes for frequently queried columns
2. **Cron Frequency**: Adjust cron job frequency based on your needs
3. **Logging**: Archive old logs regularly to save space
4. **API Limits**: Monitor DeepSeek API usage and costs

## Backup & Recovery

### Backup Database

```bash
cp /var/www/private/database.sqlite /backup/database.sqlite.backup
```

### Backup Configuration

```bash
cp /var/www/private/.env /backup/.env.backup
```

### Restore from Backup

```bash
cp /backup/database.sqlite.backup /var/www/private/database.sqlite
cp /backup/.env.backup /var/www/private/.env
```

## Support & Documentation

For more information:

- [Telegram Bot API Documentation](https://core.telegram.org/bots/api)
- [DeepSeek API Documentation](https://platform.deepseek.com/api-docs)
- [PHP Documentation](https://www.php.net/manual/)

## License

This project is proprietary and confidential.

## Version

**Version**: 1.0.0  
**Last Updated**: 2024

---

**Built with ❤️ for professional Telegram bot management**
