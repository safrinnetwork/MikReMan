# MikReMan - MikroTik Remote Manager (RouterOS v7.2+ Only)

🚀 **MikroTik Remote Manager v1.69**

A comprehensive web-based management system for MikroTik RouterOS VPN services. Manage PPP users, monitor connections, and automate VPN server configuration through an intuitive web interface.

![VPN Remote Dashboard](https://img.shields.io/badge/Version-1.69-brightgreen) ![PHP](https://img.shields.io/badge/PHP-7.4+-blue) ![MikroTik](https://img.shields.io/badge/MikroTik-RouterOS%207+-red) ![License](https://img.shields.io/badge/License-MIT-yellow)

## ✨ Key Features

### 🔐 **Secure Authentication & Configuration**
- **AES-256 Encrypted Storage** - All sensitive data protected with military-grade encryption
- **Session Management** - 60-minute timeout with activity tracking
- **CSRF Protection** - Complete form security
- **Password Masking** - Smart password management with reveal/hide functionality

### 🌐 **VPN Server Management**
- **Multi-Protocol Support** - L2TP, PPTP, SSTP server control
- **Service Toggle** - Enable/disable VPN services with one click
- **Profile Management** - Automatic service profile creation
- **NAT Configuration** - One-click NAT masquerade rule setup

### 👥 **Comprehensive PPP User Management**
- **User CRUD Operations** - Create, read, update, delete PPP users
- **Bulk Operations** - Mass enable/disable or delete users
- **Real-time Status** - Live user connection monitoring
- **Smart IP Assignment** - Automatic IP address allocation
- **Password Generation** - Secure random username/password creation

### 🔌 **Advanced NAT & Port Management**
- **Automatic NAT Rules** - Smart port forwarding configuration
- **Port Collision Detection** - Prevents port conflicts (1000-9999 range)
- **Multi-Port Support** - Comma-separated port configurations
- **Custom NAT Modes** - Individual or grouped NAT rule creation
- **Rule Cleanup** - Automatic cleanup when users are removed

### 📊 **Real-time Monitoring Dashboard**
- **System Resources** - CPU, Memory, Storage monitoring
- **Connection Statistics** - Live PPP connection counts
- **Activity Logs** - Real-time PPP connection logs with auto-scroll
- **Router Information** - Hardware and software details
- **Auto-refresh** - Updates every 30 seconds

### 🛠 **Configuration Management**
- **Backup & Restore** - Complete system backup with Telegram integration
- **SSL/TLS Toggle** - HTTPS configuration management
- **Connection Testing** - Real-time MikroTik connectivity testing
- **Import/Export** - Configuration portability

### 📱 **Modern UI/UX**
- **Responsive Design** - Works on desktop, tablet, and mobile
- **Dark Theme** - Easy on the eyes for long sessions
- **Bootstrap 5** - Modern, professional interface
- **Real-time Alerts** - Success/error notifications
- **Search & Filter** - Find users and connections quickly

## 🚀 Deployment Guide

### 📋 **Requirements**
- **Web Server**: Apache/Nginx with PHP support
- **PHP**: Version 7.4 or higher
- **PHP Extensions**: `curl`, `json`, `openssl`, `session`
- **MikroTik Router**: RouterOS v7+ with REST API enabled
- **Storage**: Minimum 50MB disk space

### 🌐 **Shared Hosting Deployment**

#### **cPanel/Shared Hosting**
1. **Download & Extract**
   ```bash
   # Download the source code
   wget https://github.com/safrinnetwork/MikReMan/archive/main.zip
   unzip main.zip
   ```

2. **Upload Files**
   - Access your hosting File Manager or use FTP
   - Upload all files to your domain's public folder:
     - `public_html/` (for main domain)
     - `public_html/vpn/` (for subdirectory)

3. **Set Permissions**
   ```bash
   # Set proper permissions
   chmod 755 pages/ api/ assets/ includes/
   chmod 644 *.php *.html *.css *.js
   mkdir config/
   chmod 700 config/
   ```

4. **Access Setup**
   - Visit: `https://yourdomain.com/vpn_remote/`
   - Default login: `user1234` / `mostech`

#### **Subdomain Setup**
1. Create subdomain via cPanel: `vpn.yourdomain.com`
2. Upload files to subdomain's root folder
3. Access: `https://vpn.yourdomain.com/`

### ☁️ **Cloud Hosting Deployment**

#### **AWS/DigitalOcean/Vultr**
1. **Server Setup**
   ```bash
   # Ubuntu/Debian
   sudo apt update
   sudo apt install apache2 php php-curl php-json php-openssl
   sudo systemctl enable apache2
   
   # CentOS/RHEL
   sudo yum install httpd php php-curl php-json php-openssl
   sudo systemctl enable httpd
   ```

2. **Deploy Application**
   ```bash
   cd /var/www/html/
   sudo git clone https://github.com/your-repo/vpn-remote.git
   sudo chown -R www-data:www-data vpn-remote/
   sudo chmod 700 vpn-remote/config/
   ```

3. **Apache Virtual Host** (Optional)
   ```apache
   <VirtualHost *:80>
       ServerName vpn.yourdomain.com
       DocumentRoot /var/www/html/vpn-remote
       <Directory /var/www/html/vpn-remote>
           AllowOverride All
           Require all granted
       </Directory>
   </VirtualHost>
   ```

### 🔒 **SSL/HTTPS Setup**

#### **Let's Encrypt (Recommended)**
```bash
# Install Certbot
sudo apt install certbot python3-certbot-apache

# Get SSL Certificate
sudo certbot --apache -d yourdomain.com -d vpn.yourdomain.com

# Auto-renewal
sudo crontab -e
# Add: 0 12 * * * /usr/bin/certbot renew --quiet
```

#### **Cloudflare SSL**
1. Add your domain to Cloudflare
2. Set SSL/TLS mode to "Full (Strict)"
3. Enable "Always Use HTTPS"

### 🐳 **Docker Deployment**

Create `docker-compose.yml`:
```yaml
version: '3.8'
services:
  vpn-remote:
    image: php:8.1-apache
    container_name: vpn-remote
    ports:
      - "8080:80"
    volumes:
      - ./:/var/www/html/
      - ./config:/var/www/html/config:rw
    environment:
      - APACHE_DOCUMENT_ROOT=/var/www/html
    restart: unless-stopped
```

Deploy:
```bash
docker-compose up -d
```

## ⚙️ **Initial Configuration**

### 1. **First Login**
- URL: `https://yourdomain.com/vpn_remote/`
- Username: `user1234`
- Password: `mostech`
- **⚠️ Change default credentials immediately!**

### 2. **MikroTik Router Setup**
Enable REST API on your MikroTik router:
```mikrotik
/ip service
set www-ssl disabled=no port=443
/user group
set full policy=local,telnet,ssh,ftp,reboot,read,write,policy,test,winbox,password,web,sniff,sensitive,api,romon,rest-api
```

### 3. **Configure Connection**
1. Go to **Admin Panel**
2. Fill **MikroTik Configuration**:
   - **Host**: Your router's IP/domain
   - **Username**: MikroTik username
   - **Password**: MikroTik password  
   - **Port**: 443 (for HTTPS) or 80 (for HTTP)
   - **SSL**: Enable for HTTPS

3. Click **Test Connection** to verify

### 4. **Service Setup**
1. **Create Service Profiles**: Click L2TP/PPTP/SSTP Profile buttons
2. **Enable VPN Services**: Toggle L2TP/PPTP/SSTP servers
3. **Setup NAT**: Click "NAT Masquerade" for internet access

## 📚 **Usage Guide**

### 👤 **Managing PPP Users**

#### **Create New User**
1. Go to **PPP Users** page
2. Click **"Add User"**
3. Fill details or use generators:
   - **Username**: Manual or click 🔄 for random
   - **Password**: Manual or click 🔄 for secure password
   - **Service**: L2TP/PPTP/SSTP/Any
   - **Ports**: Default (8291,8728) or custom comma-separated
4. Enable **"Create NAT Rule"** for port forwarding
5. Enable **"Custom Port"** for individual NAT rules per port

#### **Bulk Operations**
1. Select users with checkboxes
2. Use bulk actions:
   - **Enable/Disable**: Toggle multiple users
   - **Delete**: Remove multiple users

#### **User Details**
Click **"View Details"** to see:
- Complete user information
- Port forwarding configuration
- MikroTik client setup commands
- Copy-to-clipboard functionality

### 📊 **Monitoring Dashboard**
- **Real-time Updates**: Data refreshes every 30 seconds
- **System Health**: Monitor CPU, Memory, Storage
- **Connection Stats**: See active PPP connections
- **Live Logs**: Watch connection activity in real-time

### 🔧 **Admin Panel Features**
- **Test Connections**: Verify MikroTik connectivity
- **Backup Config**: Download complete system backup
- **Service Control**: Enable/disable VPN servers
- **Profile Management**: Create service-specific profiles

## 🔧 **Troubleshooting**

### 🚫 **Common Issues**

#### **"Cannot connect to MikroTik"**
- ✅ Check router IP/domain
- ✅ Verify REST API is enabled
- ✅ Confirm username/password
- ✅ Test port accessibility (443/80)
- ✅ Check firewall rules

#### **"Session Expired"**
- ✅ Default timeout: 60 minutes
- ✅ Refresh page to extend session
- ✅ Avoid browser auto-refresh extensions

#### **"Permission Denied"**
- ✅ Ensure MikroTik user has full policy
- ✅ Check file permissions (config/ should be 700)
- ✅ Verify web server has write access

#### **SSL/HTTPS Issues**
- ✅ Use valid SSL certificate
- ✅ Check Cloudflare SSL mode
- ✅ Verify MikroTik SSL service

### 📁 **File Permissions**
```bash
# Correct permissions
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;
chmod 700 config/
chmod 600 config/*
```

### 🔍 **Debug Mode**
Add to `includes/config.php`:
```php
// Enable debug logging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
```

## 🔄 **Update Procedure**

### **Backup First**
```bash
# Backup current installation
tar -czf vpn-remote-backup-$(date +%Y%m%d).tar.gz vpn_remote/
```

### **Update Process**
1. **Download latest version**
2. **Backup config directory**:
   ```bash
   cp -r config/ config-backup/
   ```
3. **Replace files** (keep config/)
4. **Restore config**:
   ```bash
   cp -r config-backup/* config/
   ```
5. **Test functionality**

## 📋 **Technical Specifications**

### **Security Features**
- AES-256-CBC encryption for sensitive data
- CSRF token protection
- Session hijacking prevention
- Password hashing with salt
- SSL/TLS support
- Input validation and sanitization

### **Performance**
- Optimized API calls
- Efficient database operations
- Minified assets
- Caching where appropriate
- Responsive AJAX interactions

### **Compatibility**
- **PHP**: 7.4, 8.0, 8.1, 8.2
- **MikroTik RouterOS**: v7.0+
- **Browsers**: Chrome, Firefox, Safari, Edge
- **Mobile**: iOS Safari, Android Chrome

### **File Structure**
```
vpn_remote/
├── index.php              # Login page
├── pages/                 # Application pages
│   ├── admin.php         # Admin configuration
│   ├── dashboard.php     # Monitoring dashboard  
│   └── ppp.php           # PPP user management
├── api/                  # REST API endpoints
│   ├── config.php        # Configuration API
│   ├── mikrotik.php      # MikroTik API
│   └── telegram.php      # Telegram integration
├── includes/             # Backend logic
│   ├── auth.php          # Authentication
│   ├── config.php        # Configuration management
│   └── mikrotik.php      # MikroTik API client
├── assets/               # Frontend assets
│   ├── css/             # Stylesheets
│   └── js/              # JavaScript
├── config/               # Encrypted storage (auto-generated)
└── README.md            # This file
```

## 🆘 **Support & Community**

### **Getting Help**
- 📖 **Documentation**: Check this README first
- 🐛 **Bug Reports**: Create GitHub issue with details
- 💡 **Feature Requests**: Open GitHub discussion
- 📧 **Email Support**: [your-email@domain.com]

### **Contributing**
1. Fork the repository
2. Create feature branch
3. Make changes
4. Test thoroughly
5. Submit pull request

## 📝 **License**

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 **Acknowledgments**

- MikroTik for RouterOS and REST API
- Bootstrap team for the UI framework
- PHP community for excellent documentation
- All contributors and users
  
##  **Tutorial**

- https://youtu.be/X0zZetC3eVc
---

**Made with ❤️ for the networking community**

*VPN Remote - Simplifying MikroTik VPN Management*
