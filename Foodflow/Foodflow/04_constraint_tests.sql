-- ==========================================================
-- FoodFlow database  |  File 4 of 4: Constraint & Integrity Tests
-- Tests CHECK constraints, UNIQUE constraints, and Foreign Keys
-- ==========================================================
USE foodflow;

-- TEST 1: Unique Email Constraint (Should Fail)
-- Expected error: Duplicate entry for key 'users.email'
-- INSERT INTO users (full_name, email, password_hash, role) 
-- VALUES ('Duplicate User', 'admin@foodflow.com', 'dummyhash', 'customer');

-- TEST 2: Email format CHECK constraint (Should Fail)
-- Expected error: Check constraint 'chk_users_email' is violated
-- INSERT INTO users (full_name, email, password_hash, role)
-- VALUES ('Invalid Email', 'not-an-email', 'dummyhash', 'customer');

-- TEST 3: Negative Food Price CHECK constraint (Should Fail)
-- Expected error: Check constraint 'chk_food_price' is violated
-- INSERT INTO food (name, price) VALUES ('Free Food Error', -50.00);

-- TEST 4: Non-existent Category Foreign Key (Should Fail)
-- Expected error: Cannot add or update a child row: a foreign key constraint fails
-- INSERT INTO food (name, price, category_id) VALUES ('Ghost Dish', 150.00, 99999);

-- TEST 5: Review Rating Range (Must be 1 to 5) (Should Fail)
-- Expected error: Check constraint 'chk_review_rating' is violated
-- INSERT INTO reviews (customer_id, food_id, rating) VALUES (4, 1, 6);
