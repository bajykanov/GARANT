-- ГАРАНТ: схема маркетплейса с эскроу.
-- Импорт пересоздаёт только базу garant (остальные базы не затрагиваются).
-- Повторный импорт удалит данные этого проекта.
--
-- Учебные входы после импорта:
--   admin@garant.local / admin123
--   anna@garant.local  / seller123
--   igor@garant.local  / buyer123

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS garant
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE garant;

DROP TABLE IF EXISTS messages;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS disputes;
DROP TABLE IF EXISTS escrow_transactions;
DROP TABLE IF EXISTS deals;
DROP TABLE IF EXISTS product_delivery;
DROP TABLE IF EXISTS product_images;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS delivery_options;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;

-- Пользователи. Два рейтинга живут отдельно и стартуют с нейтральных 5.00.
CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  name VARCHAR(120) NOT NULL,
  phone VARCHAR(30) DEFAULT NULL,
  city VARCHAR(120) DEFAULT NULL,
  about TEXT,
  role ENUM('user', 'admin') NOT NULL DEFAULT 'user',
  seller_rating DECIMAL(3,2) NOT NULL DEFAULT 5.00,
  buyer_rating DECIMAL(3,2) NOT NULL DEFAULT 5.00,
  seller_reviews_count INT UNSIGNED NOT NULL DEFAULT 0,
  buyer_reviews_count INT UNSIGNED NOT NULL DEFAULT 0,
  twofa_enabled TINYINT(1) NOT NULL DEFAULT 0,
  twofa_code_hash VARCHAR(255) DEFAULT NULL,
  twofa_expires_at DATETIME DEFAULT NULL,
  card_last4 CHAR(4) DEFAULT NULL,
  card_brand VARCHAR(20) DEFAULT NULL,
  card_holder VARCHAR(120) DEFAULT NULL,
  card_exp CHAR(5) DEFAULT NULL,
  is_blocked TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  parent_id INT UNSIGNED DEFAULT NULL,
  name VARCHAR(160) NOT NULL,
  slug VARCHAR(160) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_categories_slug (slug),
  CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE delivery_options (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  kind ENUM('logistics', 'special', 'pickup') NOT NULL,
  description VARCHAR(255) DEFAULT NULL,
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  title VARCHAR(200) NOT NULL,
  description TEXT NOT NULL,
  price DECIMAL(12,2) DEFAULT NULL,
  deal_type ENUM('sale', 'exchange', 'both') NOT NULL DEFAULT 'sale',
  exchange_for VARCHAR(255) DEFAULT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  city VARCHAR(120) DEFAULT NULL,
  status ENUM('active', 'hidden', 'sold') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_products_catalog (status, category_id, deal_type),
  KEY idx_products_user (user_id),
  CONSTRAINT fk_products_user FOREIGN KEY (user_id) REFERENCES users (id),
  CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_images (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  path VARCHAR(64) NOT NULL,
  is_main TINYINT(1) NOT NULL DEFAULT 0,
  CONSTRAINT fk_images_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_delivery (
  product_id INT UNSIGNED NOT NULL,
  delivery_option_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (product_id, delivery_option_id),
  CONSTRAINT fk_pd_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
  CONSTRAINT fk_pd_option FOREIGN KEY (delivery_option_id) REFERENCES delivery_options (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Сделка. amount = 0 у чистого обмена: гарант фиксирует обязательства без денег.
CREATE TABLE deals (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  buyer_id INT UNSIGNED NOT NULL,
  seller_id INT UNSIGNED NOT NULL,
  deal_type ENUM('sale', 'exchange') NOT NULL,
  amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  exchange_offer TEXT,
  delivery_option_id INT UNSIGNED DEFAULT NULL,
  delivery_address TEXT,
  buyer_note TEXT,
  status ENUM('paid', 'shipped', 'confirmed', 'completed', 'dispute', 'refunded', 'cancelled') NOT NULL DEFAULT 'paid',
  buyer_confirmed TINYINT(1) NOT NULL DEFAULT 0,
  seller_confirmed TINYINT(1) NOT NULL DEFAULT 0,
  escrow_deadline DATETIME DEFAULT NULL,
  shipped_at DATETIME DEFAULT NULL,
  confirmed_at DATETIME DEFAULT NULL,
  completed_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_deals_buyer (buyer_id, status),
  KEY idx_deals_seller (seller_id, status),
  KEY idx_deals_deadline (status, escrow_deadline),
  CONSTRAINT fk_deals_product FOREIGN KEY (product_id) REFERENCES products (id),
  CONSTRAINT fk_deals_buyer FOREIGN KEY (buyer_id) REFERENCES users (id),
  CONSTRAINT fk_deals_seller FOREIGN KEY (seller_id) REFERENCES users (id),
  CONSTRAINT fk_deals_delivery FOREIGN KEY (delivery_option_id) REFERENCES delivery_options (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Журнал денег гаранта: hold замораживает, release отдаёт продавцу, refund возвращает покупателю.
CREATE TABLE escrow_transactions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  deal_id INT UNSIGNED NOT NULL,
  type ENUM('hold', 'release', 'refund') NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  comment VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_escrow_deal (deal_id),
  CONSTRAINT fk_escrow_deal FOREIGN KEY (deal_id) REFERENCES deals (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- as_role — какая репутация цели пересчитывается: seller или buyer.
CREATE TABLE reviews (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  deal_id INT UNSIGNED NOT NULL,
  author_id INT UNSIGNED NOT NULL,
  target_id INT UNSIGNED NOT NULL,
  as_role ENUM('seller', 'buyer') NOT NULL,
  rating TINYINT UNSIGNED NOT NULL,
  comment TEXT,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_review_once (deal_id, author_id),
  KEY idx_reviews_target (target_id, as_role),
  CONSTRAINT fk_reviews_deal FOREIGN KEY (deal_id) REFERENCES deals (id) ON DELETE CASCADE,
  CONSTRAINT fk_reviews_author FOREIGN KEY (author_id) REFERENCES users (id),
  CONSTRAINT fk_reviews_target FOREIGN KEY (target_id) REFERENCES users (id),
  CONSTRAINT chk_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE messages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  deal_id INT UNSIGNED NOT NULL,
  sender_id INT UNSIGNED DEFAULT NULL,
  body TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_messages_deal (deal_id, id),
  CONSTRAINT fk_messages_deal FOREIGN KEY (deal_id) REFERENCES deals (id) ON DELETE CASCADE,
  CONSTRAINT fk_messages_sender FOREIGN KEY (sender_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE disputes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  deal_id INT UNSIGNED NOT NULL,
  opened_by INT UNSIGNED DEFAULT NULL,
  reason TEXT NOT NULL,
  status ENUM('open', 'resolved') NOT NULL DEFAULT 'open',
  resolution ENUM('refund', 'release') DEFAULT NULL,
  admin_comment TEXT,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME DEFAULT NULL,
  KEY idx_disputes_status (status, created_at),
  CONSTRAINT fk_disputes_deal FOREIGN KEY (deal_id) REFERENCES deals (id) ON DELETE CASCADE,
  CONSTRAINT fk_disputes_user FOREIGN KEY (opened_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Пароли: password_hash(PASSWORD_DEFAULT), алгоритм bcrypt.
INSERT INTO users (id, email, password_hash, name, phone, city, about, role, seller_rating, buyer_rating, seller_reviews_count, buyer_reviews_count, card_last4, card_brand, card_holder, card_exp) VALUES
(1, 'admin@garant.local', '$2y$10$4eX.IgX2dnQJ41EczJDCMeEMHowMSX/VcLR4Ic6pLHplcn4qD8m86', 'Администратор', NULL, 'Москва', 'Служебная учётная запись гаранта платформы.', 'admin', 5.00, 5.00, 0, 0, NULL, NULL, NULL, NULL),
(2, 'anna@garant.local', '$2y$10$ot.5PFb9gtOK0RClw5/EYuflrd8nW4vavwzVR5z0t79eZLiIw0YKa', 'Анна Ковалёва', '+7 900 111-22-33', 'Краснодар', 'Фермерское хозяйство. Зерно, овощи и небольшая техника. Отгружаю со склада в крае.', 'user', 4.50, 5.00, 2, 0, '4242', 'Visa', 'ANNA KOVALEVA', '12/28'),
(3, 'igor@garant.local', '$2y$10$WoIACrOph4xCoGaYzEYXeuJo4uz.k21/lWoRj0pki2QkDzPyU3Y/2', 'Игорь Смирнов', '+7 900 444-55-66', 'Воронеж', 'Закупаю продукты и инструмент для подсобного хозяйства.', 'user', 5.00, 5.00, 0, 2, '5512', 'Mastercard', 'IGOR SMIRNOV', '08/27');

INSERT INTO categories (id, parent_id, name, slug, sort_order) VALUES
(1, NULL, 'Сельхозугодья и сельхозпродукция', 'selhoz', 10),
(8, NULL, 'Прочие продукты', 'prochie', 20),
(11, NULL, 'Товары', 'tovary', 30);

INSERT INTO categories (id, parent_id, name, slug, sort_order) VALUES
(2, 1, 'Зерновые', 'zernovye', 11),
(3, 1, 'Овощи', 'ovoshchi', 12),
(4, 1, 'Фрукты и ягоды', 'frukty', 13),
(5, 1, 'Молочная продукция', 'molochka', 14),
(6, 1, 'Мёд и пчеловодство', 'med', 15),
(7, 1, 'Сельхозугодья', 'ugodia', 16),
(9, 8, 'Бакалея', 'bakaleya', 21),
(10, 8, 'Консервация', 'konservy', 22),
(12, 11, 'Бытовая техника', 'tehnika', 31),
(13, 11, 'Инструменты', 'instrumenty', 32),
(14, 11, 'Спецтехника', 'spectehnika', 33),
(15, 11, 'Стройматериалы', 'stroymaterialy', 34);

INSERT INTO delivery_options (id, name, kind, description, sort_order) VALUES
(1, 'СДЭК', 'logistics', 'Пункт выдачи или курьер до двери', 10),
(2, 'Boxberry', 'logistics', 'Постаматы и отделения', 20),
(3, 'Яндекс Доставка', 'logistics', 'Курьер по городу и между городами', 30),
(4, 'Почта России', 'logistics', 'Отделение или доставка до адреса', 40),
(5, 'Доставка продавца', 'logistics', 'Продавец везёт своим транспортом', 50),
(6, 'Самовывоз', 'pickup', 'Забрать со склада или с поля', 60),
(7, 'Грузовик', 'special', 'Бортовой или тентованный грузовик', 70),
(8, 'Манипулятор', 'special', 'Погрузка и перевозка тяжёлого', 80),
(9, 'Трактор', 'special', 'Вывоз с поля и короткие плечи', 90),
(10, 'Трал', 'special', 'Перевозка самоходной техники', 100);

INSERT INTO products (id, user_id, category_id, title, description, price, deal_type, exchange_for, quantity, city, status, created_at) VALUES
(1, 2, 2, 'Пшеница продовольственная, 3 класс', 'Партия продовольственной пшеницы урожая этого сезона. Влажность и сорность в пределах ГОСТ, документы на партию отдам при отгрузке. Фасовка — насыпью, от 1 тонны.', 85000.00, 'sale', NULL, 8, 'Краснодар', 'active', DATE_SUB(NOW(), INTERVAL 12 DAY)),
(2, 2, 3, 'Картофель семенной, сорт Удача', 'Калибр 35–55 мм, без гнили, просушен в хранилище. Подходит для посадки и для стола. Отгружаю сетками по 25 кг.', 12000.00, 'sale', NULL, 20, 'Краснодар', 'active', DATE_SUB(NOW(), INTERVAL 9 DAY)),
(3, 2, 3, 'Томаты грунтовые, ящик 10 кг', 'Спелые грунтовые томаты с поля. Срок снятия — день отправки. Можно купить ящик или обменять на зелень и рассаду.', 3500.00, 'both', 'Зелень, рассада или мёд', 15, 'Краснодар', 'active', DATE_SUB(NOW(), INTERVAL 4 DAY)),
(4, 2, 6, 'Мёд луговой, банка 3 кг', 'Разнотравье, откачка августа. Банка пищевого пластика, без добавок. Отправляю службами доставки в обрешётке.', 2100.00, 'sale', NULL, 6, 'Краснодар', 'active', DATE_SUB(NOW(), INTERVAL 30 DAY)),
(5, 2, 7, 'Пашня 12 га, Краснодарский край', 'Участок сельхозназначения, ровный, с подъездом для грузовика. Кадастровые документы покажу до сделки. Осмотр по договорённости, передача только после подтверждения гаранта.', 4500000.00, 'sale', NULL, 1, 'Краснодар', 'active', DATE_SUB(NOW(), INTERVAL 15 DAY)),
(6, 2, 4, 'Яблоки сезонные, мешок 20 кг', 'Смесь осенних сортов с сада. Ищу обмен на овощи или зерно, деньги не нужны. Самовывоз с хозяйства или доставка продавца по краю.', NULL, 'exchange', 'Овощи или фуражное зерно', 10, 'Краснодар', 'active', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(7, 2, 12, 'Холодильник Атлант, б/у', 'Двухкамерный, морозит, уплотнители целые. Есть мелкие царапины на дверце. Заберу со второго этажа, если у перевозчика есть грузчики.', 15000.00, 'sale', NULL, 1, 'Краснодар', 'active', DATE_SUB(NOW(), INTERVAL 8 DAY)),
(8, 2, 13, 'Набор садового инструмента', 'Лопата, вилы, секатор, тачка на одном колесе. Металл без сквозной ржавчины. Продам комплектом или обменяю на электроинструмент.', 6400.00, 'both', 'Электроинструмент в рабочем состоянии', 4, 'Краснодар', 'active', DATE_SUB(NOW(), INTERVAL 18 DAY)),
(9, 2, 14, 'Мини-трактор 24 л.с.', 'Дизель, наработка умеренная, фреза и плуг в комплекте. Нужен трал или свой ход с сопровождением. Осмотр на площадке хозяйства.', 690000.00, 'sale', NULL, 1, 'Краснодар', 'active', DATE_SUB(NOW(), INTERVAL 6 DAY));

INSERT INTO product_delivery (product_id, delivery_option_id) VALUES
(1, 5), (1, 6), (1, 7),
(2, 5), (2, 6), (2, 7),
(3, 1), (3, 2), (3, 5), (3, 6),
(4, 1), (4, 2), (4, 3), (4, 4),
(5, 6), (5, 7),
(6, 5), (6, 6),
(7, 1), (7, 3), (7, 5), (7, 7),
(8, 1), (8, 2), (8, 6),
(9, 5), (9, 7), (9, 8), (9, 9), (9, 10);

-- Исторические и текущие сделки, чтобы кабинет не был пустым.
INSERT INTO deals (id, product_id, buyer_id, seller_id, deal_type, amount, exchange_offer, delivery_option_id, delivery_address, buyer_note, status, buyer_confirmed, seller_confirmed, escrow_deadline, shipped_at, confirmed_at, completed_at, created_at) VALUES
(1, 4, 3, 2, 'sale', 2100.00, NULL, 1, 'Воронеж, ул. Садовая, 14', 'Просьба обрешетить банку.', 'completed', 1, 0, DATE_SUB(NOW(), INTERVAL 12 DAY), DATE_SUB(NOW(), INTERVAL 19 DAY), DATE_SUB(NOW(), INTERVAL 18 DAY), DATE_SUB(NOW(), INTERVAL 18 DAY), DATE_SUB(NOW(), INTERVAL 20 DAY)),
(2, 8, 3, 2, 'sale', 6400.00, NULL, 6, 'Самовывоз', 'Заберу в субботу.', 'completed', 1, 0, DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 9 DAY), DATE_SUB(NOW(), INTERVAL 8 DAY), DATE_SUB(NOW(), INTERVAL 8 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY)),
(3, 1, 3, 2, 'sale', 85000.00, NULL, 7, 'Воронежская область, элеватор на трассе М4, км 512', 'Нужна отгрузка на следующей неделе.', 'paid', 0, 0, NULL, NULL, NULL, NULL, DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(4, 2, 3, 2, 'sale', 12000.00, NULL, 5, 'Воронеж, склад на ул. Дорожной, 7', NULL, 'shipped', 0, 0, DATE_ADD(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY), NULL, NULL, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(5, 7, 3, 2, 'sale', 15000.00, NULL, 7, 'Воронеж, частный дом, ул. Луговая, 3', NULL, 'dispute', 0, 0, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 6 DAY), NULL, NULL, DATE_SUB(NOW(), INTERVAL 8 DAY));

INSERT INTO escrow_transactions (deal_id, type, amount, comment, created_at) VALUES
(1, 'hold', 2100.00, 'Оплата заморожена на счёте гаранта', DATE_SUB(NOW(), INTERVAL 20 DAY)),
(1, 'release', 2100.00, 'Покупатель подтвердил получение', DATE_SUB(NOW(), INTERVAL 18 DAY)),
(2, 'hold', 6400.00, 'Оплата заморожена на счёте гаранта', DATE_SUB(NOW(), INTERVAL 10 DAY)),
(2, 'release', 6400.00, 'Покупатель подтвердил получение', DATE_SUB(NOW(), INTERVAL 8 DAY)),
(3, 'hold', 85000.00, 'Оплата заморожена на счёте гаранта', DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(4, 'hold', 12000.00, 'Оплата заморожена на счёте гаранта', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(5, 'hold', 15000.00, 'Оплата заморожена на счёте гаранта', DATE_SUB(NOW(), INTERVAL 8 DAY));

INSERT INTO reviews (deal_id, author_id, target_id, as_role, rating, comment, created_at) VALUES
(1, 3, 2, 'seller', 5, 'Мёд приехал целым, вкус честный, без горечи. Документы на партию прислали сразу.', DATE_SUB(NOW(), INTERVAL 17 DAY)),
(1, 2, 3, 'buyer', 5, 'Оплата прошла через гаранта без напоминаний, адрес был точный.', DATE_SUB(NOW(), INTERVAL 17 DAY)),
(2, 3, 2, 'seller', 4, 'Инструмент рабочий, у тачки слегка люфтит колесо — предупредили заранее.', DATE_SUB(NOW(), INTERVAL 7 DAY)),
(2, 2, 3, 'buyer', 5, 'Приехал вовремя, осмотрел и спокойно подтвердил сделку.', DATE_SUB(NOW(), INTERVAL 7 DAY));

INSERT INTO messages (deal_id, sender_id, body, is_read, created_at) VALUES
(1, NULL, 'Покупатель оплатил заказ. 2 100 ₽ заморожены на счёте гаранта до подтверждения получения.', 1, DATE_SUB(NOW(), INTERVAL 20 DAY)),
(1, 2, 'Отправлю СДЭКом завтра, трек напишу сюда.', 1, DATE_SUB(NOW(), INTERVAL 19 DAY)),
(1, 3, 'Трек получил, банку забрал. Подтверждаю.', 1, DATE_SUB(NOW(), INTERVAL 18 DAY)),
(3, NULL, 'Покупатель оплатил заказ. 85 000 ₽ заморожены на счёте гаранта. Товар зарезервирован.', 1, DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(3, 3, 'Можем принять машину в четверг после обеда.', 0, DATE_SUB(NOW(), INTERVAL 40 MINUTE)),
(4, NULL, 'Продавец отметил отправку. Подтвердите получение в течение 7 дней, иначе откроется спор.', 1, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(4, 2, 'Картофель выехал с водителем, ориентир — завтра к вечеру.', 0, DATE_SUB(NOW(), INTERVAL 20 HOUR)),
(5, NULL, 'Покупатель открыл спор. Деньги остаются замороженными, пока администратор не примет решение.', 1, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(5, 3, 'Холодильник приехал с вмятиной на дверце и не держит холод. Прошу возврат.', 1, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(5, 2, 'Вмятина была указана в объявлении. По холоду готова прислать видео проверки до отгрузки.', 1, DATE_SUB(NOW(), INTERVAL 20 HOUR));

INSERT INTO disputes (deal_id, opened_by, reason, status, created_at) VALUES
(5, 3, 'Товар приехал повреждённым и не морозит. В объявлении была только косметическая царапина. Прошу вернуть оплату.', 'open', DATE_SUB(NOW(), INTERVAL 1 DAY));

SET FOREIGN_KEY_CHECKS = 1;
