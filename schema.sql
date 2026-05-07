CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(60) NOT NULL UNIQUE,
  display_name VARCHAR(120) NOT NULL,
  xp INT UNSIGNED NOT NULL DEFAULT 0,
  level INT UNSIGNED NOT NULL DEFAULT 1,
  title VARCHAR(120) NOT NULL DEFAULT 'Street Rookie',
  hp INT UNSIGNED NOT NULL DEFAULT 100,
  energy INT UNSIGNED NOT NULL DEFAULT 50,
  strength INT UNSIGNED NOT NULL DEFAULT 50,
  defense INT UNSIGNED NOT NULL DEFAULT 50,
  risk INT UNSIGNED NOT NULL DEFAULT 10,
  wellness_score INT UNSIGNED NOT NULL DEFAULT 0,
  day_count INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS email VARCHAR(190) NULL AFTER display_name,
  ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) NULL AFTER email,
  ADD COLUMN IF NOT EXISTS birthdate DATE NULL AFTER password_hash,
  ADD COLUMN IF NOT EXISTS height_cm DECIMAL(6,2) NULL AFTER birthdate,
  ADD COLUMN IF NOT EXISTS weight_kg DECIMAL(6,2) NULL AFTER height_cm,
  ADD COLUMN IF NOT EXISTS bmi DECIMAL(6,2) NULL AFTER weight_kg,
  ADD COLUMN IF NOT EXISTS hp INT UNSIGNED NOT NULL DEFAULT 100 AFTER title,
  ADD COLUMN IF NOT EXISTS energy INT UNSIGNED NOT NULL DEFAULT 50 AFTER hp,
  ADD COLUMN IF NOT EXISTS strength INT UNSIGNED NOT NULL DEFAULT 50 AFTER energy,
  ADD COLUMN IF NOT EXISTS defense INT UNSIGNED NOT NULL DEFAULT 50 AFTER strength,
  ADD COLUMN IF NOT EXISTS risk INT UNSIGNED NOT NULL DEFAULT 10 AFTER defense;

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
  tags JSON NULL,
  ingredient_breakdown JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_meal_logs_user_date (user_id, created_at),
  CONSTRAINT fk_meal_logs_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_meal_logs_food_item FOREIGN KEY (food_item_id) REFERENCES food_items (id) ON DELETE SET NULL
);

ALTER TABLE meal_logs
  ADD COLUMN IF NOT EXISTS ingredient_breakdown JSON NULL AFTER tags;

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

CREATE TABLE user_game_states (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL UNIQUE,
  state_json JSON NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_user_game_states_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);

CREATE TABLE activity_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  event_type VARCHAR(80) NOT NULL,
  event_message VARCHAR(255) NOT NULL,
  event_context JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_activity_logs_user_created (user_id, created_at),
  INDEX idx_activity_logs_event_type (event_type),
  CONSTRAINT fk_activity_logs_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
);

CREATE TABLE reward_catalog (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(80) NOT NULL UNIQUE,
  title VARCHAR(160) NOT NULL,
  description VARCHAR(255) NOT NULL,
  trigger_type ENUM('level', 'streak', 'wellness') NOT NULL,
  trigger_value INT UNSIGNED NOT NULL DEFAULT 1,
  reward_kind ENUM('voucher', 'discount', 'meal_box') NOT NULL DEFAULT 'voucher',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE user_rewards (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  reward_id INT UNSIGNED NOT NULL,
  status ENUM('unlocked', 'claimed') NOT NULL DEFAULT 'unlocked',
  reward_json JSON NULL,
  eligibility_json JSON NULL,
  unlocked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  claimed_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_user_reward (user_id, reward_id),
  CONSTRAINT fk_user_rewards_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_user_rewards_reward FOREIGN KEY (reward_id) REFERENCES reward_catalog (id) ON DELETE CASCADE
);

INSERT INTO quests (code, title, description, quest_type, reward_xp, target_value) VALUES
('daily_vegetable', 'Eat 1 vegetable dish', 'Log a vegetable-rich meal today.', 'daily', 20, 1),
('daily_fried_free', 'Avoid fried food today', 'Finish the day without fried dishes.', 'daily', 20, 1),
('daily_calorie_cap', 'Stay under 1500 calories', 'Keep the full day below the calorie cap.', 'daily', 25, 1500),
('daily_food_groups', 'Eat 3 food groups', 'Mix your meals across at least three groups.', 'daily', 25, 3);

INSERT INTO reward_catalog (code, title, description, trigger_type, trigger_value, reward_kind) VALUES
('reach_level_10', 'Level 10 Reward', 'Reach level 10 to unlock a healthy food voucher.', 'level', 10, 'voucher'),
('one_month_streak', '30-Day Streak Reward', 'Keep your streak alive for 30 days to claim a bigger food reward.', 'streak', 30, 'meal_box'),
('wellness_leader', 'Daily Wellness Leader', 'Hold the highest wellness score for the day to receive a reward.', 'wellness', 1, 'discount');