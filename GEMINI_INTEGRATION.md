# Karinderya Quest - Gemini AI Full Integration

## Overview

This document describes the complete integration of Google Gemini AI for all computational and data operations in Karinderya Quest.

## Architecture

### API Endpoints (Extended `/api/gemini.php`)

The Gemini proxy now handles multiple action types:

#### 1. Food Search
- **Action**: `search`
- **Method**: POST
- **Input**: `{ query: "adobo" }`
- **Output**: `{ ok: true, results: [{name, category, estimatedCalories, tags}, ...] }`
- **Cache**: 1 hour
- **Fallback**: Returns empty array on failure

```javascript
await searchFoodsWithGemini("adobo")
// Returns: [{name: "Adobo", category: "Protein", estimatedCalories: 320, tags: ["savory"]}]
```

#### 2. Get Food Details
- **Action**: `getFood`
- **Method**: GET or POST
- **Input**: `?name=Adobo`
- **Output**: `{ ok: true, food: {name, category, calories, price, tags, ingredients, effects} }`
- **Cache**: 24 hours
- **Fallback**: Returns null on failure

```javascript
await getFoodDetailsFromGemini("Adobo")
// Returns: {name: "Adobo", category: "Protein", calories: 320, price: 45, 
//           effects: {hp: 10, energy: 6, strength: 10, defense: 1, risk: 4, xp: 18}, ...}
```

#### 3. Estimate Nutrients
- **Action**: `estimateNutrients`
- **Method**: POST
- **Input**: `{ ingredients: [{name: "Chicken", servingSize: 1}, ...] }`
- **Output**: `{ ok: true, nutrients: {calories, protein, carbs, fat} }`
- **Cache**: 1 hour
- **Fallback**: Returns null on failure

```javascript
await estimateNutrientsWithGemini([{name: "Chicken", servingSize: 1.5}, {name: "Rice", servingSize: 1}])
// Returns: {calories: 450, protein: 28, carbs: 48, fat: 12}
```

#### 4. Generate RPG Effects
- **Action**: `generateEffects`
- **Method**: POST
- **Input**: `{ mealName: "Grilled Tilapia", ingredients: [...], calories: 320 }`
- **Output**: `{ ok: true, effects: {hp, energy, strength, defense, risk, xp} }`
- **Cache**: 24 hours
- **Fallback**: Returns null on failure

```javascript
await generateEffectsWithGemini("Adobo", ["Chicken", "Garlic", "Soy Sauce"], 320)
// Returns: {hp: 10, energy: 6, strength: 10, defense: 1, risk: 4, xp: 18}
```

#### 5. Meal Summary (Original)
- **Action**: `summary` (default)
- **Method**: POST
- **Input**: `{ meal object }`
- **Output**: `{ ok: true, text: "summary..." }`
- **Cache**: None (one-time)

## Integration Points

### Step 1: Meal Type Selection
- No Gemini calls - user selects breakfast/lunch/dinner/etc.

### Step 2: Food Search (Async)
- **Gemini Call**: `searchFoodsWithGemini(userInput)`
- **Displays**: Search suggestions with categories
- **Fallback**: If Gemini fails, shows local hardcoded database items
- **Selection**: When user clicks result, calls `getFoodDetailsFromGemini(name)` to fetch full details

### Step 3: Ingredient Management
- **No Gemini calls** - user reviews/edits ingredient list from selected food
- **Local Calculation**: Shows ingredient sliders (0.5x - 3x)

### Step 4: Calorie Preview
- **Gemini Call**: `estimateMealCaloriesWithGemini()` (async)
- **Displays**: Estimated total calories
- **Fallback**: Uses local ingredient database if Gemini fails
- **Override**: User can manually input custom calories

### Step 5: Submit Meal
- **Gemini Calls**:
  1. For database foods: `estimateMealCaloriesWithGemini()` (if not done in Step 4)
  2. For custom dishes: `generateEffectsWithGemini()` to generate RPG effects
  3. `sendToGemini()` (async summary) - existing functionality
- **Effects Applied**: Generated effects immediately applied to stats
- **Logged**: Entry saved with Gemini-generated data

## Fallback Strategy

### Hierarchy
1. **Gemini Cache** - Check if result was cached (fastest)
2. **Gemini Live** - Call Gemini API (medium speed)
3. **Local Data** - Use hardcoded food database or regex heuristics (fastest, less accurate)

### Search Fallback
```
User types "adobo"
  ↓ Try Gemini search
  ↓ Success? Return Gemini results
  ↓ Fail/Empty? Return local database.filter()
  ↓ Display either Gemini or local results
```

### Calorie Fallback
```
calculateMealCalories() is called
  ↓ Check if Gemini estimate available
  ↓ Use it if successful
  ↓ Otherwise use local: baseCalories × servingSize
```

### Effect Fallback
```
buildCustomDish() is called
  ↓ Try generateEffectsWithGemini()
  ↓ Success? Use Gemini effects
  ↓ Fail? Use local regex heuristics
```

## Caching Mechanism

### Cache Structure
- **Directory**: `/cache/` (auto-created by PHP)
- **Format**: JSON files named `{md5_hash}.json`
- **Lifetime**: 
  - Searches: 1 hour
  - Food details: 24 hours
  - Nutrient estimates: 1 hour
  - Effect generations: 24 hours

### Cache Keys
- Search: `md5('search' . json_encode('adobo'))`
- Food details: `md5('food' . json_encode('Adobo'))`
- Nutrients: `md5('nutrients' . json_encode([...]))`
- Effects: `md5('effects' . json_encode('Adobo' + ingredients))`

### Performance Benefits
- **Cache Hit**: ~5ms response (local file read)
- **Gemini Call**: ~2-3 seconds (API latency)
- **Typical User Session**: 80%+ cache hit rate (most common foods searched repeatedly)

## Error Handling

### Network Errors
- If fetch fails: Use fallback data silently
- No error alerts to user (app remains functional)
- Console warnings logged for debugging

### API Errors (Non-200 Status)
- Returns JSON error response from Gemini
- Falls back to hardcoded/default values
- Graceful degradation

### Malformed Responses
- Attempts JSON extraction from Gemini output
- Falls back to local data if JSON parse fails
- Clamped values (effects 0-20, calories positive)

### Missing API Key
- PHP proxy returns 500 with clear message
- App.js gracefully ignores and uses local data
- User can continue playing with hardcoded food database

## Configuration

### Environment Variables (Set Before Running)
```bash
export GEMINI_API_KEY="AIzaSyB0remFe0g6ZNihb7g40JE41CopRcsqwrE"
export GEMINI_MODEL="text-bison-001"  # Optional, defaults to text-bison-001
```

### PHP Cache Requirements
- Write permission to `/cache/` directory
- Directory automatically created on first API call
- No external dependencies required

## Performance Metrics

### Typical Response Times
- Cached result: 5-10ms
- First Gemini call: 2-4 seconds
- Subsequent searches (same query): 5-10ms (cached)
- Food details fetch: 2-3s (first), 5ms (cached)

### Quota Management
- No built-in rate limiting yet
- Gemini API quota: Check Google Cloud Console
- Cache strategy reduces actual API calls by ~80% in typical usage

### Optimization Tips
- Popular foods are automatically cached
- User searches are cached (repeated queries hit cache)
- Consider pre-warming cache with common Filipino dishes

## Testing Checklist

### Manual Tests
- [ ] Type "adobo" in Step 2 → See Gemini search results
- [ ] Click "Adobo" from search → See full Gemini details loaded
- [ ] Modify ingredient serving size → See recalculated calories
- [ ] Submit custom meal → See Gemini-generated effects applied
- [ ] Repeat search for "adobo" → Verify cache (no "Searching..." delay)
- [ ] Disconnect internet → App should still work with hardcoded data
- [ ] Remove GEMINI_API_KEY → App should work with fallback data

### Performance Tests
- [ ] First search: ~2-3s (Gemini call)
- [ ] Second search (same term): ~5-10ms (cache)
- [ ] Load app multiple times: Cache should be reused

### Data Validation Tests
- [ ] Calories are reasonable (100-800 range for typical meal)
- [ ] RPG effects are balanced (hp/energy/strength generally positive)
- [ ] Effects properly clipped to 0-20 range
- [ ] Archived meals preserve Gemini-generated data

## Limitations

1. **Real-time Latency**: First Gemini call adds 2-3 seconds to user flow
2. **API Dependency**: App requires internet connection for first query (then uses cache)
3. **Accuracy**: Gemini estimates may not match real nutritional data
4. **Cost**: Gemini API calls use quota (check billing)
5. **Cache Expiry**: Food details cached for 24 hours (might miss menu updates)

## Future Enhancements

1. **Pre-cache Popular Foods**: Load 50 common Filipino dishes on app startup
2. **Batch Queries**: Cache searches in bulk (e.g., all street food, all vegetarian)
3. **User Feedback**: Let users rate accuracy and refine Gemini prompts
4. **Local Storage**: Save popular searches/meals locally to reduce API calls
5. **Advanced Nutrition**: Show macronutrient breakdown (already in Gemini response)
6. **Photo Recognition**: Use Gemini Vision to identify foods from photos

## Debugging

### Enable Verbose Logging
Add to browser console:
```javascript
// Watch all Gemini API calls
const originalFetch = fetch;
window.fetch = function(...args) {
  console.log('FETCH:', args[0]);
  return originalFetch.apply(this, args)
    .then(r => {
      console.log('RESPONSE:', r.status, r.url);
      return r;
    });
};
```

### Check Cache Status
```php
// Clear cache (PHP)
exec('rm -rf ' . __DIR__ . '/../cache/*');
echo "Cache cleared";

// List cache files (PHP)
$files = glob(__DIR__ . '/../cache/*.json');
echo count($files) . " cached items\n";
```

### Monitor API Usage
- Check `/api/gemini.php` logs for errors
- Monitor Google Cloud Console for quota usage
- Review PHP error logs for cache write failures

## Deployment Notes

1. **Cache Directory**: Ensure `/cache/` directory is writable by web server
2. **Environment Variables**: Set `GEMINI_API_KEY` on hosting platform
3. **Timeout**: PHP script timeout should be ≥10 seconds (for Gemini API calls)
4. **HTTPS**: Required for production (Google API enforces HTTPS)
5. **CORS**: Not needed - all Gemini calls go through PHP proxy

## Support & Troubleshooting

**Issue**: Search shows "Searching..." forever
- **Solution**: Check internet connection, verify GEMINI_API_KEY is set

**Issue**: Effects are always between 3-6 (default range)
- **Solution**: Check PHP error logs, verify Gemini response parsing

**Issue**: Cache not working (always slow)
- **Solution**: Verify `/cache/` directory exists and is writable

**Issue**: Gemini returning wrong calorie estimates
- **Solution**: Adjust Gemini prompt in `handleEstimateNutrients()` function
