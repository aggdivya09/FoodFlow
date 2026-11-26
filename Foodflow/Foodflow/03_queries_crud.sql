-- ==========================================================
-- FoodFlow database  |  File 3 of 4: CRUD queries & reports
-- Demonstrates DBMS query operations (SELECT, JOIN, GROUP BY, UPDATE, DELETE)
-- ==========================================================
USE foodflow;

-- 1. READ: Active menu items with category names
SELECT f.id, f.name AS dish_name, c.name AS category, f.price, f.is_veg, f.available
FROM food f
LEFT JOIN categories c ON f.category_id = c.id
WHERE f.available = 1
ORDER BY c.name, f.price;

-- 2. READ: Order summary with customer name, items count, and payment info
SELECT 
    o.id AS order_id,
    u.full_name AS customer_name,
    u.phone AS customer_phone,
    o.delivery_address,
    o.total,
    o.status AS order_status,
    p.method AS payment_method,
    p.status AS payment_status,
    o.created_at
FROM orders o
JOIN users u ON o.customer_id = u.user_id
LEFT JOIN payments p ON o.id = p.order_id
ORDER BY o.created_at DESC;

-- 3. READ: Kitchen order details showing each item and quantity
SELECT 
    oi.order_id,
    f.name AS food_item,
    oi.quantity,
    oi.price AS unit_price,
    (oi.quantity * oi.price) AS subtotal
FROM order_items oi
JOIN food f ON oi.food_id = f.id
WHERE oi.order_id = 1001;

-- 4. AGGREGATE: Category-wise sales and revenue
SELECT 
    c.name AS category_name,
    COUNT(DISTINCT oi.order_id) AS total_orders,
    SUM(oi.quantity) AS units_sold,
    COALESCE(SUM(oi.quantity * oi.price), 0) AS total_revenue
FROM categories c
LEFT JOIN food f ON c.id = f.category_id
LEFT JOIN order_items oi ON f.id = oi.food_id
GROUP BY c.id, c.name
ORDER BY total_revenue DESC;

-- 5. UPDATE: Change order status and update delivery state
UPDATE orders SET status = 'out_for_delivery' WHERE id = 1001;
UPDATE deliveries SET status = 'picked_up' WHERE order_id = 1001;

-- 6. DELETE: Safe cancellation of an unpaid order
-- (Deletes cascades to order_items, payments, and deliveries)
-- DELETE FROM orders WHERE id = 9999;
