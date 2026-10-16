# FoodFlow
- FoodFlow: Streamlining Food Orders from Kitchen to Doorstep — a DBMS and OS-based food ordering and management system.
# 🍕 FoodFlow: Streamlining Food Orders from Kitchen to Doorstep

FoodFlow is a web-based food ordering and delivery system inspired by platforms like Domino's. It allows customers to explore food menus, discover special items, and order food from a single restaurant.

A key feature of FoodFlow is its dedicated **Celebrations section**, which offers special food items and combos for birthdays, parties, and other occasions.

This project combines concepts from **Database Management Systems (DBMS)** and **Operating Systems (OS)** to demonstrate practical database management and efficient order processing.

## ✨ Features

- **User Authentication:** Login and signup functionality.
- **Food Menu:** Browse food items and categories.
- **Celebrations Section:** Explore special food items and combos for occasions.
- **Customer Dashboard:** Access customer-related features.
- **Database Management:** Store and manage users, food items, orders, payments, deliveries, and reviews.
- **OS Scheduling Module:** Demonstrates FCFS and Priority Scheduling using C.
- **Order Management:** Planned functionality for placing orders and viewing order history.

## 🛠️ Technologies Used

| Technology   | Purpose                               |
| ------------ | ------------------------------------- |
| HTML5        | Webpage structure                     |
| Tailwind CSS | Styling and responsive design         |
| JavaScript   | Frontend interactions                 |
| PHP          | Backend processing and authentication |
| MySQL        | Database management                   |
| XAMPP        | Local development server              |
| C            | Operating System modules              |

## 🗄️ DBMS Concepts

The project uses MySQL to organize and manage data through tables for:

- Users
- Food categories
- Food items
- Orders
- Order items
- Payments
- Deliveries
- Reviews

The database design uses relationships, primary keys, foreign keys, and constraints to maintain data consistency.

## ⚙️ Operating System Concepts

The project includes C-based modules to demonstrate Operating System concepts in a food-order processing scenario.

- **CPU Scheduling:** FCFS (First Come, First Served) and Priority Scheduling.
- **Process Management:** Managing order-processing jobs and their states.
- **Synchronization and IPC:** Additional modules for coordinating tasks and communication between processes, as implemented by the team.

## 🚀 Getting Started

### Prerequisites

Install [XAMPP](https://www.apachefriends.org/) and ensure PHP and MySQL are available.

### Installation

1. Clone or download this repository.
2. Place the project folder inside the XAMPP `htdocs` directory.
3. Start Apache and MySQL from the XAMPP Control Panel.
4. Open phpMyAdmin at `http://localhost/phpmyadmin`.
5. Create the project database and import the provided SQL schema.
6. Configure the database connection settings in the PHP configuration file.
7. Open the project in your browser using:

   `http://localhost/FoodFlow/`

   Adjust the URL if your project folder has a different name or if the homepage is inside a subfolder.

## 📌 Project Status

The frontend and login/signup functionality have been developed and connected to MySQL. The database schema and CPU scheduling module are available.

Food order processing, remaining OS module integration, and end-to-end testing are ongoing.

## 🎯 Project Objectives

- Provide a convenient food ordering experience.
- Offer special food options for celebrations.
- Maintain organized and consistent database records.
- Demonstrate practical applications of DBMS and OS concepts.
- Improve coordination and efficiency in restaurant order management.

## 👥 Team Members

- **Divya Agarwal** — Frontend development, login/signup integration, and project documentation.
- **Geo Emmanuel and Divya Agarwal** — PHP backend development and database connectivity.
- **Divya Agarwal and Mayank Dhami** — Database schema and design.
- **Aviral Pundir** — C-based CPU scheduling module.
- **Aradhya Rawat** — Additional Operating System modules.

---

**FoodFlow — From Kitchen to Doorstep 🍕**
