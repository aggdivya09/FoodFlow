-- ==========================================================
-- FoodFlow database  |  File 2 of 4: sample data
-- Seed accounts, restaurant, categories, and menu items
-- ==========================================================
USE foodflow;

-- 1. SEED DEFAULT USERS (Passwords match project documentation):
-- Admin:          admin@foodflow.com    / Admin@123
-- Restaurant:     staff@foodflow.com    / FoodFlow@123
-- Delivery:       delivery@foodflow.com / Delivery@123
-- Customer:       customer@foodflow.com / Customer@123
INSERT INTO users (user_id, full_name, email, password_hash, role, phone, address, is_active)
VALUES
(1, 'Admin User', 'admin@foodflow.com', '$2y$10$vvsjvvq6/yYdku1G2s8Zte8kv/V..qqQi8VbXqhhqU7KNqNUtNMrm', 'admin', '9876543210', 'FoodFlow HQ, Dehradun', 1),
(2, 'Chef Divya', 'staff@foodflow.com', '$2y$10$gvLuEWn.HyTRMxrlPiDdHeyd0s2HdoslqFxv5kq1qiIeZM4T1UKmO', 'restaurant_staff', '9876543211', 'FoodFlow Central Kitchen, Dehradun', 1),
(3, 'Rider Aviral', 'delivery@foodflow.com', '$2y$10$GikEjXTZbcb82yUkLodn/u.7w7Z.FWMJirOpLgY7gKkCX1uDwzTGa', 'delivery_person', '9876543212', 'Dehradun Delivery Hub', 1),
(4, 'Rohit Sharma', 'customer@foodflow.com', '$2y$10$fYMkf3RzwIOHvOG1X.dEjOvDkMshKzXaRAhBbgoSsFfSUTyik8lcq', 'customer', '9876543213', '12 Rajpur Road, Dehradun', 1)
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name);

-- 2. SEED RESTAURANT
INSERT INTO restaurants (restaurant_id, restaurant_name, address, city, phone, email, is_active)
VALUES
(1, 'FoodFlow Kitchen', '14, Rajpur Road', 'Dehradun', '9876543211', 'kitchen@foodflow.com', 1)
ON DUPLICATE KEY UPDATE restaurant_name = VALUES(restaurant_name);

-- 3. SEED CATEGORIES
INSERT INTO categories (id, name) VALUES
(1, 'Pizza'),
(2, 'Burgers'),
(3, 'Pasta'),
(4, 'Tacos'),
(5, 'Beverages'),
(6, 'Desserts'),
(7, 'Combos')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- 4. SEED MENU ITEMS (FOOD)
INSERT INTO food (id, category_id, restaurant_id, name, description, price, stock, available, is_veg, image) VALUES
(1, 1, 2, 'Margherita Pizza', 'Fresh tomatoes, mozzarella and basil.', 149.00, 50, 1, 1, 'margherita.png'),
(2, 1, 2, 'Farmhouse Pizza', 'Capsicum, onion, mushrooms and cheese.', 249.00, 50, 1, 1, 'farmhouse.png'),
(3, 1, 2, 'Cheese Burst Pizza', 'Extra cheese with a creamy gooey center.', 299.00, 50, 1, 1, 'cheeseburst.png'),
(4, 1, 2, 'Pepperoni Pizza', 'Savory pepperoni and melted mozzarella.', 329.00, 50, 1, 0, 'pepperoni.png'),
(5, 1, 2, 'Veg Extravaganza', 'Colorful vegetables, olives and extra cheese.', 279.00, 50, 1, 1, 'extravaganza.png'),
(6, 1, 2, 'BBQ Chicken Pizza', 'Smoky barbecue chicken with herbs and cheese.', 349.00, 50, 1, 0, 'chicken.png'),
(7, 2, 2, 'Classic Pizza Burger', 'Classic pizza sauce, melted mozzarella, tomato, onion and herbs.', 189.00, 50, 1, 1, 'b1.png'),
(8, 2, 2, 'Paneer Tikka Pizza Burger', 'Tandoori paneer, mozzarella, capsicum and spicy tikka sauce.', 219.00, 50, 1, 1, 'b2.png'),
(9, 2, 2, 'Golden Corn Pizza Burger', 'Sweet corn, melted mozzarella, creamy cheese and herbs.', 129.00, 50, 1, 1, 'b3.png'),
(10, 3, 2, 'Classic Alfredo Pasta', 'Penne pasta in rich, creamy Alfredo sauce with parmesan.', 219.00, 50, 1, 1, 'p1.png'),
(11, 3, 2, 'Pink Sauce Pasta', 'Penne tossed in creamy tomato sauce with basil and herbs.', 249.00, 50, 1, 1, 'p2.png'),
(12, 4, 2, 'Classic Veg Taco', 'Crispy shell with fresh veggies, lettuce, salsa and cheese.', 219.00, 50, 1, 1, 't1.png'),
(13, 4, 2, 'Crispy Chicken Taco', 'Crispy chicken bites, fresh veggies and smoky mayo.', 299.00, 50, 1, 0, 't3.png'),
(14, 5, 2, 'Thums Up', 'Bold, refreshing chilled cola.', 49.00, 100, 1, 1, 'bb1.png'),
(15, 5, 2, 'Classic Cold Coffee', 'Creamy chilled coffee blended with smooth milk and ice cream.', 119.00, 100, 1, 1, 'bb5.png'),
(16, 5, 2, 'Blue Lagoon', 'Blue citrus syrup, zesty lemonade and sparkling soda.', 99.00, 100, 1, 1, 'bb4.png'),
(17, 6, 2, 'Chocolate Lava Cake', 'Warm chocolate cake with gooey molten center.', 99.00, 50, 1, 1, 'd3.png'),
(18, 6, 2, 'Chocolate Brownie', 'Rich, fudgy chocolate brownie with warm chocolate sauce.', 249.00, 50, 1, 1, 'd1.png'),
(19, 7, 2, 'Pizza Combo', 'Pizza + garlic bread + Thums Up', 299.00, 30, 1, 1, 'c1.png'),
(20, 7, 2, 'Family Pizza Feast', 'Two large pizzas + garlic bread + two beverages', 699.00, 20, 1, 1, 'c3.png')
ON DUPLICATE KEY UPDATE price = VALUES(price);

-- 5. SEED INITIAL SAMPLE ORDERS
INSERT INTO orders (id, customer_id, customer_name, total, payment_method, status, delivery_address, notes)
VALUES
(1001, 4, 'Rohit Sharma', 368.00, 'Cash on delivery', 'preparing', '12, Rajpur Road, Dehradun', 'Please make it extra spicy'),
(1002, 4, 'Rohit Sharma', 548.00, 'Online', 'out_for_delivery', '12, Rajpur Road, Dehradun', 'Ring bell on arrival')
ON DUPLICATE KEY UPDATE total = VALUES(total);

INSERT INTO order_items (order_id, food_id, quantity, price)
VALUES
(1001, 1, 1, 149.00),
(1001, 8, 1, 219.00),
(1002, 2, 2, 249.00),
(1002, 14, 1, 50.00)
ON DUPLICATE KEY UPDATE quantity = VALUES(quantity);

INSERT INTO payments (order_id, amount, method, status, paid_at)
VALUES
(1001, 368.00, 'cash', 'pending', NULL),
(1002, 548.00, 'card', 'paid', NOW())
ON DUPLICATE KEY UPDATE amount = VALUES(amount);

INSERT INTO deliveries (order_id, delivery_person_id, status, assigned_at)
VALUES
(1001, 3, 'assigned', NOW()),
(1002, 3, 'picked_up', NOW())
ON DUPLICATE KEY UPDATE status = VALUES(status);
