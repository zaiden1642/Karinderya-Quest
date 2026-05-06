# Karinderya Quest - Gemini Integration Test Guide

## Pre-Test Setup

### Environment Configuration
```bash
# Set environment variables before running your web server
export GEMINI_API_KEY="AIzaSyB0remFe0g6ZNihb7g40JE41CopRcsqwrE"
export GEMINI_MODEL="text-bison-001"

# Or create .env file in project root (if using PHP's dotenv)
GEMINI_API_KEY=AIzaSyB0remFe0g6ZNihb7g40JE41CopRcsqwrE
GEMINI_MODEL=text-bison-001
```

### Verify Prerequisites
- [ ] Web server running (PHP 7.0+)
- [ ] Project accessible at http://localhost/Karinderya Quest/
- [ ] `cache/` directory exists and is writable
- [ ] cURL enabled in PHP (for API calls)
- [ ] GEMINI_API_KEY environment variable set

## Test Scenarios

### Scenario 1: Food Search with Gemini

#### Steps
1. Open app in browser
2. Navigate to Step 2 ("What did you eat?")
3. Type "adobo" in the main dish input field

#### Expected Behavior
- [ ] Input field shows typed text
- [ ] Dropdown appears with "Searching..." message
- [ ] After 2-3 seconds, dropdown shows Gemini search results
- [ ] Results include at least 3-5 Filipino dishes (e.g., "Adobo", "Adobong Manok", etc.)
- [ ] Each result shows name and category

#### Success Criteria
- Results appear within 4 seconds
- Results are relevant to search term
- Results contain actual Filipino dishes

#### Fallback Test (If Gemini Fails)
1. Disconnect internet or stop Gemini service
2. Repeat search for "adobo"
3. Should show local hardcoded results within 1 second

### Scenario 2: Food Details Retrieval

#### Steps
1. From Scenario 1, click on "Adobo" from search results
2. Verify main dish field is populated

#### Expected Behavior
- [ ] Main dish field shows "Adobo"
- [ ] Dish suggestions dropdown closes
- [ ] Proceed to Step 3 by clicking "Next"
- [ ] Step 3 shows ingredient list (Chicken, Garlic, Soy Sauce, etc.)

#### Success Criteria
- Adobo details loaded correctly
- Ingredients are appropriate for dish
- Serving size sliders visible for each ingredient

### Scenario 3: Ingredient Serving Size & Calorie Estimation

#### Steps
1. In Step 3, adjust Chicken serving size from 1x to 1.5x
2. Observe calorie display
3. Adjust Rice from 1x to 2x
4. Observe total calories update

#### Expected Behavior
- [ ] Changing slider immediately updates ingredient calorie display
- [ ] Total meal calories update in real-time
- [ ] "Next" button to Step 4 becomes active

#### Success Criteria
- Calories increase appropriately when serving size increases
- UI responds immediately (< 100ms)
- Total calories reasonable (200-600 for typical meal)

### Scenario 4: Gemini Calorie Estimation (Step 4)

#### Steps
1. From Step 3, click "Next" to proceed to Step 4
2. Observe custom calories input field placeholder

#### Expected Behavior
- [ ] Loading: Placeholder may show "Loading estimated calories..."
- [ ] After 2-3 seconds: Placeholder shows "Leave blank to use calculated: XXX cal"
- [ ] Gemini estimate may differ slightly from local calculation
- [ ] Number is reasonable (200-600 range)

#### Success Criteria
- Gemini calorie estimate appears within 4 seconds
- Estimate is different from simple local calculation (showing Gemini worked)
- Number is in reasonable range
- User can override by entering custom value

#### Performance Notes
- First call: ~2-3 seconds (Gemini API)
- Subsequent calls: ~100-300ms (cached)

### Scenario 5: Custom Meal with Gemini Effect Generation

#### Steps
1. Return to Step 2
2. Type "grilled tilapia with vegetables"
3. Press "Next" to Step 3
4. Click "Next" again to Step 3 ("No matching dish found..." message)
5. Click "Next" to Step 4
6. Click "Next" to Step 5 and submit

#### Expected Behavior
- [ ] Step 3 shows "No matching dish found" message
- [ ] Step 4 allows custom calories entry (0-1000 range)
- [ ] Step 5 submit succeeds
- [ ] Meal logged with Gemini-generated effects

#### Success Criteria
- Meal submission succeeds
- Effects are applied (HP/Energy/Strength may increase)
- XP awarded to player
- Meal appears in "Meal Log" section

### Scenario 6: RPG Effect Generation Validation

#### Steps
1. Submit 3 different meals:
   - "Vegetable sisig" (should boost Defense)
   - "Kwek kwek" (should boost Risk)
   - "Sinigang" (should boost HP/Defense)
2. Check stats after each meal

#### Expected Behavior
- [ ] After vegetable meal: Defense stat increases more than others
- [ ] After fried meal: Risk stat increases noticeably
- [ ] After soup meal: HP increases, Risk decreases
- [ ] Stats remain clamped 0-100

#### Success Criteria
- Effects are context-aware (different foods produce different effects)
- Effects make logical sense
- Stats properly constrained
- At least 2 out of 3 meals show expected pattern

### Scenario 7: Cache Validation

#### Steps
1. Search for "chicken" (note time to show results: ~2-3s)
2. Scroll down to check cache directory: `cache/` should have files
3. Search for "chicken" again (time should be < 1s)
4. Search for "adobo" (first time for this search: ~2-3s)
5. Search for "adobo" again (should be < 1s)

#### Expected Behavior
- [ ] First search slow (Gemini API call)
- [ ] Repeated searches fast (cache hit)
- [ ] Cache files created in `cache/` directory
- [ ] Clear pattern: first search slow, repeat fast

#### Success Criteria
- Cache hit is > 10x faster than Gemini call
- Cache persists across page reloads
- Different searches have different cache entries

### Scenario 8: Fallback to Local Data (No API Key)

#### Steps
1. Remove or invalidate GEMINI_API_KEY environment variable
2. Restart web server
3. Refresh browser
4. Try to search for "adobo"
5. Try to submit a custom meal

#### Expected Behavior
- [ ] Search shows local hardcoded results (no delay)
- [ ] Dropdown populated from DATA.foodItems
- [ ] Custom meal submission succeeds
- [ ] Effects appear reasonable (local heuristics)
- [ ] No user-facing error messages
- [ ] Console may show warnings (check browser DevTools)

#### Success Criteria
- App remains fully functional without Gemini
- User can complete full workflow
- No crashes or broken UI

### Scenario 9: Meal Log Persistence

#### Steps
1. Submit 3 meals with different types (breakfast, lunch, dinner)
2. Close browser completely
3. Reopen app
4. Check "Meal Log" section

#### Expected Behavior
- [ ] All 3 meals still visible in log
- [ ] Meal details preserved (name, calories, ingredients)
- [ ] RPG effects still applied (stats show cumulative)
- [ ] localStorage has persisted data

#### Success Criteria
- Data survives browser close/reopen
- Stats accurately reflect all meals
- Gemini-generated data preserved

### Scenario 10: Budget Suggestions Still Work

#### Steps
1. Submit 2 meals
2. Scroll to "Budget Meal Combos" section
3. Check if recommendations display

#### Expected Behavior
- [ ] 3 combo suggestions appear
- [ ] Each combo shows price, calories, score
- [ ] Combos are logical (e.g., protein + carb + vegetable)
- [ ] All use hardcoded food database (not affected by Gemini)

#### Success Criteria
- Budget suggestions work
- Prices reasonable for Philippine market
- Combos make nutritional sense

## Performance Benchmarks

### Target Metrics
- First Gemini search: 2-4 seconds
- Cached search: < 500ms
- Food details fetch: 2-4 seconds (first), < 500ms (cached)
- Calorie estimation: 2-4 seconds (first), < 500ms (cached)
- Effect generation: 2-4 seconds (first), < 500ms (cached)
- Custom meal submission: < 8 seconds (includes Gemini calls)

### Acceptable Performance
- Searches complete within 5 seconds
- Cached results appear instantly (< 1s)
- User perceives responsiveness
- No timeout errors from Gemini API

## Browser Console Debugging

### Check Gemini API Calls
```javascript
// Open browser DevTools (F12) → Console
// Enable verbose logging:
const originalFetch = fetch;
window.fetch = function(...args) {
  console.log('🔵 FETCH:', args[0], args[1]?.body?.slice?.(0, 50));
  return originalFetch.apply(this, args)
    .then(r => {
      console.log('✓ Response:', r.status, r.url);
      return r.json().then(j => {
        console.log('  Data:', j);
        return new Response(JSON.stringify(j), r);
      });
    })
    .catch(e => {
      console.error('✗ Error:', e);
      throw e;
    });
};
```

### Check localStorage
```javascript
// View all stored data
JSON.parse(localStorage.getItem('karinderya-quest-state-v1'));

// Clear state to reset
localStorage.removeItem('karinderya-quest-state-v1');
```

### Check Cache File Directory
```bash
# List cache files
ls -la cache/

# View specific cache entry
cat cache/md5_hash.json | jq .

# Clear all cache
rm -rf cache/*
```

## Known Issues & Workarounds

### Issue 1: "Searching..." never resolves
**Cause**: GEMINI_API_KEY not set or invalid
**Workaround**: 
1. Verify environment variable is set
2. Test API key: `curl -s -X POST "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite-preview:generateContent?key=YOUR_KEY" -H "Content-Type: application/json" -d '{"contents":[{"parts":[{"text":"ping"}]}]}'`
3. Clear browser cache and retry

### Issue 2: Calorie estimates are way off (< 100 or > 2000)
**Cause**: Gemini prompt may need refinement for portion sizes
**Workaround**: User can manually override in Step 4
**Fix**: Adjust Gemini prompt in `handleEstimateNutrients()` function

### Issue 3: Effects always 0 (no stat changes)
**Cause**: Gemini effect response not parsing correctly
**Workaround**: Check browser console for JSON parse errors
**Fix**: Verify Gemini prompt format and response structure in `handleGenerateEffects()`

### Issue 4: Search returns empty results
**Cause**: Gemini response JSON extraction failed
**Workaround**: Manually type exact food name or proceed with custom meal
**Fix**: Check PHP error logs, adjust JSON extraction regex

## Test Completion Checklist

- [ ] Scenario 1: Food Search ✓
- [ ] Scenario 2: Food Details ✓
- [ ] Scenario 3: Ingredient Serving Sizes ✓
- [ ] Scenario 4: Calorie Estimation ✓
- [ ] Scenario 5: Custom Meal Effects ✓
- [ ] Scenario 6: RPG Effect Validation ✓
- [ ] Scenario 7: Cache Performance ✓
- [ ] Scenario 8: Fallback Mode ✓
- [ ] Scenario 9: Data Persistence ✓
- [ ] Scenario 10: Legacy Features ✓

## Sign-Off

**Tested By**: ________________
**Date**: ________________
**Environment**: ________________
**API Key Used**: ________________ (first 10 chars)
**Overall Result**: ☐ PASS ☐ FAIL

**Notes**:
_________________________________________
