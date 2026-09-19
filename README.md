# 🩸 LifeDrop Backend API

[![Laravel Version](https://img.shields.io/badge/Laravel-13.x-FF2D20?style=for-the-badge&logo=laravel)](https://laravel.com)
[![PHP Version](https://img.shields.io/badge/PHP-8.3%2B-777BB4?style=for-the-badge&logo=php)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-green.svg?style=for-the-badge)](LICENSE)

**LifeDrop Backend API** is a RESTful backend service built with **Laravel 13** and **PHP 8.3** to power the LifeDrop Blood Donation platform. It facilitates real-time emergency blood requests, location-based donor search, automated blood group compatibility matching, blood bank inventory management, and role-based access control.

---

## 📌 Project Features

- 🔐 **Authentication & Authorization**: Token-based authentication using **Laravel Sanctum** with support for multiple user roles (`donor`, `requester`, `bloodbank`, `admin`).
- 🩸 **Blood Request System**: Create, view, update, and accept emergency blood requests with urgency levels and hospital coordinates.
- 🎯 **Automated Donor Matching**: Background job (`MatchDonorsJob`) automatically matches compatible donors based on ABO/Rh blood group rules and 12-week donation eligibility windows.
- 📍 **Nearby Donor Search**: Geospatial distance calculation to find available nearby donors around hospital coordinates.
- 🏥 **Blood Bank Inventory**: Dedicated endpoints for registered blood banks to monitor and update blood unit stock.
- 🛡️ **Admin User Management**: Admin controls to list, inspect, update, block, or delete user accounts.
- 📩 **Public Contact API**: Public inquiry endpoint with structured logging.
- 🧪 **Automated Testing**: Comprehensive test suite covering authentication, requests, donors, and donations.

---

## 🛠️ Tech Stack & Requirements

- **PHP**: `^8.3`
- **Framework**: Laravel `^13.8`
- **Authentication**: Laravel Sanctum `^4.0`
- **Database**: MySQL / PostgreSQL / SQLite
- **Dependency Managers**: Composer & NPM

---

## 🚀 Getting Started & Installation

Follow these steps to set up the project locally:

### 1. Clone the Repository
```bash
git clone git@github.com:Waleed-09/lifedrop-backend.git
cd lifedrop-backend
```

### 2. Install Dependencies
```bash
composer install
npm install
```

### 3. Environment Setup
Copy the example `.env` file and generate the application encryption key:
```bash
cp .env.example .env
php artisan key:generate
```

Configure your database credentials in the `.env` file:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=lifedrop
DB_USERNAME=root
DB_PASSWORD=
```

### 4. Database Setup & Seeders
Run database migrations and seed demo data:
```bash
php artisan migrate --seed
```

### 5. Start Development Server
Run the Laravel development server:
```bash
php artisan serve
```
Or start the full dev environment (server, queue, logs, vite) concurrently:
```bash
composer run dev
```

---

## 🧪 Running Tests

Execute the PHPUnit test suite:
```bash
php artisan test
# or
composer test
```

---

## 🛡️ Files Excluded from Git (`.gitignore`)

For security and repository efficiency, the following files and folders are **strictly ignored** and will **NOT** be uploaded to GitHub:

- ❌ **Environment & Credentials**: `.env`, `.env.local`, `.env.production`, `auth.json` (contains sensitive passwords, API keys, database credentials)
- ❌ **Dependencies**: `/vendor`, `/node_modules` (can be restored via `composer install` / `npm install`)
- ❌ **Storage & Logs**: `/storage/logs/*.log`, `/storage/framework/cache/*`, `/storage/framework/views/*`, `/storage/*.key`
- ❌ **Local Databases & Cache**: `*.sqlite`, `*.sqlite-journal`, `.phpunit.result.cache`
- ❌ **IDE & System Files**: `/.idea`, `/.vscode`, `/.cursor`, `/.codex`, `.DS_Store`, `Thumbs.db`

---

## 📡 API Endpoints Summary

Base URL: `/api/v1`

### 🔓 Public Endpoints
| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/api/v1/auth/signup` | Register new user account |
| `POST` | `/api/v1/auth/login` | Authenticate user & get token |
| `GET` | `/api/v1/requests` | List active emergency blood requests |
| `GET` | `/api/v1/requests/{id}` | Get specific blood request details |
| `POST` | `/api/v1/contact` | Submit contact us message |

### 🔒 Authenticated Endpoints (`auth:sanctum`)
| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/api/v1/auth/logout` | Revoke active access token |
| `GET` | `/api/v1/auth/me` | Fetch authenticated user profile |
| `PATCH` | `/api/v1/donors/me/availability` | Toggle donor availability status |
| `GET` | `/api/v1/donors/nearby` | Search nearby donors by location |
| `POST` | `/api/v1/requests` | Create new blood request |
| `PATCH` | `/api/v1/requests/{id}` | Update blood request details |
| `PATCH` | `/api/v1/requests/{id}/accept` | Accept a blood request |
| `GET` | `/api/v1/donations` | View donation history |
| `POST` | `/api/v1/donations` | Log new blood donation |

### 🏥 Blood Bank Endpoints (`role:bloodbank`)
| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/v1/bloodbanks/{bank}/inventory` | View blood bank inventory |
| `PATCH` | `/api/v1/bloodbanks/{bank}/inventory` | Update blood bank inventory stock |

### 👑 Admin Endpoints (`role:admin`)
| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/v1/admin/users` | List all registered users |
| `GET` | `/api/v1/admin/users/{id}` | Get user details |
| `PATCH` | `/api/v1/admin/users/{id}` | Update user profile |
| `PATCH` | `/api/v1/admin/users/{id}/block` | Block/unblock user account |
| `DELETE` | `/api/v1/admin/users/{id}` | Delete user account |

---

## 🔗 Repository Information

- **GitHub Repository**: [git@github.com:Waleed-09/lifedrop-backend.git](https://github.com/Waleed-09/lifedrop-backend)
- **Maintainer**: Waleed-09

---

## 📄 License

This project is licensed under the [MIT License](LICENSE).

