# 💰 FinTrack Pro

> A modern, secure, and intelligent Personal Finance Management System built with PHP, PostgreSQL, HTML, CSS, and JavaScript.

![Status](https://img.shields.io/badge/Status-Active-success)
![PHP](https://img.shields.io/badge/PHP-8.x-777BB4?logo=php)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-18-336791?logo=postgresql)
![License](https://img.shields.io/badge/License-MIT-blue)

---

## 📖 Overview

FinTrack Pro is a full-stack personal finance management application that helps users manage their financial life through secure authentication, income and expense tracking, budgeting, savings goals, investments, loans, bill reminders, notifications, and advanced financial analytics.

The application follows a clean **Controller → Service → Repository** architecture with **PostgreSQL** as the primary data source and a secure **PHP session-based authentication system**.

---

# ✨ Features

## 🔐 Authentication & Security

- Secure User Registration
- Email OTP Verification
- Session-based Authentication
- Password Hashing
- CSRF Protection
- Secure Route Protection
- IDOR Prevention
- Activity Logging

---

## 💵 Financial Management

### Income Management
- Add Income
- Edit Income
- Delete Income
- Category Management
- Search & Filtering

### Expense Management
- Expense Tracking
- Expense Categories
- Monthly Tracking
- Search & Filters

### Budget Management
- Monthly Budgets
- Budget Monitoring
- Budget Alerts

### Savings Goals
- Create Savings Goals
- Progress Tracking
- Completion Monitoring

### Investments
- Portfolio Tracking
- Investment Profit
- ROI Calculation
- Investment History

### Loan & EMI
- Loan Management
- EMI Tracking
- Outstanding Balance
- Repayment Monitoring

### Bill Reminders
- Upcoming Bills
- Due Date Notifications
- Reminder Management

### Notification Center
- Real-time Notifications
- Budget Alerts
- Bill Alerts
- Financial Updates

---

# 📊 Reporting & Analytics (Phase 7)

Current implementation includes:

- Centralized Reporting Engine
- PostgreSQL Server-side Aggregations
- Financial Analytics Service
- Income Analytics
- Expense Analytics
- Savings Analytics
- Investment Analytics
- Loan Analytics
- Cash Flow Analysis
- Net Worth Calculation
- Debt Ratio Calculation
- Savings Rate Calculation
- Financial Health Metrics
- REST Reporting APIs

Upcoming:

- Interactive Charts
- PDF Reports
- Excel Export
- CSV Export
- Print Reports
- Dashboard Analytics

---

# 🏗 Architecture

```
Frontend
│
├── HTML5
├── CSS3
├── Vanilla JavaScript
│
▼
PHP Router
│
▼
Controllers
│
▼
Services
│
▼
Repositories
│
▼
PostgreSQL Database
```

---

# 🛠 Tech Stack

### Frontend

- HTML5
- CSS3
- Vanilla JavaScript

### Backend

- PHP 8.x
- PDO
- Session Authentication

### Database

- PostgreSQL 18

### Development Tools

- Git
- GitHub
- pgAdmin 4
- VS Code

---

# 📁 Project Structure

```
FinTrack-Pro/
│
├── app/
│   ├── Controllers/
│   ├── Services/
│   ├── Repositories/
│   ├── Models/
│   └── Middleware/
│
├── assets/
│
├── config/
│
├── database/
│
├── pages/
│
├── public/
│
├── routes/
│
├── tests/
│
└── README.md
```

---

# 🔒 Security

- Session Authentication
- OTP Verification
- Password Hashing
- Prepared Statements
- SQL Injection Protection
- XSS Protection
- CSRF Protection
- Route Authorization
- IDOR Protection

---

# 🚀 Current Progress

| Phase | Status |
|--------|--------|
| UI & Design System | ✅ Completed |
| Dashboard | ✅ Completed |
| Backend Foundation | ✅ Completed |
| Authentication & OTP | ✅ Completed |
| Investments & Loans | ✅ Completed |
| Notifications & Bills | ✅ Completed |
| Reports & Analytics (Foundation) | ✅ Completed |
| Charts & Data Visualization | 🚧 In Progress |
| Export System | 🚧 Planned |
| AI Financial Insights | 🔜 Planned |
| Production Deployment | 🔜 Planned |

---

# 🎯 Future Roadmap

- Interactive Financial Charts
- PDF Report Export
- Excel Report Export
- CSV Export
- Advanced Dashboard Analytics
- AI Spending Insights
- Financial Health Score
- Smart Budget Recommendations
- Expense Forecasting
- Docker Deployment
- Cloud Deployment

---

# 💻 Installation

Clone the repository

```bash
git clone https://github.com/yourusername/FinTrack-Pro.git
```

Navigate into the project

```bash
cd FinTrack-Pro
```

Start the PHP development server

```bash
php -S localhost:8000 -t public
```

Configure PostgreSQL credentials in your environment configuration.

Open

```
http://localhost:8000
```

---

# 📈 Project Status

🚧 Active Development

The project is currently under active development with new enterprise-level features being added in every phase.

---

# 👩‍💻 Author

**Subhalaxmi Sahoo**

B.Tech Student | Full Stack Developer

---

# ⭐ Support

If you like this project, consider giving it a ⭐ on GitHub!
