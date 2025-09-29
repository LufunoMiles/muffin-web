-- Only create admin user, no default customers
-- Admin credentials: admin@muffinshop.com / admin123
INSERT IGNORE INTO users (name, email, password_hash, role) VALUES 
('Administrator', 'admin@muffinshop.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Sample products
INSERT IGNORE INTO products (name, description, price, stock, image_url) VALUES 
('Blueberry Bliss', 'Fresh blueberries baked into our signature muffin batter', 3.99, 50, '/uploads/blueberry.jpg'),
('Chocolate Dream', 'Rich chocolate muffin with chocolate chips', 4.25, 30, '/uploads/chocolate.jpg'),
('Classic Banana', 'Moist banana muffin with walnut topping', 3.75, 40, '/uploads/banana.jpg'),
('Lemon Zest', 'Tangy lemon muffin with sweet glaze', 4.10, 25, '/uploads/lemon.jpg'),
('Morning Glory', 'Healthy muffin with carrots, nuts and raisins', 4.50, 35, '/uploads/morning-glory.jpg');