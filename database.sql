CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
);

CREATE TABLE posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE theme_settings (
    id INT PRIMARY KEY,
    bg_color_1 VARCHAR(20),
    bg_color_2 VARCHAR(20),
    glass_opacity DECIMAL(3,2),
    blur_px INT,
    text_color VARCHAR(20)
);

CREATE TABLE sliders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    image_path VARCHAR(255) NOT NULL,
    title VARCHAR(100),
    caption VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO theme_settings (id, bg_color_1, bg_color_2, glass_opacity, blur_px, text_color) 
VALUES (1, '#4A00E0', '#8E2DE2', 0.15, 15, '#ffffff');
