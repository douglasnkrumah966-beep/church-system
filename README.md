# Church Management System

A complete, mobile-friendly management system for a Roman Catholic parish station.

## Features

- Multi-role login (sysadmin, admin, secretary, finance, catechist, member)
- Member registration with photos and flexible date-of-birth
- Sacrament records (Baptism, Communion, Confirmation, Marriage)
- Day Born Groups (Sunday Born through Saturday Born)
- Attendance tracking
- Finance — collections, contributions, expenses, cashbook
- Funeral contribution tracking with WhatsApp thank-you
- WhatsApp groups and bulk messaging
- Backup and restore
- Maintenance mode
- Reports and receipts

## Requirements

- XAMPP (Apache + MySQL)
- PHP 7.4 or later
- Modern browser (Firefox recommended for localhost)

## Installation

1. Copy the project to `C:\xampp\htdocs\chruch_system\`
2. Create a MySQL database named `church_db`
3. Import `database.sql` in phpMyAdmin
4. Copy `config/database.example.php` to `config/database.php`
5. Set your database credentials
6. Create folder `uploads/members/`
7. Open `http://localhost/chruch_system/login.php`

## Default Login

| Role | Username | Password |
|------|----------|----------|
| Sysadmin | admin | password@123 |
| Secretary | secretary | password@123 |
| Finance | finance | password@123 |
| Catechist | catechist | password@123 |
| Member | (phone number) | password@123 |

**Change all default passwords after first login.**

## Technology

- PHP 8
- MySQL / MariaDB
- HTML5, CSS3, vanilla JavaScript
- No external frameworks
