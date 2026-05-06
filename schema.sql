CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(60) NOT NULL UNIQUE,
  display_name VARCHAR(120) NOT NULL,
  xp INT UNSIGNED NOT NULL DEFAULT 0,
  level INT UNSIGNED NOT NULL DEFAULT 1,
  title VARCHAR(120) NOT NULL DEFAULT 'Street Rookie',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE food_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(80) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  category VARCHAR(120) NOT NULL,
  food_group VARCHAR(80) NOT NULL,
  calories INT UNSIGNED NOT NULL,
  price INT UNSIGNED NOT NULL,
  hp_effect SMALLINT NOT NULL DEFAULT 0,
  energy_effect SMALLINT NOT NULL DEFAULT 0,
  strength_effect SMALLINT NOT NULL DEFAULT 0,
  defense_effect SMALLINT NOT NULL DEFAULT 0,
  risk_effect SMALLINT NOT NULL DEFAULT 0,
  xp_reward SMALLINT NOT NULL DEFAULT 10,
  tags JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE meal_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  meal_type ENUM('Breakfast', 'Lunch', 'Dinner', 'Snack') NOT NULL,
  food_item_id INT UNSIGNED NULL,
  custom_name VARCHAR(160) NULL,
  category VARCHAR(120) NOT NULL,
  calories INT UNSIGNED NOT NULL,
  price INT UNSIGNED NOT NULL DEFAULT 0,
  hp_delta SMALLINT NOT NULL DEFAULT 0,
  energy_delta SMALLINT NOT NULL DEFAULT 0,
  strength_delta SMALLINT NOT NULL DEFAULT 0,
  defense_delta SMALLINT NOT NULL DEFAULT 0,
  risk_delta SMALLINT NOT NULL DEFAULT 0,
  xp_earned SMALLINT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_meal_logs_user_date (user_id, created_at),
  CONSTRAINT fk_meal_logs_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_meal_logs_food_item FOREIGN KEY (food_item_id) REFERENCES food_items (id) ON DELETE SET NULL
);

CREATE TABLE quests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(80) NOT NULL UNIQUE,
  title VARCHAR(160) NOT NULL,
  description VARCHAR(255) NOT NULL,
  quest_type ENUM('daily', 'weekly', 'milestone') NOT NULL DEFAULT 'daily',
  reward_xp SMALLINT NOT NULL DEFAULT 20,
  target_value INT UNSIGNED NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE quest_progress (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  quest_id INT UNSIGNED NOT NULL,
  progress_value INT UNSIGNED NOT NULL DEFAULT 0,
  completed_at TIMESTAMP NULL DEFAULT NULL,
  log_date DATE NOT NULL,
  UNIQUE KEY uniq_user_quest_day (user_id, quest_id, log_date),
  CONSTRAINT fk_quest_progress_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_quest_progress_quest FOREIGN KEY (quest_id) REFERENCES quests (id) ON DELETE CASCADE
);

INSERT INTO quests (code, title, description, quest_type, reward_xp, target_value) VALUES
('daily_vegetable', 'Eat 1 vegetable dish', 'Log a vegetable-rich meal today.', 'daily', 20, 1),
('daily_fried_free', 'Avoid fried food today', 'Finish the day without fried dishes.', 'daily', 20, 1),
('daily_calorie_cap', 'Stay under 1500 calories', 'Keep the full day below the calorie cap.', 'daily', 25, 1500),
('daily_food_groups', 'Eat 3 food groups', 'Mix your meals across at least three groups.', 'daily', 25, 3);