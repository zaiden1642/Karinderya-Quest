# Karinderya Quest

Karinderya Quest is an MVP web app that combines simple karinderya meal logging with RPG-style wellness tracking and AI-powered guidance (Gemini). This README is the canonical project summary and must be updated whenever code or features change.

## Key policy
- Keep this file up-to-date: update `README.md` for every code, DB, API, or feature change.

## Features (planned / in progress)
- Account management: signup, login, profile (email, password, username, birthdate, height, weight).
- Body Attributes: `HP`, `Energy`, `Strength`, `Defense`, `Risk` computed hourly from meal logs and BMI initialization.
- Day Streak (renamed from "Current Day").
- Daily Quests: refreshed daily at 00:00 PHT, populated by Gemini.
- Rankings: Levels, Wellness Score, Day Streak, Total Daily Quests Completed.
- Budget Builds: Gemini-generated meal plans for target budgets (e.g., ₱50, ₱100, ₱150).
- Daily Macro Intake: per-day totals for carbs, fat, protein, fiber, sodium, sugar.
- End of Summary: detailed daily summary + Gemini natural-language explanation.

## Body attribute calculations
- HP: baseline derived from BMI-based initialization and adjusted hourly by Energy, Strength, Defense.
   - Each attribute has a 50% baseline. For every full 10% deviation from baseline (e.g., 32% → between 30–40%), HP changes by 1% per attribute.
   - Hourly HP change is the sum of the three attribute contributions, capped at ±15% per hour.
   - Example: Energy at 32% => −2% HP contribution; other attributes compute similarly; total applied hourly.
- Energy / Strength / Defense: estimated from summed nutrients, macronutrients, and vitamins of ingredients in meal logs (see `schema.sql` and `app.js` ingestion logic).
- Risk: increases when meals are unhealthy, when meals are skipped, or excessive portions; decreases faster when Energy/Strength/Defense are high, and more slowly when they are low.

Formulas (summary):

- Attribute percentile rounding: round to nearest 10% bucket, then map buckets to HP percent contribution (1% per 10% bucket away from 50%).
- Hourly HP delta = clamp(sum(attribute_contributions), -15%, +15%).

Note: Exact nutrient→attribute mappings are implemented in `app.js` and must be reflected here when changed.

## Account management
- Signup: email, password, username, birthdate, height (cm), weight (kg). BMI computed at signup for initial attribute values.
- Login: email + password (hashed server-side). Sessions or JWTs expected for API endpoints.
- Profile updates adjust initial baselines; record change history for reproducibility.

## Daily quests & Gemini
- Gemini generates daily quests and end-of-day summaries. Quests refresh at 00:00 PHT.
- `api/gemini.php` is the integration endpoint; store your API key in a secure env var or config file (not in repo).

## Rankings
- Four ranking types:
   1. Levels — experience-based progression
   2. Wellness Score — composite metric derived from daily attributes and macros
   3. Day Streak — consecutive days of activity
   4. Total Daily Quests Completed — cumulative quests completed

## Database (MySQL)
- Use MySQL / MariaDB. See `schema.sql` for tables: `users`, `food_items`, `meal_logs`, `quests`, `rankings`, `sessions`.
- Apply migrations when changing schema and update `README.md` with migration steps.

## Quick start
1. Serve the folder with PHP built-in server for development:

```powershell
php -S localhost:8000
```

2. Import the DB schema:

```bash
mysql -u your_user -p your_database < schema.sql
```

3. Create or edit `config.php` in the project root (next to this folder) and set `$GEMINI_API_KEY` and optionally `$GEMINI_MODEL`.

   Example `config.php` (development):

   ```php
   <?php
   $GEMINI_API_KEY = 'your-key-here';
   $GEMINI_MODEL = 'gemini-3.5-flash';
   ?>
   ```

   Do NOT rely on `.env` or environment variables; this project uses `config.php` for local configuration.

4. Open `http://localhost:8000` and test the app.

## API troubleshooting (common checks)
- Ensure database credentials are correct and DB is reachable (host, port, user, password).
- Check `api/gemini.php` for correct Gemini API key and endpoint; verify key permissions and rate limits.
- Look for CORS issues when calling API from the browser; use server-side proxies where appropriate.
- Verify PHP error logs and enable verbose errors in development (do not enable in production).

## Development notes
- All computed formulas (HP, attribute updates, risk) are implemented in `app.js` (frontend) and should be mirrored on the server for authoritative calculations if you rely on server state.
- The app computes body attribute values hourly; use a cron job or server scheduler if moving computations to the backend.

## Testing
- Update `TEST_GUIDE.md` whenever you change calculations, API behavior, or DB schema. Include test cases for:
   - Signup/login flows
   - Hourly attribute updates and HP caps
   - Gemini daily quest generation and summary output
   - Rankings calculation and leaderboard consistency

## Contributing
- Open issues or PRs. For changes affecting DB, API, or calculations: include migration steps, test cases, and update this `README.md`.

## Notes / TODO
- API connection currently reported as not working — see `API troubleshooting` above and check `api/gemini.php` and DB connectivity.
