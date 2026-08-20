# RepoMaster PHP + MySQL Backend

Migrated from the uploaded Spring Boot + PostgreSQL RepoMaster backend.

## Requirements

- PHP 8.1+
- MySQL 8+
- Composer
- Apache with mod_rewrite OR PHP built-in server

## 1. Install dependencies

```bash
composer install
```

## 2. Create MySQL database

Import:

```text
sql/schema.sql
```

Example:

```bash
mysql -u root -p < sql/schema.sql
```

## 3. Configure database

Default values are:

```text
host: 127.0.0.1
port: 3306
database: repomaster
user: root
password: empty
```

For a password, set environment variables:

```text
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=repomaster
DB_USER=root
DB_PASSWORD=your_password
```

## 4. Start locally

From the project root:

```bash
php -S 0.0.0.0:8080 index.php
```

API base URL:

```text
http://YOUR_PC_IP:8080/api/
```

For Android, update the Retrofit base URL from the Spring Boot port to the PHP server port.

## 5. Excel dependency

Excel upload/report APIs use PhpSpreadsheet:

```bash
composer require phpoffice/phpspreadsheet
```

The project already declares this dependency in composer.json.

## API compatibility

The migration keeps the existing RepoMaster endpoint paths wherever possible, including users, vehicles, yards, invoices, search history and reports.

## Important

Do not expose database passwords or production credentials in the project ZIP. Use environment variables in production.
