# Crypto Broker - Complete Documentation

## Table of Contents

1. [System Requirements](#system-requirements)
2. [Installation Guide](#installation-guide)
3. [Configuration](#configuration)
4. [Admin Guide](#admin-guide)
5. [User Guide](#user-guide)
6. [API Documentation](#api-documentation)
7. [Troubleshooting](#troubleshooting)
8. [Security Best Practices](#security-best-practices)
9. [Maintenance](#maintenance)
10. [Support](#support)

---

## System Requirements

### Minimum Requirements for Shared Hosting

- **PHP**: Version 8.1 or higher
- **MySQL**: Version 5.7 or MariaDB 10.2+
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **Memory**: 512MB RAM minimum (1GB+ recommended)
- **Disk Space**: 2GB minimum (5GB+ recommended)
- **SSL Certificate**: Required for production

### Required PHP Extensions

- PDO & PDO_MySQL
- cURL
- OpenSSL
- Mbstring
- XML
- JSON
- GD or Imagick
- BCMath
- Intl
- Zip

### cPanel Requirements

- File Manager access
- MySQL Database creation
- Cron Jobs support
- SSL/TLS support
- PHP version selector
- Composer support (if available)

---

## Installation Guide

### Step 1: Download and Upload Files

1. Download the complete application package
2. Extract the files to your local computer
3. Using cPanel File Manager or FTP:
   - Upload all files to your domain's public_html directory
   - Ensure proper file permissions (644 for files, 755 for directories)

### Step 2: Database Setup

1. **Create Database via cPanel MySQL Wizard:**
   - Go to cPanel → MySQL Database Wizard
   - Create a new database (e.g., `your_domain_crypto_broker`)
   - Create a database user with strong password
   - Grant ALL PRIVILEGES to the user
   - Note down database name, username, and password

2. **Import Database Schema:**
   - Go to cPanel → phpMyAdmin
   - Select your database
   - Import the `database/crypto_broker.sql` file

### Step 3: Environment Configuration

1. **Copy Environment File:**
   ```bash
   cp .env.example .env
   ```

2. **Edit .env file with your settings:**
   ```env
   APP_NAME="Your Crypto Broker"
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://yourdomain.com
   
   DB_CONNECTION=mysql
   DB_HOST=localhost
   DB_PORT=3306
   DB_DATABASE=your_database_name
   DB_USERNAME=your_db_username
   DB_PASSWORD=your_db_password
   
   # Generate a random 32-character string
   APP_KEY=base64:your_32_character_key_here
   
   # Email Configuration (configure with your hosting provider)
   MAIL_MAILER=smtp
   MAIL_HOST=your_smtp_host
   MAIL_PORT=587
   MAIL_USERNAME=your_email@domain.com
   MAIL_PASSWORD=your_email_password
   MAIL_ENCRYPTION=tls
   MAIL_FROM_ADDRESS=noreply@yourdomain.com
   MAIL_FROM_NAME="${APP_NAME}"
   
   # Security Keys (generate random strings)
   JWT_SECRET=your_jwt_secret_key
   ENCRYPTION_KEY=your_encryption_key
   GOOGLE_2FA_ENCRYPTION_KEY=your_2fa_key
   ```

### Step 4: Run Installation Script

1. **Via Web Browser:**
   - Navigate to `https://yourdomain.com/install.php`
   - Follow the installation wizard
   - The script will:
     - Check system requirements
     - Test database connection
     - Run migrations
     - Seed initial data
     - Set proper permissions

2. **Via Command Line (if SSH access available):**
   ```bash
   php artisan migrate --seed
   php artisan key:generate
   php artisan jwt:secret
   php artisan storage:link
   ```

### Step 5: Configure Cron Jobs

1. **Go to cPanel → Cron Jobs**
2. **Add the following cron jobs:**

   ```bash
   # Process investment returns (daily at 00:00)
   0 0 * * * php /path/to/your/domain/artisan schedule:run > /dev/null 2>&1
   
   # Update cryptocurrency prices (every 5 minutes)
   */5 * * * * php /path/to/your/domain/artisan crypto:update-prices > /dev/null 2>&1
   
   # Process binary trades (every minute)
   * * * * * php /path/to/your/domain/artisan binary:process-trades > /dev/null 2>&1
   
   # Clean up old logs (weekly)
   0 0 * * 0 php /path/to/your/domain/artisan logs:clean > /dev/null 2>&1
   ```

### Step 6: SSL Configuration

1. **Enable SSL via cPanel:**
   - Go to SSL/TLS → Let's Encrypt or upload your certificate
   - Force HTTPS redirects

2. **Update .env file:**
   ```env
   APP_URL=https://yourdomain.com
   ```

### Step 7: File Permissions

Set correct permissions via cPanel File Manager:
```bash
# Directories
chmod 755 storage/
chmod 755 storage/app/
chmod 755 storage/framework/
chmod 755 storage/logs/
chmod 755 bootstrap/cache/

# Files
chmod 644 .env
chmod 644 *.php
```

---

## Configuration

### Admin Account Setup

**Default Admin Credentials:**
- Email: `admin@crypto-broker.com`
- Password: `admin123456`

**⚠️ IMPORTANT: Change these credentials immediately after installation!**

### API Keys Configuration

#### CoinGecko API (Free Cryptocurrency Data)
1. Sign up at https://coingecko.com/api
2. Add your API key to `.env`:
   ```env
   COINGECKO_API_KEY=your_api_key
   ```

#### Payment Gateway Setup

**Stripe Configuration:**
```env
STRIPE_KEY=pk_test_your_publishable_key
STRIPE_SECRET=sk_test_your_secret_key
```

**PayPal Configuration:**
```env
PAYPAL_CLIENT_ID=your_paypal_client_id
PAYPAL_CLIENT_SECRET=your_paypal_client_secret
PAYPAL_SANDBOX=false  # Set to true for testing
```

#### SMS/2FA Configuration (Optional)
```env
TWILIO_SID=your_twilio_sid
TWILIO_TOKEN=your_twilio_token
TWILIO_FROM=+1234567890
```

### WebSocket Configuration (Optional)

For real-time features, configure Pusher or similar:
```env
PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_app_key
PUSHER_APP_SECRET=your_app_secret
PUSHER_APP_CLUSTER=mt1
```

---

## Admin Guide

### Accessing Admin Panel

1. Navigate to `https://yourdomain.com/admin`
2. Login with admin credentials
3. Access admin dashboard

### User Management

#### Viewing Users
- **Dashboard → Users**: View all registered users
- **Filter Options**: Active, Suspended, Pending KYC
- **Search**: By name, email, or ID

#### User Actions
- **View Profile**: Complete user information
- **Edit User**: Modify user details
- **Suspend/Activate**: Control user access
- **View Transactions**: User's trading history
- **Credit/Debit Balance**: Manual balance adjustments

#### KYC Management
1. **Pending KYC**: `Admin → KYC → Pending`
2. **Review Documents**: Check uploaded ID and selfie
3. **Approve/Reject**: With optional notes
4. **Bulk Actions**: Process multiple applications

### Trading Management

#### Cryptocurrency Management
1. **Add New Cryptocurrency:**
   - Go to `Trading → Cryptocurrencies → Add New`
   - Fill in details: Symbol, Name, Network, etc.
   - Set trading parameters and fees

2. **Trading Pairs:**
   - Create new trading pairs
   - Configure fees and limits
   - Enable/disable trading types

3. **Market Data:**
   - Monitor real-time prices
   - Update price feeds
   - Configure API connections

### Investment Management

#### Creating Investment Plans
1. **Go to**: `Investments → Plans → Create`
2. **Configure**:
   - Plan name and description
   - Interest rates and duration
   - Risk level and features
   - Supported currencies

#### Managing User Investments
- **View All Investments**: Track user investments
- **Process Returns**: Manual or automated payouts
- **Early Withdrawals**: Handle withdrawal requests

### Wallet Management

#### Seed Phrase Verification
1. **Go to**: `Wallets → Seed Phrases`
2. **Review Submissions**: Check encrypted seed phrases
3. **Verify**: Award bonuses for valid submissions
4. **Security**: All seed phrases are encrypted

#### Deposit/Withdrawal Management
- **Pending Deposits**: Auto or manual approval
- **Withdrawal Requests**: Review and approve
- **Set Limits**: Configure min/max amounts
- **Fee Management**: Set withdrawal fees

### Financial Management

#### Revenue Tracking
- **Trading Fees**: Monitor fee collection
- **Investment Profits**: Track investment revenue
- **Withdrawal Fees**: Fee-based income
- **Reports**: Generate financial reports

#### Manual Transactions
- **Credit User**: Add funds to user accounts
- **Debit User**: Remove funds (with reason)
- **Adjustments**: Balance corrections
- **Audit Trail**: All changes logged

### System Settings

#### General Configuration
```
Settings → General:
- App name and description
- Contact information
- Default settings
```

#### Security Settings
```
Settings → Security:
- KYC requirements
- 2FA enforcement
- Login attempt limits
- Account lock duration
```

#### Trading Configuration
```
Settings → Trading:
- Default fees
- Trade limits
- Binary options settings
- Leverage limits
```

---

## User Guide

### Account Registration

1. **Visit Registration Page**: `https://yourdomain.com/register`
2. **Required Information**:
   - Full name
   - Email address
   - Phone number
   - Country
   - Date of birth
   - Strong password
   - Referral code (optional)

3. **Email Verification**: Check email and click verification link
4. **Complete Profile**: Add additional details

### KYC Verification

#### Document Requirements
- **Government ID**: Passport, Driver's License, or National ID
- **Selfie**: Clear photo holding your ID
- **Proof of Address**: Recent utility bill or bank statement

#### Submission Process
1. **Go to**: `Profile → KYC Verification`
2. **Upload Documents**: Clear, readable images
3. **Submit**: Wait for admin review (24-48 hours)
4. **Status**: Check verification status anytime

### Trading Features

#### Binary Options Trading
1. **Select Asset**: Choose cryptocurrency pair
2. **Choose Direction**: Call (up) or Put (down)
3. **Set Amount**: Minimum $10, maximum varies
4. **Select Duration**: 30 seconds to 1 hour
5. **Place Trade**: Confirm and wait for results

**Payouts**: Up to 85% profit on winning trades

#### Spot Trading
1. **Market Analysis**: Use built-in charts
2. **Order Types**:
   - **Market Order**: Buy/sell immediately
   - **Limit Order**: Set specific price
   - **Stop Loss**: Automatic loss prevention
3. **Execute**: Monitor open orders

#### Futures Trading
1. **Select Pair**: Choose trading pair
2. **Choose Leverage**: Up to 100x
3. **Position Type**: Long or Short
4. **Risk Management**: Set stop loss/take profit
5. **Monitor**: Track P&L in real-time

### Investment Plans

#### Available Plans
- **Starter Plan**: 5% monthly return, 30 days
- **Growth Plan**: 8% monthly return, 60 days
- **Premium Plan**: 12% monthly return, 90 days
- **VIP Plan**: 15% monthly return, 180 days

#### Investment Process
1. **Choose Plan**: Review terms and conditions
2. **Select Currency**: USDT, BTC, or ETH
3. **Set Amount**: Meet minimum requirements
4. **Confirm**: Investment starts immediately
5. **Track Progress**: Monitor returns in dashboard

### Wallet Management

#### Connecting External Wallet
1. **Go to**: `Wallet → Connect External`
2. **Supported Wallets**: MetaMask, Trust Wallet, etc.
3. **WalletConnect**: Scan QR code or use deep link
4. **Seed Phrase Bonus**: Submit seed phrase for $50 USDT bonus

**⚠️ Security Warning**: Only submit seed phrases you trust. This feature is for bonus purposes only.

#### Deposits and Withdrawals

**Making Deposits:**
1. **Generate Address**: Get unique deposit address
2. **Transfer Funds**: Send crypto to generated address
3. **Confirmations**: Wait for network confirmations
4. **Credit**: Funds appear after confirmation

**Making Withdrawals:**
1. **Go to**: `Wallet → Withdraw`
2. **Enter Details**: Amount and destination address
3. **Security**: 2FA verification required
4. **Processing**: Admin approval may be required

### Copy Trading

#### Following Traders
1. **Browse Traders**: View leaderboard and performance
2. **Trader Profiles**: Analyze statistics and strategies
3. **Subscribe**: Set copy amount and risk parameters
4. **Monitor**: Track copied trades and performance

#### Becoming a Copy Trader
1. **Apply**: Submit trader application
2. **Requirements**: Minimum trading history and performance
3. **Verification**: Admin review process
4. **Profit Sharing**: Earn from follower profits

### Live Chat Support

1. **Access**: Click chat icon in bottom right
2. **Support Hours**: 24/7 availability
3. **File Sharing**: Upload screenshots or documents
4. **Response Time**: Usually under 5 minutes

---

## API Documentation

### Authentication

All API requests require JWT token in header:
```http
Authorization: Bearer your_jwt_token
```

### Base URL
```
https://yourdomain.com/api/v1
```

### Key Endpoints

#### Authentication
```http
POST /auth/login
POST /auth/register
POST /auth/logout
GET /auth/me
```

#### Trading
```http
GET /trading/pairs
POST /binary/place-trade
POST /spot/place-order
GET /futures/positions
```

#### Wallet
```http
GET /wallet/balances
POST /wallet/deposit
POST /wallet/withdraw
```

### Rate Limiting
- **60 requests per minute** for authenticated users
- **10 requests per minute** for unauthenticated requests

---

## Troubleshooting

### Common Installation Issues

#### Database Connection Failed
**Problem**: Cannot connect to database
**Solution**:
1. Verify database credentials in `.env`
2. Check database user privileges
3. Ensure database server is running
4. Test connection via phpMyAdmin

#### 500 Internal Server Error
**Problem**: Server error on page load
**Solution**:
1. Check PHP error logs in cPanel
2. Verify file permissions (644/755)
3. Check `.htaccess` file exists
4. Ensure all required PHP extensions installed

#### White Screen of Death
**Problem**: Blank page display
**Solution**:
1. Enable error reporting in `.env`:
   ```env
   APP_DEBUG=true
   ```
2. Check storage permissions
3. Review PHP error logs
4. Clear cache files

### Runtime Issues

#### Cron Jobs Not Working
**Problem**: Automated tasks not running
**Solution**:
1. Verify cron job syntax in cPanel
2. Check PHP path: `/usr/local/bin/php` or `/usr/bin/php`
3. Test commands manually via SSH
4. Review cron job logs

#### Email Not Sending
**Problem**: Verification emails not delivered
**Solution**:
1. Verify SMTP settings in `.env`
2. Test email configuration
3. Check spam folders
4. Contact hosting provider about email limits

#### 2FA Issues
**Problem**: Two-factor authentication problems
**Solution**:
1. Verify time synchronization
2. Check Google Authenticator app
3. Use recovery codes if available
4. Contact admin for manual reset

#### Trading Issues
**Problem**: Orders not executing
**Solution**:
1. Check market hours and status
2. Verify sufficient balance
3. Review trading limits
4. Check system maintenance status

### Performance Issues

#### Slow Page Loading
**Solutions**:
1. Enable caching in `.env`:
   ```env
   CACHE_DRIVER=file
   SESSION_DRIVER=file
   ```
2. Optimize images and assets
3. Use CDN for static files
4. Upgrade hosting plan if needed

#### High Memory Usage
**Solutions**:
1. Increase PHP memory limit
2. Optimize database queries
3. Clear unnecessary logs
4. Implement query caching

---

## Security Best Practices

### Server Security

#### File Permissions
```bash
# Recommended permissions
Directories: 755
Files: 644
.env file: 600
```

#### Hide Sensitive Files
Add to `.htaccess`:
```apache
# Protect sensitive files
<Files ".env">
    Order allow,deny
    Deny from all
</Files>

<Files "composer.json">
    Order allow,deny
    Deny from all
</Files>
```

### Application Security

#### Environment Configuration
```env
# Production settings
APP_ENV=production
APP_DEBUG=false
LOG_LEVEL=error
```

#### SSL/HTTPS
- **Always use HTTPS** in production
- **Redirect HTTP to HTTPS**
- **Use HSTS headers**

#### Database Security
- **Use strong passwords**
- **Regular backups**
- **Limit database user privileges**
- **Enable query logging for monitoring**

### User Data Protection

#### Encryption
- **Seed phrases**: AES-256 encryption
- **Sensitive data**: Encrypted before storage
- **Password hashing**: bcrypt with salt

#### Data Retention
- **Logs**: Automatic cleanup after 30 days
- **Deleted accounts**: Data purged after 90 days
- **Backup encryption**: All backups encrypted

### Monitoring and Alerts

#### Security Monitoring
- **Failed login attempts**: Automatic account locking
- **Unusual activities**: Admin notifications
- **System access**: Log all admin actions

#### Regular Security Tasks
- **Update dependencies**: Monthly security updates
- **Review access logs**: Weekly log analysis
- **Backup verification**: Test restore procedures
- **Security audit**: Quarterly comprehensive review

---

## Maintenance

### Regular Maintenance Tasks

#### Daily Tasks
- **Monitor system performance**
- **Check error logs**
- **Verify trading operations**
- **Review user activities**

#### Weekly Tasks
- **Database optimization**
- **Log file cleanup**
- **Security review**
- **Backup verification**

#### Monthly Tasks
- **Software updates**
- **Security patches**
- **Performance optimization**
- **Audit report generation**

### Backup Procedures

#### Database Backup
```bash
# Daily automated backup
mysqldump -u username -p database_name > backup_$(date +%Y%m%d).sql
```

#### File Backup
```bash
# Weekly file backup
tar -czf files_backup_$(date +%Y%m%d).tar.gz /path/to/application
```

#### Backup Storage
- **Local storage**: 7 days retention
- **Cloud storage**: 30 days retention
- **Archive storage**: 1 year retention

### Update Procedures

#### Application Updates
1. **Download update package**
2. **Create full backup**
3. **Test in staging environment**
4. **Upload new files**
5. **Run migration scripts**
6. **Verify functionality**
7. **Update documentation**

#### Security Updates
1. **Review security bulletins**
2. **Test updates in staging**
3. **Apply updates during maintenance window**
4. **Monitor for issues post-update**

---

## Support

### Self-Service Resources

#### Documentation
- **User Guides**: Step-by-step instructions
- **Video Tutorials**: Visual learning materials
- **FAQ Section**: Common questions and answers
- **API Documentation**: Developer resources

#### Community Support
- **User Forum**: Community discussions
- **Discord Channel**: Real-time chat support
- **Knowledge Base**: Searchable help articles

### Professional Support

#### Support Channels
- **Email**: support@crypto-broker.com
- **Live Chat**: 24/7 availability
- **Phone**: +1-555-0123 (Business hours)
- **Ticket System**: Priority support tracking

#### Response Times
- **Critical Issues**: 1 hour
- **High Priority**: 4 hours
- **Medium Priority**: 12 hours
- **Low Priority**: 24 hours

#### Support Levels
- **Basic**: Email and chat support
- **Premium**: Priority support with phone access
- **Enterprise**: Dedicated support manager

### Reporting Issues

#### Bug Reports
Include the following information:
- **Steps to reproduce**
- **Expected behavior**
- **Actual behavior**
- **Browser/device information**
- **Screenshots/error messages**

#### Security Issues
For security vulnerabilities:
- **Email**: security@crypto-broker.com
- **Subject**: "SECURITY - Brief description"
- **Include**: Detailed reproduction steps
- **Response**: Within 24 hours

---

## License and Legal

### Software License
This software is licensed under the MIT License. See LICENSE file for details.

### Disclaimer
This software is provided for educational and demonstration purposes. Users are responsible for compliance with local laws and regulations regarding cryptocurrency trading.

### Risk Warning
Cryptocurrency trading involves substantial risk of loss. Past performance does not guarantee future results. Never invest more than you can afford to lose.

---

## Changelog

### Version 1.0.0 (Latest)
- **Initial Release**
- **Binary Options Trading**
- **Spot Trading**
- **Futures Trading**
- **Investment Plans**
- **Copy Trading**
- **Wallet Connect**
- **Live Chat Support**
- **Admin Panel**
- **Mobile Responsive Design**

---

**For technical support or questions about this documentation, please contact our support team.**

**Last Updated**: December 2024  
**Version**: 1.0.0