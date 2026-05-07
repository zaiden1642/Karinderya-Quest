# Daily Quests Implementation - Complete Summary

## Implementation Status: ✅ COMPLETE

### What Was Implemented

A comprehensive server-side daily quests system that integrates with the Karinderya Quest game to provide players with daily achievement-based objectives for bonus XP rewards.

## Files Modified

### 1. **schema.sql** 
- Added `wellness_score` column to `users` table
- Added `day_count` column to `users` table  
- Added `tags` column (JSON) to `meal_logs` table for quest tracking
- Created new `user_game_states` table for serialized game state persistence
- Quests and quest_progress tables already existed

### 2. **api/state.php**
Added 3 new endpoints and 2 helper functions:

#### Endpoints:
- **`get_daily_quests`** - Fetches all daily quests with current user's progress
- **`log_meal`** - Records a meal and updates all quest progress
- **`claim_quest`** - Marks a quest as claimed and awards XP

#### Helper Functions:
- **`calculateQuestProgress()`** - Computes progress for a specific quest
- **`detectFoodGroup()`** - Infers food group from tags and category
- **`updateQuestProgressForMeal()`** - Recalculates all quest progress after meal logged

### 3. **app.js**
- Added `callStateApi()` function for server API communication
- Updated `handleMealSubmit()` to sync meals to server via `log_meal` endpoint
- Refactored `renderQuests()` to support server quests with:
  - `fetchAndRenderServerQuests()` - Fetches from server when logged in
  - `renderClientQuests()` - Fallback for client-side calculation
- Added quest claim button event listeners with error handling

### 4. **styles.css**
- Added styling for `.quest-claim-btn` with hover/active states
- Added styling for `.quest-card.is-claimed` state
- Proper flex layout for quest meta section with claim button

### 5. **DAILY_QUESTS.md**
- Comprehensive documentation of the system
- API endpoint specifications with examples
- Database schema documentation
- Testing checklist

## Daily Quests Available

1. **Eat 1 vegetable dish** (20 XP)
   - Progress tracked via 'vegetable' tag
   - Boolean: completed when any vegetable meal logged

2. **Avoid fried food today** (20 XP)
   - Progress tracked via 'fried' tag
   - Boolean: completed when NO fried meals logged

3. **Stay under 1500 calories** (25 XP)
   - Tracks total daily calorie count
   - Progress bar: current calories / 1500
   - Completed when under 1500

4. **Eat 3 food groups** (25 XP)
   - Counts distinct food groups (Protein, Carb, Vegetable, Drink, Street Food, Other)
   - Progress bar: unique groups / 3
   - Completed when 3+ groups logged

## How It Works

### Quest Progress Tracking Flow:
```
User logs meal 
  ↓
handleMealSubmit() called
  ↓
callStateApi('log_meal', {meal: data})
  ↓
log_meal endpoint:
  - Insert meal_logs row with tags
  - Update user XP
  - Call updateQuestProgressForMeal()
  ↓
updateQuestProgressForMeal():
  - Query all today's meals
  - For each daily quest:
    - Calculate progress with calculateQuestProgress()
    - Upsert quest_progress record
  ↓
Response returned to frontend
  ↓
Frontend re-renders via renderQuests()
  ↓
fetchAndRenderServerQuests() fetches latest progress
  ↓
Display quest cards with updated progress and claim buttons
```

### Quest Claim Flow:
```
User clicks "Claim Reward" button
  ↓
callStateApi('claim_quest', {quest_id: id})
  ↓
claim_quest endpoint:
  - Verify quest exists
  - Check if already claimed today (409 if claimed)
  - Mark quest_progress.completed_at = NOW()
  - Add reward_xp to user.xp
  ↓
Response with xpRewarded
  ↓
Frontend updates state.xp
  ↓
Button disabled/hidden and shows "Reward claimed"
```

## Key Features

✅ **Server-Side Persistence**
- All quest progress stored in database
- Survives browser refresh
- Per-user, per-day tracking

✅ **Intelligent Progress Calculation**
- Meals tagged with 'vegetable' and 'fried' recognized
- Food group auto-detection from category and tags
- Calorie summation across all meals

✅ **Quest Completion Logic**
- Quests auto-complete based on meal data
- Claim button only appears for completed quests
- Prevents double-claiming via database constraint

✅ **Daily Reset**
- Each day gets fresh quest_progress records via log_date
- No manual reset needed (per-day UNIQUE constraint handles it)

✅ **Error Handling**
- 401 Unauthorized if not logged in
- 404 if quest doesn't exist
- 409 Conflict if already claimed
- Fallback to client-side quests if server fails

## Testing Guide

### Prerequisites
1. MySQL database with schema.sql applied
2. User account created and logged in
3. Server-side database configured in config.php

### Test Steps

1. **Log your first meal:**
   - Navigate to meal form
   - Select meal type, dish
   - Submit meal
   - Check that `get_daily_quests` returns updated progress

2. **Test vegetable quest:**
   - Log a meal with 'vegetable' tag (e.g., Sinigang)
   - Quest should show progress = 1
   - Button to claim should appear

3. **Test fried-free quest:**
   - Avoid logging fried meals (e.g., Kwek-kwek)
   - Quest should show completed if no fried meals logged
   - Can claim when complete

4. **Test calorie cap:**
   - Log multiple meals
   - Watch calorie progress accumulate
   - Show progress bar filling toward 1500

5. **Test food groups:**
   - Log meals from different categories:
     - Protein (Adobo, Tinola)
     - Vegetable (Sinigang)
     - Other category
   - Should count distinct groups
   - Should show 2/3, 3/3 as you log meals

6. **Claim rewards:**
   - Click "Claim Reward" on completed quest
   - Should show +XP confirmation
   - Button should change to "Reward claimed"
   - User XP should increase
   - Cannot claim same quest twice in one day

7. **Daily reset:**
   - Complete a quest and claim
   - After midnight (or manually reset log_date in DB)
   - Same quest should be available again

## Database Queries for Verification

```sql
-- Check today's meals for a user
SELECT * FROM meal_logs 
WHERE user_id = 1 AND DATE(created_at) = CURDATE()
ORDER BY created_at DESC;

-- Check quest progress
SELECT qp.*, q.title, q.code 
FROM quest_progress qp
JOIN quests q ON qp.quest_id = q.id
WHERE qp.user_id = 1 AND qp.log_date = CURDATE();

-- Check if quest was claimed
SELECT * FROM quest_progress 
WHERE user_id = 1 AND quest_id = 1 
AND log_date = CURDATE() 
AND completed_at IS NOT NULL;

-- View user stats
SELECT username, xp, level, title, wellness_score, day_count
FROM users WHERE id = 1;
```

## Known Limitations & Future Work

- [ ] Weekly and milestone quests not yet implemented
- [ ] No quest history (only current day tracked)
- [ ] No custom user quest preferences
- [ ] No quest chains/storytelling
- [ ] No community quest leaderboards
- [ ] Manual food group assignment not flexible (auto-detected only)

## Integration Notes

- The system requires user authentication (SESSION)
- Meals must include valid tags array
- Categories must be reasonable for food group detection
- Calorie values must be numeric and positive
- JSON encoding assumed for tags in database

## Performance Considerations

- `quest_progress` indexed on (user_id, quest_id, log_date)
- Queries run for each meal submission (not heavy)
- Food group detection via regex (O(n) where n = tags + category length)
- Suitable for typical usage patterns

---

**Implementation Date:** May 7, 2026  
**Status:** Ready for Testing  
**Dependencies:** MySQL 5.7+, PHP 7.2+, Modern Browser (ES6 support)
