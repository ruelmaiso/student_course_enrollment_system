# Student-Course Enrollment System

A comprehensive PHP-based enrollment management system with integrated payment processing, allowance tracking, and modern UI.

## Features

- **Student Management**: Add, edit, delete students with allowance tracking
- **Course Management**: Manage courses with fee information
- **Enrollment System**: Enroll students in courses with automatic payment processing
- **Payment Tracking**: Complete payment management with status indicators
- **Search & Pagination**: Built-in search and pagination on all pages
- **Allowance Logs**: Automatic tracking of allowance changes
- **Modern UI**: Responsive design with modals and toast notifications

## File Structure

```
/workspace/
├── index.php              # Main dashboard
├── students.php           # Student management with search & pagination
├── students_actions.php   # Student CRUD operations
├── courses.php            # Course management with search & pagination
├── courses_actions.php    # Course CRUD operations
├── enrollments.php        # Enrollment management with payment integration
├── enrollments_actions.php # Enrollment CRUD with payment processing
├── payments.php           # Payment management with search & pagination
├── payments_actions.php   # Payment CRUD operations
├── get_allowance_logs.php # AJAX endpoint for allowance history
├── db.php                 # Database connection
├── schema.sql             # Complete database schema
├── styles.css             # Enhanced styling
└── README.md              # This file
```

## Database Schema

The system uses MySQL with the following tables:
- `students` - Student information with allowance tracking
- `courses` - Course information with fees
- `enrollments` - Student-course relationships
- `payments` - Payment records
- `allowance_logs` - Automatic allowance change tracking

## Installation

1. Import `schema.sql` into your MySQL database
2. Update database credentials in `db.php`
3. Place files in your web server directory
4. Access `index.php` to start using the system

## Key Features

### Integrated Search & Pagination
- All main pages (students, courses, enrollments, payments) have built-in search
- Pagination with configurable page sizes (10, 25, 50 records)
- Search preserves pagination state

### Payment Integration
- Automatic payment processing during enrollment
- Allowance deduction when students have sufficient funds
- Partial payment handling for insufficient allowance
- Payment status indicators (Complete, Partial, Overpaid)

### Modern UI
- Responsive design that works on all devices
- Modal dialogs for detailed information
- Toast notifications for user feedback
- Clean, professional interface

## Usage

1. **Dashboard**: View system statistics and navigate to different sections
2. **Students**: Manage student records with allowance tracking
3. **Courses**: Manage course information with fee details
4. **Enrollments**: Enroll students in courses with payment processing
5. **Payments**: Track and manage all payment records

## Technical Details

- **PHP 7.4+** with MySQLi for database operations
- **Prepared statements** for security
- **Responsive CSS** with modern design principles
- **JavaScript** for interactive features
- **AJAX** for dynamic content loading

## Security Features

- SQL injection protection with prepared statements
- XSS protection with htmlspecialchars()
- Input validation and sanitization
- Secure database connections

This system provides a complete solution for educational institutions to manage student enrollments with integrated financial tracking.