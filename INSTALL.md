# 📦 INSTALL.md

## MySQL + PHP CLI Setup Guide (Windows & Linux)

## 🧭 Overview

This guide installs:

- MySQL Server
- PHP CLI
- PHP MySQL extension

---

## 🐧 Linux (Debian / Ubuntu / Raspberry Pi)

### 1. Update system

```bash
sudo apt update && sudo apt upgrade -y
```

### 2. Install MySQL

```bash
sudo apt install mysql-server -y
```

Start & enable:

```bash
sudo systemctl start mysql
sudo systemctl enable mysql
```

Secure installation:

```bash
sudo mysql_secure_installation
```

### 3. Install PHP CLI

```bash
sudo apt install php-cli -y
```

Check:

```bash
php -v
```

### 4. Install PHP MySQL Extension

```bash
sudo apt install php-mysql -y
```

Verify:

```bash
php -m | grep mysqli
```

### 5. Test MySQL

```bash
sudo mysql -u root -p
SHOW DATABASES;
EXIT;
```

### 6. Test PHP + MySQL

Create file:

```bash
nano test.php
```

```php
<?php
$conn = new mysqli("localhost", "root", "YOUR_PASSWORD");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
echo "Connected successfully";
?>
```

Run:

```bash
php test.php
```

---

## 🪟 Windows

### Option A (Recommended): XAMPP (Easy Setup)

#### 1. Install XAMPP

Download: https://www.apachefriends.org/index.html

Install with:

- MySQL ✔
- PHP ✔

#### 2. Start Services

Open XAMPP Control Panel:

- Start **MySQL**

#### 3. PHP CLI Path Setup

Add to Environment Variables:

```
C:\xampp\php
```

Verify:

```bash
php -v
```

#### 4. Test MySQL

```bash
mysql -u root
```

---

### Option B: Manual Install (Advanced)

#### 1. Install MySQL

Download MySQL Installer:
https://dev.mysql.com/downloads/installer/

- Choose **Server**
- Set root password

#### 2. Install PHP (CLI)

Download PHP (Non Thread Safe ZIP):
https://windows.php.net/download/

Extract to:

```
C:\php
```

#### 3. Add PHP to PATH

Add:

```
C:\php
```

Verify:

```bash
php -v
```

#### 4. Enable MySQL Extension

Edit:

```
C:\php\php.ini
```

Uncomment:

```ini
extension=mysqli
extension=pdo_mysql
```

#### 5. Test PHP + MySQL

Create `test.php`:

```php
<?php
$conn = new mysqli("127.0.0.1", "root", "YOUR_PASSWORD");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
echo "Connected successfully";
?>
```

Run:

```bash
php test.php
```

---

## ⚠️ Common Issues

### MySQL root login fails (Linux)

```bash
sudo mysql
```

Then:

```sql
ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'yourpassword';
FLUSH PRIVILEGES;
```

### PHP MySQL extension missing

```bash
php -m | grep mysqli
```

If missing:

```bash
sudo apt install php-mysql
```

### Windows: `php` not recognized

- PATH not set correctly
- Restart terminal after setting PATH

---

## ✅ Final Checklist

- [ ] MySQL installed and running
- [ ] PHP CLI installed
- [ ] PHP MySQL extension enabled
- [ ] Test script connects successfully
