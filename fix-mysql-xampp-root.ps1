# Path to XAMPP mysql.exe
$mysqlExe = "C:\xampp\mysql\bin\mysql.exe"

# New root password
$newPassword = "root"

# phpMyAdmin config path
$configFile = "C:\xampp\phpMyAdmin\config.inc.php"

# Function to set root password safely
function Set-MariaDBRootPassword {
    param(
        [string]$mysqlPath,
        [string]$password
    )

    Write-Host "Setting MariaDB root password to: $password"

    # Try connecting with no password
    $exitCode = & $mysqlPath -u root -e "SELECT 1;" 2>$null
    if ($LASTEXITCODE -eq 0) {
        Write-Host "Connected with empty root password. Updating..."
        & $mysqlPath -u root -e "ALTER USER 'root'@'localhost' IDENTIFIED BY '$password'; FLUSH PRIVILEGES;"
    }
    else {
        Write-Host "Root already has a password. You may need to provide old password."
        # Optional: uncomment below if you know old password
        # $oldPassword = "oldpassword"
        # & $mysqlPath -u root -p$oldPassword -e "ALTER USER 'root'@'localhost' IDENTIFIED BY '$password'; FLUSH PRIVILEGES;"
    }

    Write-Host "MariaDB root password set."
}

# Function to update phpMyAdmin config
function Update-PhpMyAdminConfig {
    param(
        [string]$configPath,
        [string]$password
    )

    if (Test-Path $configPath) {
        Write-Host "Updating phpMyAdmin config..."
        (Get-Content $configPath) -replace "\$cfg\['Servers'\]\[\$i\]\['password'\]\s*=\s*'.*';", "\$cfg['Servers'][\$i]['password'] = '$password';" |
            Set-Content $configPath
        Write-Host "phpMyAdmin config updated."
    }
    else {
        Write-Warning "phpMyAdmin config not found at $configPath"
    }
}

# Run functions
Set-MariaDBRootPassword -mysqlPath $mysqlExe -password $newPassword
Update-PhpMyAdminConfig -configPath $configFile -password $newPassword

Write-Host "`nDone! Root password is now '$newPassword'. You can log in to phpMyAdmin at http://localhost/phpmyadmin"