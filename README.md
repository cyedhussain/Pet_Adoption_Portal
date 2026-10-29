# Pet Adoption Portal

A pet adoption portal that connects rescued animals with loving homes. Visitors browse pets across shelter branches, adopters apply and follow their application step by step, and shelter staff manage everything from an admin dashboard.

Built with PHP and MySQL/MariaDB, styled with Bootstrap 5.

## Features

### Visitors
- Browse pets by category (Cats, Dogs, Rabbits, …) and filter by branch, age, gender, size and vaccination
- View detailed pet profiles
- Read About Us and contact information for each shelter branch
- Sign up, log in, and reset a forgotten password by email

### Adopters
- Submit an adoption application for any available pet
- Track each application through its stages: **Under review → Approved, ready for pickup → Adopted** (or Not approved / Cancelled)
- Cancel a pending request
- Save favourite pets
- Chat one-to-one with the shelter team
- Join community group chats created by the shelter

### Shelter staff (admin)
- Dashboard overview
- Add, edit and remove pets, categories and branch locations, with image uploads
- Review adoption requests: approve or reject with a reason, then mark approved pets as adopted
- See accepted and adopted records
- Reply to adopters in one-to-one chat and run community groups

### Security
- Passwords are hashed with `password_hash()`. Older plain-text accounts are upgraded automatically on their next login.
- CSRF protection on every form
- Prepared statements for all database queries
- Password-reset links are single-use, expire after 1 hour, and are stored hashed

## Tech stack

| Layer | Used |
|---|---|
| Backend | PHP 8.2 |
| Database | MySQL / MariaDB 10.4 |
| Frontend | Bootstrap 5.3, Bootstrap Icons (via CDN) |
| Email | PHPMailer (bundled) |
| Local server | XAMPP (Apache + MariaDB) |

## Project structure

```
.
├── pet_adoption.sql              # Database schema + sample data
├── Pet_Adoption_Portal_Report.pdf
└── pet_adoption_portal/
    ├── dbconnect.php             # Shared bootstrap: config, DB, sessions, security, helpers
    ├── index.php                 # Public home page
    ├── login.php, signup.php, forgetPassword.php, resetPassword.php
    ├── pets.php, petInfo.php, categories.php, sub_categories.php
    ├── aboutUs.php, contactUs.php
    ├── adopter/                  # Pages for logged-in adopters
    ├── admin/                    # Shelter staff dashboard
    ├── assets/style.css
    ├── img/                      # Site, category and pet images
    └── phpmailer/                # PHPMailer library
```

## Getting started

### 1. Requirements
Install [XAMPP](https://www.apachefriends.org/), or any setup with PHP 8.1+ and MySQL/MariaDB.

### 2. Copy the project
Put the `pet_adoption_portal` folder inside XAMPP's `htdocs` directory:

- Windows: `C:\xampp\htdocs\pet_adoption_portal`
- Linux: `/opt/lampp/htdocs/pet_adoption_portal`

The folder name matters. The app expects to be served from `/pet_adoption_portal`. To use a different path, change `APP_URL` in `dbconnect.php`.

### 3. Create the database
1. Start **Apache** and **MySQL** from the XAMPP control panel.
2. Open <http://localhost/phpmyadmin>.
3. Create a database named `pet_adoption`.
4. Select it, open **Import**, and import `pet_adoption.sql`.

### 4. Check the database settings
The defaults in `pet_adoption_portal/dbconnect.php` match a fresh XAMPP install:

```php
$servername = "localhost";
$username = "root";
$password = "";
$database = "pet_adoption";
```

### 5. Open the site
Go to <http://localhost/pet_adoption_portal/>.

## Default login

| Role | Email | Password |
|---|---|---|
| Admin | `manager@gmail.com` | `manager` |

Admins and adopters use the same login page; the portal works out which one you are from your email. Create adopter accounts through **Sign up**.

> Change the admin password before deploying anywhere public.

## Email (optional)

Password-reset emails are sent with PHPMailer. Until you configure it, the site works normally and simply skips sending emails.

To enable it, fill in the SMTP settings in `dbconnect.php`. Gmail needs an [App Password](https://myaccount.google.com/apppasswords), not your normal password:

```php
define('SMTP_USER', 'you@gmail.com');
define('SMTP_PASS', 'your-app-password');
define('SMTP_FROM', 'you@gmail.com');
```

Don't commit real credentials to the repository.

## Configuration

Other settings at the top of `dbconnect.php`:

| Setting | Default | Purpose |
|---|---|---|
| `APP_NAME` | `Animal Matter To Me` | Site name shown in the UI and emails |
| `APP_TIMEZONE` | `Asia/Karachi` | Timezone for PHP and MySQL |
| `MAX_UPLOAD_BYTES` | 5 MB | Maximum image upload size |
