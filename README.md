# Karinderya Quest

Karinderya Quest is a Filipino food RPG health tracker built for a hackathon-style MVP. Users log meals manually, the app converts those meals into stat changes, and daily quests reward healthy variety without using AI vision.

## What it does

- Logs Breakfast, Lunch, Dinner, and Snack entries.
- Uses a starter Filipino food database with stat effects.
- Turns meals into RPG progress for HP, Energy, Strength, Defense, and Risk.
- Generates daily quests like eating a vegetable dish or staying under 1500 calories.
- Suggests budget-friendly karinderya combos for ₱50, ₱100, and ₱150.
- Shows an end-of-day report and simple improvement tip.

## Files

- [index.php](index.php) is the entry page and PHP data bootstrap.
- [app.js](app.js) contains the RPG logic, quest engine, and local storage state.
- [styles.css](styles.css) contains the visual design.
- [schema.sql](schema.sql) defines the MySQL tables for users, food items, meal logs, and quests.

## Run locally

If PHP is installed, run this folder with a local PHP server and open the page in your browser:

```bash
php -S localhost:8000
```

If you want the MySQL side, import [schema.sql](schema.sql) and seed the `food_items` table with the sample dishes from [index.php](index.php).

## MVP notes

- This version intentionally avoids AI photo recognition.
- Meal logging is manual so the demo is fast, reliable, and easy to explain.
- Browser state is stored in localStorage for a no-backend demo path.