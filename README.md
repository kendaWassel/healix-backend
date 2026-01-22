# Healix Backend 🩺⚙️

**Healix Backend** is a robust Laravel-based RESTful API that powers the Healix healthcare platform.  
It provides secure authentication, scalable data handling, and clean API endpoints for web and mobile clients.

---

## Overview

Healix Backend is designed to serve as the core backend system for a healthcare/telemedicine application.  
It follows modern Laravel best practices to ensure performance, security, and maintainability.

---
📮 Postman Collection

Healix Backend provides an official Postman Collection to help developers easily test and integrate with the API.

🌐 Online Documentation 

You can view and run the API directly in Postman using the public documentation:

👉 Postman API Documentation
https://documenter.getpostman.com/view/44027819/2sBXVfjrwZ

### 🔹 Download Postman Collection (JSON)
- [Healix Backend Postman Collection](postman/Healix-Backend.postman_collection.json)

## ✨ Features

- 🚀 Laravel REST API
- 🔐 Secure Authentication (Laravel Sanctum)
- 🧩 Clean MVC architecture
- 📦 Database migrations & seeders
- 🧪 Automated testing
- ⚙️ Environment-based configuration
- 📄 API-ready for mobile & web clients

---

## 🛠 Tech Stack

- **Framework:** Laravel
- **Language:** PHP 8+
- **Database:** MySQL 
- **Authentication:** Laravel Sanctum 
- **Testing:** PHPUnit 
- **Dependency Manager:** Composer

---

## 📦 Requirements

Before you begin, ensure you have the following installed:

- PHP >= 8.1
- Composer
- MySQL or PostgreSQL
- Node.js & npm (optional)
- Git

---

## 🚀 Installation & Setup

### 1️⃣ Clone the Repository

```bash
git clone https://github.com/kendaWassel/healix-backend.git
cd healix-backend
````

### 2️⃣ Install Dependencies

```bash
composer install
npm install
```

### 3️⃣ Environment Configuration

```bash
cp .env.example .env
```

Update `.env` with your database and app settings.

### 4️⃣ Generate App Key

```bash
php artisan key:generate
```

### 5️⃣ Run Migrations & Seeders

```bash
php artisan migrate --seed
```

### 6️⃣ Start Development Server

```bash
php artisan serve
```

The API will be available at:

```
http://127.0.0.1:8000
```

---

## 🔐 Authentication

This API uses **token-based authentication**.

Example request header:

```
Authorization: Bearer {your_token}
```


## 🧪 Testing

Run automated tests using:

```bash
php artisan test
```

or

```bash
vendor/bin/phpunit
```

---


## ⭐ Support

If you find this project useful, please consider giving it a ⭐ on GitHub.

==

