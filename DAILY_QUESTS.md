# Daily Quests Implementation

## Overview
The daily quests system allows players to complete achievement-based goals each day for bonus XP rewards. This is a server-side implementation using MySQL for persistence.

## Database Schema

### `quests` table
Defines available quests:
- `id`: Primary key
- `code`: Unique quest identifier (e.g., 'daily_vegetable')
- `title`: Display name
- `description`: Quest description
- `quest_type`: Type (daily, weekly, milestone) - currently only 'daily' is used
- `reward_xp`: XP awarded upon completion
- `target_value`: Goal threshold

### `quest_progress` table
Tracks individual player progress:
- `user_id`: Player ID (foreign key to users)
- `quest_id`: Quest ID (foreign key to quests)
- `progress_value`: Current progress toward goal
- `completed_at`: Timestamp when quest was completed (null until claimed)
- `log_date`: Date of progress tracking (yyyy-mm-dd)
- **UNIQUE constraint**: (user_id, quest_id, log_date) - one record per user per quest per day

### `users` table (additions)
- `wellness_score`: Player wellness rating (INT UNSIGNED, default 0)
- `day_count`: Number of days playing (INT UNSIGNED, default 1)

### `user_game_states` table (new)
Stores serialized game state per player:
- `user_id`: Player ID (unique foreign key)
- `state_json`: JSON object containing client state
- `updated_at`: Last modification timestamp

## Daily Quests Available

1. **daily_vegetable** ("Eat 1 vegetable dish")
   - Goal: Log at least 1 meal tagged with 'vegetable'
   - Reward: 20 XP
   - Progress: 0 or 1

2. **daily_fried_free** ("Avoid fried food today")
   - Goal: Finish the day without logging any fried meals
   - Reward: 20 XP
   - Progress: 1 if no fried meals, 0 otherwise

3. **daily_calorie_cap** ("Stay under 1500 calories")
   - Goal: Keep daily calorie total below 1500
   - Reward: 25 XP
   - Progress: Current total calories (capped at 1500)

4. **daily_food_groups** ("Eat 3 food groups")
   - Goal: Log meals from at least 3 different food groups
   - Reward: 25 XP
   - Progress: Count of distinct food groups (capped at 3)

## API Endpoints

### POST `/api/state.php` - Get Daily Quests
```json
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
    },
    ...
  ]
}
```

### POST `/api/state.php` - Log Meal
```json
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

**Response:**
```json
{
  "ok": true,
  "mealId": 12345
}
```

**Side effects:**
- Meal is saved to `meal_logs` table
- User XP is incremented
- `quest_progress` table is updated for all daily quests

### POST `/api/state.php` - Claim Quest Reward
```json
{
  "action": "claim_quest",
  "quest_id": 1
}
```

**Response:**
```json
{
  "ok": true,
  "xpRewarded": 20
}
```

**Side effects:**
- `quest_progress.completed_at` is set to NOW()
- User XP is incremented by reward amount
- Cannot claim same quest twice in one day (409 Conflict error)

## Frontend Integration

### JavaScript API

**Fetch daily quests:**
```javascript
const data = await callStateApi('get_daily_quests');
// data.quests contains array of quest objects with progress
```

**Log meal with server sync:**
```javascript
await callStateApi('log_meal', { meal: mealData });
// Automatically updates all quest progress
```

**Claim quest reward:**
```javascript
const result = await callStateApi('claim_quest', { quest_id: questId });
// result.xpRewarded contains awarded XP
```

### UI Components

**Quest Card (completed):**
- Shows checkmark or "Completed" status
- "Claim Reward" button appears if not yet claimed
- Pressing button claims reward and disables button

**Quest Card (in progress):**
- Shows progress bar: `progress / goal`
- Progress bar width: `(progress / goal) * 100%`
- Status: "Quest active"

**Quest Card (failed for day):**
- Shows progress bar with current value
- Status: "Try again tomorrow"

## Daily Reset Logic

1. **Server-side:** No automatic reset needed
   - Each day gets a new `log_date` entry
   - `quest_progress` records are per-day via unique constraint

2. **Client-side:** `syncDay()` function handles:
   - Clears `state.logs` array (client meals)
   - Clears `state.rewardedQuestIds` array (tracked locally)
   - Increments `state.dayCount`
   - Triggers re-render to fetch fresh server quests

## Data Flow on Meal Submission

1. User fills meal form and submits
2. Frontend calculates or Gemini-estimates meal properties (calories, effects, tags)
3. Frontend calls `handleMealSubmit()`:
   - Adds to client-side `state.logs` for immediate UI feedback
   - Calls `callStateApi('log_meal', { meal: entry })`
   - Server logs meal and recalculates all quest progress
4. Frontend re-renders:
   - Updates UI stats with meal effects
   - Refreshes quest display via `renderQuests()`
   - Fetches latest quest progress from server via `get_daily_quests`

## Quest Progress Calculation

The `calculateQuestProgress()` helper function evaluates each quest based on logged meals:

- **Vegetable quest:** Checks if any meal has `'vegetable'` tag
- **Fried-free quest:** Checks if NO meals have `'fried'` tag  
- **Calorie cap:** Sums all meal calories, compares to 1500
- **Food groups:** Counts distinct food groups, compares to 3

Results are cached in `quest_progress` table and returned with each `get_daily_quests` call.

## Error Handling

- **Unauthorized (401):** User not logged in
- **Invalid action (400):** Unknown action type
- **Quest not found (404):** Quest ID doesn't exist
- **Already claimed (409):** Reward already claimed today for this quest

## Testing Checklist

- [ ] Database tables created successfully
- [ ] User can log a meal and see quest progress update
- [ ] Vegetable quest tracks `'vegetable'` tag correctly
- [ ] Fried-free quest prevents completion if fried meal logged
- [ ] Calorie quest shows progress toward 1500 target
- [ ] Food groups quest counts distinct categories
- [ ] User can claim completed quest and receive XP
- [ ] Cannot claim same quest reward twice in one day
- [ ] Quests reset each day (progress cleared, can be claimed again)
- [ ] Server calculates progress correctly with multiple meals

## Implementation Files Modified

1. **schema.sql**: Added columns and tables
2. **api/state.php**: Added quest endpoints and helper functions
3. **app.js**: Integrated server API calls for quests

## Future Enhancements

- Weekly and milestone quests
- User-specific quest customization
- Quest chains and storyline progression
- Leaderboards filtered by quest completion
- Seasonal quest rotations
