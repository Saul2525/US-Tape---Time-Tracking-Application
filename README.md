# US Tape – Time Tracking Application

A lightweight employee time tracking system built with PHP and MySQL.

## 📌 Overview

This application allows employees to:

- Clock in and clock out
- Track total worked hours
- View approved hours
- View their profile information

Administrators (future feature) will be able to:

- Review and approve shifts
- Edit time entries
- Manage employees

---

## 🛠 Tech Stack

- PHP
- MySQL
- HTML/CSS
- Git / GitHub

---

## 🗄 Database Structure

### EMPLOYEES
- employee_id (PK)
- first_name
- last_name
- email
- pin (hashed)
- is_active

### WORK_TIMES
- work_time_id (PK)
- employee_id (FK)
- clock_in_time
- clock_out_time
- clock_in_lat
- clock_in_lng
- clock_out_lat
- clock_out_lng
- clock_in_ip
- clock_out_ip
- approved
- is_edited

---

## 🚀 Features Implemented

- Secure PIN verification (password_hash / password_verify)
- Clock in / Clock out logic
- Shift duration calculation
- Approved vs Total hours tracking
- Employee profile view
- GitHub integration

---

## 🔒 Security Notes

- Passwords are hashed
- Prepared statements prevent SQL injection
- Future updates will include session-based authentication and role-based access

---

## 📈 Planned Improvements

- Admin dashboard
- Shift approval buttons
- Session login system
- Payroll reporting
- UI styling improvements

---

## � Local Setup and Run Instructions

### 1. Start MySQL

Make sure MySQL is running locally and the app can connect using the credentials in `config.php`:

- Host: `127.0.0.1`
- Port: `3306`
- Database: `timeclock`
- User: `root`
- Password: empty string

If needed, create the database and import the schema from `db.sql`.

### 2. Start the PHP server

From the project folder:

```bash
cd /Users/gabrielkagwanja/Desktop/US\ Tape\ Time\ Tracking\ Application/timeclock
php -S 127.0.0.1:8000
```

Then open:

```text
http://127.0.0.1:8000
```

### 3. Manager login

The app includes a manager login page:

```text
http://127.0.0.1:8000/manager_login.php
```

### 4. Troubleshooting

- If the page does not load, make sure PHP is installed and available in your terminal.
- If the database connection fails, verify MySQL is running and the database name/user credentials match `config.php`.
- If the app cannot find files, run the server from the `timeclock` folder where `index.php` is located.

---

Some Temp Employe login numbers:
Employee1: 21850
Employee2: 09924
Employee3: 95425

## �👨‍💻 Author

Developed by Gabriel Kagwanja, Chris Anderson, and Saul Toribio
