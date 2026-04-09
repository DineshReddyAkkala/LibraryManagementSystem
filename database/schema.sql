CREATE DATABASE IF NOT EXISTS library_management_system;
USE library_management_system;

CREATE TABLE roles (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) UNIQUE NOT NULL
);

CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_email_confirmed TINYINT(1) NOT NULL DEFAULT 0,
    confirmation_token VARCHAR(64),
    confirmation_sent_at TIMESTAMP NULL,
    status ENUM('active','inactive') DEFAULT 'inactive',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(role_id),
    UNIQUE KEY uq_users_confirmation_token (confirmation_token)
);

CREATE TABLE categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) UNIQUE NOT NULL
);

CREATE TABLE publishers (
    publisher_id INT AUTO_INCREMENT PRIMARY KEY,
    publisher_name VARCHAR(150) NOT NULL
);

CREATE TABLE authors (
    author_id INT AUTO_INCREMENT PRIMARY KEY,
    author_name VARCHAR(150) NOT NULL
);

CREATE TABLE books (
    book_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    isbn VARCHAR(30) UNIQUE,
    publisher_id INT,
    publish_year YEAR,
    total_copies INT NOT NULL,
    available_copies INT NOT NULL,
    FOREIGN KEY (publisher_id) REFERENCES publishers(publisher_id)
);

CREATE TABLE book_authors (
    book_id INT NOT NULL,
    author_id INT NOT NULL,
    PRIMARY KEY (book_id, author_id),
    FOREIGN KEY (book_id) REFERENCES books(book_id),
    FOREIGN KEY (author_id) REFERENCES authors(author_id)
);

CREATE TABLE book_categories (
    book_id INT NOT NULL,
    category_id INT NOT NULL,
    PRIMARY KEY (book_id, category_id),
    FOREIGN KEY (book_id) REFERENCES books(book_id),
    FOREIGN KEY (category_id) REFERENCES categories(category_id)
);

CREATE TABLE borrowings (
    borrowing_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    book_id INT NOT NULL,
    borrow_date DATE NOT NULL,
    due_date DATE NOT NULL,
    return_date DATE,
    status ENUM('borrowed','returned','overdue','return_requested') DEFAULT 'borrowed',
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (book_id) REFERENCES books(book_id)
);

CREATE TABLE reservations (
    reservation_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    book_id INT NOT NULL,
    reservation_date DATE NOT NULL,
    position INT,
    status ENUM('active','fulfilled','cancelled') DEFAULT 'active',
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (book_id) REFERENCES books(book_id)
);

CREATE TABLE fines (
    fine_id INT AUTO_INCREMENT PRIMARY KEY,
    borrowing_id INT NOT NULL,
    amount DECIMAL(6,2) NOT NULL,
    paid_status ENUM('paid','unpaid') DEFAULT 'unpaid',
    FOREIGN KEY (borrowing_id) REFERENCES borrowings(borrowing_id)
);

CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    notification_type ENUM('due_date','reservation','general','fine'),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_read BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

CREATE TABLE borrow_history (
    history_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    book_id INT NOT NULL,
    borrowed_at DATE NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (book_id) REFERENCES books(book_id)
);

CREATE TABLE search_history (
    search_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    search_keyword VARCHAR(255) DEFAULT NULL,
    category_id INT DEFAULT NULL,
    searched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (category_id) REFERENCES categories(category_id)
);

INSERT IGNORE INTO roles (role_id, role_name) VALUES
    (1, 'student'),
    (2, 'admin');

INSERT IGNORE INTO categories (category_id, category_name) VALUES
    (1, 'Computer Science'),
    (2, 'Engineering'),
    (3, 'Mathematics'),
    (4, 'Humanities'),
    (5, 'Data Science'),
    (6, 'Software Engineering');

INSERT IGNORE INTO publishers (publisher_id, publisher_name) VALUES
    (1, 'Addison-Wesley Professional'),
    (2, 'MIT Press'),
    (3, 'Pearson'),
    (4, 'O''Reilly Media'),
    (5, 'McGraw-Hill Education'),
    (6, 'Prentice Hall'),
    (7, 'Cambridge University Press');

INSERT IGNORE INTO authors (author_id, author_name) VALUES
    (1, 'Erich Gamma'),
    (2, 'Richard Helm'),
    (3, 'Ralph Johnson'),
    (4, 'John Vlissides'),
    (5, 'Thomas H. Cormen'),
    (6, 'Charles E. Leiserson'),
    (7, 'Ronald L. Rivest'),
    (8, 'Clifford Stein'),
    (9, 'Donald E. Knuth'),
    (10, 'Oren Patashnik'),
    (11, 'Robert C. Martin'),
    (12, 'Steve McConnell'),
    (13, 'Andrew Hunt'),
    (14, 'David Thomas'),
    (15, 'James F. Kurose'),
    (16, 'Keith W. Ross'),
    (17, 'Stuart Russell'),
    (18, 'Peter Norvig'),
    (19, 'Abraham Silberschatz'),
    (20, 'Henry F. Korth'),
    (21, 'Ian Goodfellow'),
    (22, 'François Chollet'),
    (23, 'Aurélien Géron'),
    (24, 'Dennis M. Ritchie'),
    (25, 'Thomas H. Cormen Jr.');

INSERT IGNORE INTO books (book_id, title, isbn, publisher_id, publish_year, total_copies, available_copies) VALUES
    (1, 'Design Patterns: Elements of Reusable Object-Oriented Software', '9780201633610', 1, 1994, 6, 4),
    (2, 'Introduction to Algorithms', '9780262046305', 2, 2022, 5, 2),
    (3, 'Concrete Mathematics', '9780201558029', 1, 1994, 4, 3),
    (4, 'Clean Code', '9780132350884', 1, 2008, 7, 5),
    (5, 'Code Complete', '9780735619678', 5, 2004, 6, 4),
    (6, 'The Pragmatic Programmer', '9780201616224', 1, 1999, 5, 3),
    (7, 'Computer Networking: A Top-Down Approach', '9780133594140', 3, 2016, 8, 6),
    (8, 'Artificial Intelligence: A Modern Approach', '9780134610993', 3, 2020, 5, 2),
    (9, 'Operating System Concepts', '9781119800368', 5, 2023, 5, 3),
    (10, 'Database System Concepts', '9780073523320', 5, 2019, 6, 5),
    (11, 'Deep Learning with Python', '9781617296864', 4, 2021, 5, 4),
    (12, 'Hands-On Machine Learning with Scikit-Learn, Keras, and TensorFlow', '9781098125974', 4, 2023, 6, 4),
    (13, 'Discrete Mathematics and Its Applications', '9781259676512', 5, 2018, 6, 5),
    (14, 'Structure and Interpretation of Computer Programs', '9780262510875', 2, 1996, 4, 2),
    (15, 'Modern Operating Systems', '9780133591620', 3, 2014, 5, 3);

INSERT IGNORE INTO book_authors (book_id, author_id) VALUES
    (1, 1),
    (1, 2),
    (1, 3),
    (1, 4),
    (2, 5),
    (2, 6),
    (2, 7),
    (2, 8),
    (3, 9),
    (3, 10),
    (4, 11),
    (5, 12),
    (6, 13),
    (6, 14),
    (7, 15),
    (7, 16),
    (8, 17),
    (8, 18),
    (9, 19),
    (10, 20),
    (11, 22),
    (12, 23),
    (13, 25),
    (14, 9),
    (15, 19);

INSERT IGNORE INTO book_categories (book_id, category_id) VALUES
    (1, 1),
    (2, 1),
    (3, 3),
    (4, 6),
    (5, 6),
    (6, 6),
    (7, 2),
    (8, 1),
    (8, 5),
    (9, 1),
    (10, 1),
    (11, 5),
    (12, 5),
    (13, 3),
    (14, 1),
    (15, 1);
