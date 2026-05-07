# Daily Quests - Setup & Deployment Guide

## Quick Start

### 1. Database Setup

Execute the schema.sql file to create all necessary tables:

```bash
mysql -u your_user -p your_database < schema.sql
```

Or run individually in your MySQL client:
```sql
-- Users table with new columns
ALTER TABLE users ADD COLUMN wellness_score INT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN day_count INT UNSIGNED NOT NULL DEFAULT 1;

-- Add tags to meal_logs
ALTER TABLE meal_logs ADD COLUMN tags JSON NULL;

-- Create user_game_states table
CREATE TABLE user_game_states (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL UNIQUE,
  state_json JSON NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_user_game_states_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);
```

### 2. Configure Database Connection

Edit `config.php` and uncomment/fill in database credentials:

```php
$DB_HOST = '127.0.0.1';      // MySQL server
$DB_USER = 'root';            // MySQL user
$DB_PASS = 'password';        // MySQL password
$DB_NAME = 'karinderya_quest'; // Database name
$DB_PORT = '3306';            // MySQL port
```

### 3. Verify Installation

Open your browser console (F12) and test:

```javascript
// Should return available quests
await callStateApi('get_daily_quests');

// Should return quests if authenticated
```

Check Network tab to verify responses are 200 OK.

## API Endpoints Reference

### GET Daily Quests
```
POST /api/state.php
{
  "action": "get_daily_quests"
}
```

**Response:**
```json
{
  "quests": [
    {
      "id": 1,
      "code": "daily_vegetable",
      "title": "Eat 1 vegetable dish",
      "description": "Log a vegetable-rich meal today.",
      "reward": 20,
      "goal": 1,
      "progress": 0,
      "completed": false
    }
  ]
}
```

### Log Meal
```
POST /api/state.php
{
  "action": "log_meal",
  "meal": {
    "mealType": "Lunch",
    "name": "Sinigang",
    "category": "Soup / Vegetable",
    "calories": 240,
    "price": 55,
    "tags": ["broth", "vegetable", "balanced"],
    "effects": {
      "hp": 12,
      "energy": 4,
      "strength": 2,
      "defense": 10,
      "risk": -3,
      "xp": 20
    }
  }
}
```

### Claim Quest Reward
```
POST /api/state.php
{
  "action": "claim_quest",
  "quest_id": 1
}
```

## Frontend Integration

The frontend automatically integrates with the quest system:

1. **Meal Submission**: When user logs a meal, it's sent to server via `log_meal` endpoint
2. **Quest Display**: When render() is called, it fetches latest quest progress
3. **Claiming Rewards**: Quest claim buttons call `claim_quest` endpoint
4. **Error Handling**: Failed requests fall back to client-side calculations

## Troubleshooting

### Issue: "Unauthorized" Error (401)
**Solution:** Ensure user is logged in via the account system
```javascript
// Check if user is logged in
console.log(currentUser);
```

### Issue: "Invalid JSON" Error
**Solution:** Ensure tags are sent as array, not string
```javascript
// Correct
tags: ["vegetable", "healthy"]

// Wrong
tags: "vegetable, healthy"
```

### Issue: Quests not progressing
**Solution:** Verify meals have proper tags
```javascript
// Check meal tags in Network inspector
// Should see tags in request body
```

### Issue: Database connection failed
**Solution:** Verify config.php credentials
```php
// Test connection
$pdo = db_connect();
```

## Performance Tips

1. **Cache Control**: Quests are fetched on each render - consider caching if performance issues
2. **Batch Operations**: Multiple meals logged at once should batch quest updates
3. **Database Indexes**: Ensure indexes on user_id, quest_id, log_date in quest_progress

## Security Considerations

1. **Session Authentication**: All endpoints require valid PHP session
2. **Input Validation**: All meal data is sanitized and type-checked
3. **SQL Injection Prevention**: Using prepared statements throughout
4. **XSS Prevention**: JSON responses are properly escaped

## Monitoring & Debugging

### Enable detailed logging (in state.php):
```php
error_log('Quest progress calculated: ' . json_encode($progress));
```

### Check logs:
```bash
tail -f /var/log/php-errors.log
```

### Database queries to monitor:
```sql
-- See all quests being tracked
SELECT * FROM quest_progress 
WHERE log_date = CURDATE()
LIMIT 100;

-- See all meals logged today
SELECT * FROM meal_logs 
WHERE DATE(created_at) = CURDATE()
LIMIT 50;

-- Identify slow queries
SHOW PROCESSLIST;
```

## Future Enhancements

1. **Weekly Quests** - Extend to support weekly_* codes
2. **Milestone Quests** - Level-based achievements  
3. **Quest History** - Archive completed quests
4. **Leaderboards** - Rank players by quest completion
5. **Custom Quests** - User-defined quest creation

## Support & Testing

For issues or questions:
1. Check DAILY_QUESTS.md for detailed specifications
2. Review IMPLEMENTATION_COMPLETE.md for architecture
3. Test using the verification queries provided

---

**Last Updated:** May 7, 2026  
**Maintainer:** Development Team
