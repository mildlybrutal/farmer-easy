USE farmers_portal;

-- Sample users (passwords hashed with password_hash('password', PASSWORD_DEFAULT))
INSERT INTO users (name, email, password, role) VALUES
('Farmer Joe', 'farmer@example.com', '$2y$10$somehashedpasswordhere', 'farmer'),
('Retailer Bob', 'retailer@example.com', '$2y$10$anotherhashedpassword', 'retailer'),
('Public Jane', 'public@example.com', '$2y$10$yetanotherhashedpass', 'public');

-- Sample product
INSERT INTO products (farmer_id, name, description, price, stock) VALUES
(1, 'Organic Apples', 'Fresh from the orchard', 2.50, 100);

-- Sample project
INSERT INTO projects (retailer_id, title, description, deadline) VALUES
(2, 'Bulk Potato Order', 'Need 500kg of potatoes', '2025-04-01');