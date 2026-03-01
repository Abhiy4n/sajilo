# Sajilo — Business Tracking App

> **सजिलो** *(Nepali: "easy")* — A simple, powerful business management tool built for small retailers and shop owners to track inventory, customers, sales, warranties, and payments.

![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?style=flat-square&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-5.7+-4479A1?style=flat-square&logo=mysql&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-1DB954?style=flat-square)

---

## Features

- **Dashboard** — At-a-glance overview of products, warranties, revenue, and outstanding balances
- **Inventory Management** — Add, edit, delete products with categories, supplier info, cost/sell price, and stock tracking
- **Sales Recording** — Log sales with customer details, quantity, payment tracking, and warranty periods
- **Customer Management** — Full customer profiles with purchase history, balance tracking, and warranty status per sale
- **Reports & Analytics** — Interactive Chart.js charts — line, bar, and donut charts for revenue, top products, category breakdown, and warranty status
- **Warranty Tracking** — Auto-calculated expiry dates with expiring-soon alerts on the dashboard
- **Outstanding Balances** — Track partial payments and record follow-up payments per sale
- **Multi-user Ready** — All data is scoped per user account

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.2 |
| Database | MySQL / MariaDB |
| Frontend | Vanilla HTML, CSS, JavaScript |
| Charts | Chart.js 4.4 (CDN) |
| Fonts | Bricolage Grotesque + DM Sans (Google Fonts) |
| Server | Apache (XAMPP local / shared hosting production) |

---

## Project Structure
```
sajilo/
├── index.php                  # Landing page
├── login.php                  # Login & signup
├── dashboard.php              # Main dashboard
├── logout.php                 # Session destroy
│
├── includes/
│   ├── db.php                 # Database connection
│   ├── auth_check.php         # Session guard
│   └── sidebar.php            # Reusable sidebar component
│
└── pages/
    ├── inventory.php          # Product & category management
    ├── record_sale.php        # Record a new sale
    ├── customers.php          # Customer list & management
    ├── customer_detail.php    # Individual customer profile & history
    └── reports.php            # Analytics & charts
```

---

## Database Schema
```sql
CREATE DATABASE sajilo;
USE sajilo;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category_id INT,
    name VARCHAR(200) NOT NULL,
    model_no VARCHAR(100),
    brand VARCHAR(100),
    supplier_name VARCHAR(150),
    supplier_phone VARCHAR(20),
    cost_price DECIMAL(10,2) DEFAULT 0,
    selling_price DECIMAL(10,2) DEFAULT 0,
    quantity INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

CREATE TABLE customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150),
    phone VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    customer_id INT,
    product_id INT,
    product_name VARCHAR(200) NOT NULL,
    model_no VARCHAR(100),
    quantity INT DEFAULT 1,
    unit_price DECIMAL(10,2) DEFAULT 0,
    total_price DECIMAL(10,2) DEFAULT 0,
    amount_paid DECIMAL(10,2) DEFAULT 0,
    balance_due DECIMAL(10,2) DEFAULT 0,
    warranty_months INT DEFAULT 0,
    warranty_expiry DATE,
    notes TEXT,
    sale_date DATE NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
);
```

> Sales store product name and model as snapshots so historical records stay accurate even if products are later edited or deleted.

---

## Local Setup (XAMPP)

**1. Clone the repo**
```bash
git clone https://github.com/yourusername/sajilo.git
```

**2. Move to XAMPP htdocs**
```bash
mv sajilo/ /path/to/xampp/htdocs/sajilo
```

**3. Start Apache + MySQL** in XAMPP Control Panel

**4. Create the database**

Open `http://localhost/phpmyadmin`, create a database named `sajilo`, then run the SQL schema above.

**5. Configure DB connection** in `includes/db.php`:
```php
$conn = new mysqli('localhost', 'root', '', 'sajilo');
```

**6. Visit**
```
http://localhost/sajilo/
```

---

## Deployment (Shared Hosting)

1. Export local DB from phpMyAdmin as `.sql`
2. Upload all files to `public_html/sajilo/` via FTP
3. Create a MySQL database in your hosting control panel and import the SQL dump
4. Update `includes/db.php` with your hosting credentials

---

## Roadmap

- [ ] PDF invoice generation per sale
- [ ] Export sales to CSV/Excel
- [ ] SMS/email warranty expiry reminders
- [ ] Mobile responsive layout
- [ ] Dark mode

---

## License

MIT — free to use, modify, and distribute.

---

Built with ☕ and PHP. Feel free to open issues or pull requests!
