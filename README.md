# Student Complaint System - Laravel Backend

A comprehensive Laravel-based backend API for a Student Complaint System. This system allows students to submit complaints, track their status, and enables administrators to manage and respond to these complaints.

## Features

- User authentication with role-based access control (Student, Staff, Admin)
- Complaint submission and tracking
- Response management for complaints
- Category and status management
- Admin dashboard with statistics and reports
- File attachment support for complaints and responses

## Requirements

- PHP 8.1+
- Composer
- MySQL/SQLite database
- Laravel 10.x

## Installation

1. Clone the repository:
   ```
   git clone https://github.com/yourusername/student-complaint-system.git
   cd student-complaint-system
   ```

2. Install dependencies:
   ```
   composer install
   ```

3. Set up environment variables:
   ```
   cp .env.example .env
   php artisan key:generate
   ```

4. Configure your database in the `.env` file:
   ```
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=student_complaint_system
   DB_USERNAME=root
   DB_PASSWORD=
   ```

5. Run migrations and seeders:
   ```
   php artisan migrate --seed
   ```

6. Start the development server:
   ```
   php artisan serve
   ```

## API Documentation

### Authentication Endpoints

- **POST /api/register** - Register a new user
- **POST /api/login** - Login and get access token
- **GET /api/user** - Get authenticated user details
- **POST /api/logout** - Logout and invalidate token

### Complaint Endpoints

- **GET /api/complaints** - List all complaints (filtered by user role)
- **POST /api/complaints** - Create a new complaint
- **GET /api/complaints/{id}** - Get a specific complaint
- **PUT /api/complaints/{id}** - Update a complaint
- **DELETE /api/complaints/{id}** - Delete a complaint

### Response Endpoints

- **GET /api/complaints/{complaint_id}/responses** - List responses for a complaint
- **POST /api/complaints/{complaint_id}/responses** - Add a response to a complaint
- **GET /api/complaints/{complaint_id}/responses/{id}** - Get a specific response
- **PUT /api/complaints/{complaint_id}/responses/{id}** - Update a response
- **DELETE /api/complaints/{complaint_id}/responses/{id}** - Delete a response

### Category Endpoints

- **GET /api/categories** - List all categories
- **GET /api/categories/{id}** - Get a specific category
- **POST /api/admin/categories** - Create a new category (admin only)
- **PUT /api/admin/categories/{id}** - Update a category (admin only)
- **DELETE /api/admin/categories/{id}** - Delete a category (admin only)

### Status Endpoints

- **GET /api/statuses** - List all statuses
- **GET /api/statuses/{id}** - Get a specific status
- **POST /api/admin/statuses** - Create a new status (admin only)
- **PUT /api/admin/statuses/{id}** - Update a status (admin only)
- **DELETE /api/admin/statuses/{id}** - Delete a status (admin only)

### Admin Endpoints

- **GET /api/admin/dashboard** - Get dashboard statistics
- **GET /api/admin/users** - List all users
- **POST /api/admin/users** - Create a new user
- **PUT /api/admin/users/{id}** - Update a user
- **DELETE /api/admin/users/{id}** - Delete a user
- **GET /api/admin/reports** - Generate system reports

## Default Users

After running the seeders, the following users will be available:

- **Admin User**
  - Email: admin@example.com
  - Password: password
  - Role: admin

- **Staff User**
  - Email: staff@example.com
  - Password: password
  - Role: staff

- **Student Users**
  - Email: john@example.com
  - Password: password
  - Role: student

  - Email: jane@example.com
  - Password: password
  - Role: student

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).