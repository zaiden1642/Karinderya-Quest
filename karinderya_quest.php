<?php
$foodItems = [
    [
        'id' => 'adobo',
        'name' => 'Adobo',
        'category' => 'Protein',
        'group' => 'Protein',
        'calories' => 320,
        'price' => 45,
        'effects' => ['hp' => 10, 'energy' => 6, 'strength' => 10, 'defense' => 1, 'risk' => 4, 'xp' => 18],
        'tags' => ['savory', 'stew', 'protein'],
        'ingredients' => ['Chicken', 'Garlic', 'Soy Sauce', 'Vinegar', 'Black Pepper', 'Oil']
    ],
    [
        'id' => 'sinigang',
        'name' => 'Sinigang',
        'category' => 'Soup / Vegetable',
        'group' => 'Vegetable',
        'calories' => 240,
        'price' => 55,
        'effects' => ['hp' => 12, 'energy' => 4, 'strength' => 2, 'defense' => 10, 'risk' => -3, 'xp' => 20],
        'tags' => ['broth', 'vegetable', 'balanced'],
        'ingredients' => ['Pork', 'Radish', 'String Beans', 'Onion', 'Garlic', 'Salt']
    ],
    [
        'id' => 'tinola',
        'name' => 'Tinola',
        'category' => 'Healthy',
        'group' => 'Balanced',
        'calories' => 260,
        'price' => 50,
        'effects' => ['hp' => 11, 'energy' => 5, 'strength' => 5, 'defense' => 7, 'risk' => -2, 'xp' => 18],
        'tags' => ['broth', 'vegetable', 'balanced'],
        'ingredients' => ['Chicken', 'Ginger', 'Papaya', 'Chili Leaves', 'Garlic', 'Salt']
    ],
    [
        'id' => 'karekare',
        'name' => 'Kare-kare',
        'category' => 'Heavy Meal',
        'group' => 'Heavy',
        'calories' => 520,
        'price' => 85,
        'effects' => ['hp' => 14, 'energy' => 7, 'strength' => 8, 'defense' => 4, 'risk' => 6, 'xp' => 24],
        'tags' => ['heavy', 'rich', 'protein'],
        'ingredients' => ['Peanut Butter', 'Beef', 'Onion', 'Garlic', 'Eggplant', 'String Beans']
    ],
    [
        'id' => 'kwek-kwek',
        'name' => 'Kwek-kwek',
        'category' => 'Street Food',
        'group' => 'Street Food',
        'calories' => 290,
        'price' => 25,
        'effects' => ['hp' => 4, 'energy' => 7, 'strength' => 1, 'defense' => -1, 'risk' => 10, 'xp' => 10],
        'tags' => ['fried', 'street', 'salty'],
        'ingredients' => ['Quail Eggs', 'Flour', 'Cornstarch', 'Oil']
    ],
    [
        'id' => 'pinakbet',
        'name' => 'Pinakbet',
        'category' => 'Vegetable',
        'group' => 'Vegetable',
        'calories' => 210,
        'price' => 40,
        'effects' => ['hp' => 9, 'energy' => 4, 'strength' => 2, 'defense' => 11, 'risk' => -2, 'xp' => 18],
        'tags' => ['vegetable', 'fiber', 'balanced'],
        'ingredients' => ['Eggplant', 'Okra', 'String Beans', 'Tomato', 'Garlic', 'Salt']
    ],
    [
        'id' => 'tortang-talong',
        'name' => 'Tortang Talong',
        'category' => 'Vegetable / Protein',
        'group' => 'Balanced',
        'calories' => 180,
        'price' => 35,
        'effects' => ['hp' => 8, 'energy' => 4, 'strength' => 5, 'defense' => 7, 'risk' => 1, 'xp' => 16],
        'tags' => ['vegetable', 'protein', 'home-cooked'],
        'ingredients' => ['Eggplant', 'Egg', 'Garlic', 'Onion', 'Tomato']
    ],
    [
        'id' => 'rice-egg',
        'name' => 'Rice + Fried Egg',
        'category' => 'Carb / Protein',
        'group' => 'Carb',
        'calories' => 340,
        'price' => 28,
        'effects' => ['hp' => 7, 'energy' => 8, 'strength' => 4, 'defense' => 1, 'risk' => 3, 'xp' => 12],
        'tags' => ['carb', 'protein', 'quick'],
        'ingredients' => ['Rice', 'Egg', 'Oil', 'Salt']
    ],
    [
        'id' => 'lumpia',
        'name' => 'Lumpiang Shanghai',
        'category' => 'Street Food',
        'group' => 'Street Food',
        'calories' => 260,
        'price' => 30,
        'effects' => ['hp' => 4, 'energy' => 6, 'strength' => 2, 'defense' => 0, 'risk' => 8, 'xp' => 11],
        'tags' => ['fried', 'street', 'crispy'],
        'ingredients' => ['Pork', 'Vegetables', 'Spring Roll Wrapper', 'Oil']
    ],
    [
        'id' => 'ginataang-kalabasa',
        'name' => 'Ginataang Kalabasa',
        'category' => 'Vegetable',
        'group' => 'Vegetable',
        'calories' => 250,
        'price' => 42,
        'effects' => ['hp' => 10, 'energy' => 4, 'strength' => 2, 'defense' => 9, 'risk' => 0, 'xp' => 17],
        'tags' => ['vegetable', 'comfort', 'balanced'],
        'ingredients' => ['Butternut Squash', 'Coconut Milk', 'Onion', 'Garlic', 'Salt']
    ],
    [
        'id' => 'sisig',
        'name' => 'Sisig',
        'category' => 'Protein',
        'group' => 'Protein',
        'calories' => 390,
        'price' => 60,
        'effects' => ['hp' => 9, 'energy' => 7, 'strength' => 9, 'defense' => 1, 'risk' => 8, 'xp' => 20],
        'tags' => ['protein', 'fried', 'salty'],
        'ingredients' => ['Pork Jowls', 'Liver', 'Onion', 'Chili', 'Egg', 'Salt']
    ],
    [
        'id' => 'buko-juice',
        'name' => 'Buko Juice',
        'category' => 'Drink',
        'group' => 'Drink',
        'calories' => 120,
        'price' => 22,
        'effects' => ['hp' => 5, 'energy' => 6, 'strength' => 0, 'defense' => 3, 'risk' => -1, 'xp' => 8],
        'tags' => ['drink', 'refreshing', 'light'],
        'ingredients' => ['Young Coconut', 'Water']
    ],
    [
        'id' => 'isaw',
        'name' => 'Isaw',
        'category' => 'Street Food',
        'group' => 'Street Food',
        'calories' => 200,
        'price' => 20,
        'effects' => ['hp' => 3, 'energy' => 5, 'strength' => 2, 'defense' => -1, 'risk' => 9, 'xp' => 9],
        'tags' => ['street', 'grill', 'salty'],
        'ingredients' => ['Pork Intestines', 'Vinegar', 'Garlic', 'Salt']
    ],
];

$initialData = [
    'appName' => 'Karinderya Quest',
    'tagline' => 'Turn Filipino food into an RPG health tracker.',
    'foodItems' => $foodItems,
];

function asset_version($relativePath) {
    $fullPath = __DIR__ . '/' . ltrim($relativePath, '/\\');
    return file_exists($fullPath) ? filemtime($fullPath) : time();
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Karinderya Quest is a Filipino food RPG health tracker built for fast hackathon demos.">
    <title>Karinderya Quest</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Jacquard+24&display=swap" rel="stylesheet">
<style>
@import url('https://fonts.googleapis.com/css2?family=Jacquard+12&family=Jersey+10&family=VT323&display=swap');
</style>
    <link rel="stylesheet" href="styles.css?v=<?php echo asset_version('styles.css'); ?>">
</head>
<body>
    <div class="aurora aurora-one"></div>
    <div class="aurora aurora-two"></div>

    <main class="app-shell">
        <section class="hero panel">
            <div class="hero-copy">
                <div class="eyebrow">Filipino Food RPG Health Tracker</div>
                <h1>Karinderya Quest</h1>
                <p class="hero-text">
                    Log meals manually, earn XP, and watch your health stats evolve through familiar Filipino dishes.
                </p>
                <div class="hero-meta">
                    <div>
                        <span class="meta-label">Day Streak</span>
                        <strong id="day-count">0 days</strong>
                    </div>
                    <div>
                        <span class="meta-label">Title</span>
                        <strong id="player-title">Street Rookie</strong>
                    </div>
                    <div>
                        <span class="meta-label">Wellness Score</span>
                        <strong id="wellness-score">--</strong>
                    </div>
                </div>
            </div>
            <div class="hero-status">
                <div class="status-ring">
                    <div>
                        <span class="meta-label">Level</span>
                        <strong id="player-level">1</strong>
                    </div>
                </div>
                <div class="xp-block">
                    <div class="xp-row">
                        <span>XP Progress</span>
                        <span id="xp-label">0 / 100</span>
                    </div>
                    <div class="xp-track"><div id="xp-fill"></div></div>
                </div>
            </div>
        </section>

        <nav class="section-switcher hidden" id="section-switcher" aria-label="App sections">
            <button type="button" class="section-tab active" data-app-section="nutrition">Nutrition</button>
            <button type="button" class="section-tab" data-app-section="rewards">Rewards</button>
            <button type="button" class="section-tab" data-app-section="settings">Settings</button>
        </nav>

        <section id="settings-section" class="app-section">
            <section class="panel auth-panel" id="auth-panel">
            <div class="auth-header">
                <div>
                    <span class="eyebrow">Account</span>
                    <h2>Sign up or log in</h2>
                </div>
                <div class="auth-toggle" id="auth-toggle">
                    <button type="button" data-auth-view="login" class="auth-tab active">Login</button>
                    <button type="button" data-auth-view="signup" class="auth-tab">Sign Up</button>
                </div>
            </div>

            <div id="auth-status" class="auth-status hidden"></div>

            <form id="login-form" class="auth-form">
                <label>
                    <span>Email</span>
                    <input id="login-email" type="email" required placeholder="you@example.com">
                </label>
                <label>
                    <span>Password</span>
                    <input id="login-password" type="password" required placeholder="Enter password">
                </label>
                <button type="submit" class="auth-btn">Login</button>
            </form>

            <form id="signup-form" class="auth-form hidden">
                <label>
                    <span>Username</span>
                    <input id="signup-username" type="text" minlength="3" required placeholder="e.g. ricewarrior">
                </label>
                <label>
                    <span>Email</span>
                    <input id="signup-email" type="email" required placeholder="you@example.com">
                </label>
                <label>
                    <span>Password</span>
                    <input id="signup-password" type="password" minlength="6" required placeholder="At least 6 characters">
                </label>
                <div class="auth-grid">
                    <label>
                        <span>Birthdate</span>
                        <input id="signup-birthdate" type="date" required>
                    </label>
                    <label>
                        <span>Height (cm)</span>
                        <input id="signup-height" type="number" min="80" max="260" step="1" required placeholder="170">
                    </label>
                    <label>
                        <span>Weight (kg)</span>
                        <input id="signup-weight" type="number" min="20" max="300" step="0.1" required placeholder="65">
                    </label>
                </div>
                <button type="submit" class="auth-btn">Create Account</button>
            </form>

            <div id="profile-view" class="profile-view hidden">
                <div class="profile-meta">
                    <h3 id="profile-name">User</h3>
                    <p id="profile-email">-</p>
                    <p id="profile-bmi">BMI: -</p>
                </div>

                <form id="profile-form" class="auth-form compact">
                    <div class="auth-grid">
                        <label>
                            <span>Birthdate</span>
                            <input id="profile-birthdate" type="date" required>
                        </label>
                        <label>
                            <span>Height (cm)</span>
                            <input id="profile-height" type="number" min="80" max="260" step="1" required>
                        </label>
                        <label>
                            <span>Weight (kg)</span>
                            <input id="profile-weight" type="number" min="20" max="300" step="0.1" required>
                        </label>
                    </div>
                    <div class="auth-actions">
                        <button type="submit" class="auth-btn">Update Profile</button>
                        <button type="button" id="logout-btn" class="auth-btn secondary">Logout</button>
                        <button type="button" id="delete-account-btn" class="auth-btn danger">Delete All Data & Reset</button>
                    </div>
                </form>
            </div>
            </section>
        </section>

        <section id="nutrition-section" class="app-section">
            <section class="dashboard-grid">
            <section class="panel stats-panel">
                <div class="panel-heading">
                    <div>
                        <span class="eyebrow">RPG Stats</span>
                        <h2>Health progression</h2>
                    </div>
                    <span class="subtle">Log meals manually so your stats stay accurate</span>
                </div>
                <div class="stats-grid" id="stats-grid"></div>
            </section>

            <section class="panel form-panel">
                <div class="panel-heading">
                    <div>
                        <span class="eyebrow">Log a Meal</span>
                        <h2>Build your daily loadout</h2>
                    </div>
                    <span class="subtle">Use custom dishes and edits to match what you actually ate</span>
                </div>

                <form id="meal-form" class="meal-form-wizard">
                    <!-- Step 1: Meal Type -->
                    <div class="wizard-step" data-step="1">
                        <label>
                            <span>Step 1: Select Meal Type</span>
                            <select id="meal-type" required>
                                <option value="">-- Choose a meal type --</option>
                                <option>Breakfast</option>
                                <option>Brunch</option>
                                <option>Lunch</option>
                                <option>Dinner</option>
                                <option>Snacks</option>
                            </select>
                        </label>
                        <button type="button" class="step-next-btn" data-next="2">Next</button>
                    </div>

                    <!-- Step 2: Main Dish Input -->
                    <div class="wizard-step hidden" data-step="2">
                        <label>
                            <span>Step 2: What did you eat?</span>
                            <p class="step-hint">Enter the main dish or food (e.g., "adobo", "rice", "chips")</p>
                            <input id="main-dish" type="text" placeholder="e.g., adobo, sinigang, rice with egg" autocomplete="off">
                            <div id="dish-suggestions" class="dish-suggestions"></div>
                        </label>
                        <div class="wizard-actions">
                            <button type="button" class="step-prev-btn" data-prev="1">Back</button>
                            <button type="button" class="step-next-btn" data-next="3">Next</button>
                        </div>
                    </div>

                    <!-- Step 3: Ingredient Verification (conditional) -->
                    <div class="wizard-step hidden" data-step="3">
                        <label>
                            <span>Step 3: Verify Ingredients</span>
                            <p class="step-hint" id="ingredients-hint">Please verify the ingredients are correct. You can add or remove them.</p>
                        </label>
                        <div id="ingredients-editor" class="ingredients-editor"></div>
                        <div class="wizard-actions">
                            <button type="button" class="step-prev-btn" data-prev="2">Back</button>
                            <button type="button" class="step-next-btn" data-next="4">Next</button>
                        </div>
                    </div>

                    <!-- Step 4: Final Details -->
                    <div class="wizard-step hidden" data-step="4">
                        <label>
                            <span>Step 4: Final Details</span>
                            <p class="step-hint">Add custom name or calories if needed (both optional)</p>
                        </label>
                        <label>
                            <span>Custom dish name (optional)</span>
                            <input id="custom-dish" type="text" placeholder="e.g., homemade adobo with extra vegetables">
                        </label>
                        <label>
                            <span>Estimated calories (optional)</span>
                            <input id="custom-calories" type="number" min="50" step="5" placeholder="Leave blank to auto-estimate">
                        </label>
                        <div class="wizard-actions">
                            <button type="button" class="step-prev-btn" data-prev="3">Back</button>
                            <button type="submit" class="primary-button" data-loading-label="Logging meal...">Log Meal + Gain XP</button>
                        </div>
                    </div>
                </form>
            </section>
        </section>

        <section class="dashboard-grid lower-grid">
            <section class="panel">
                <div class="panel-heading">
                    <div>
                        <span class="eyebrow">Daily Quests</span>
                        <h2>Today's mission board</h2>
                    </div>
                    <span class="subtle">Finish daily goals to unlock extra XP and rewards</span>
                </div>
                <div id="quest-list" class="quest-list"></div>
            </section>

            <section class="panel">
                <div class="panel-heading">
                    <div>
                        <span class="eyebrow">Health Quest Report</span>
                        <h2>End-of-day summary</h2>
                    </div>
                    <span class="subtle">Check the summary to see what affected your score today</span>
                </div>
                <div id="report-card" class="report-card"></div>
                <div class="archive-wrap">
                    <h3>Past day snapshots</h3>
                    <div id="archive-list" class="archive-list"></div>
                </div>
            </section>
        </section>

        <section class="dashboard-grid lower-grid" style="display: none;">
            <section class="panel">
                    <div class="panel-heading" style="display: none;">
                        <div>
                            <span class="eyebrow">Budget Builds</span>
                            <h2>Karinderya combos under budget</h2>
                        </div>
                        <span class="subtle">Pick lower-cost meal ideas when you want to stretch your budget</span>
                    </div>
                    <div id="budget-suggestions" class="combo-list" style="display: none;"></div>
            </section>

                <section class="panel" style="display: none;">
                    <div class="panel-heading" style="display: none;">
                        <div>
                            <span class="eyebrow">Filipino Food Database</span>
                            <h2>Starter dataset for MVP</h2>
                        </div>
                        <span class="subtle">Start from familiar Filipino foods and adjust from there</span>
                    </div>
                    <div id="food-database" class="food-grid" style="display: none;"></div>
                </section>
        </section>

        <section class="dashboard-grid lower-grid">
            <section class="panel rankings-panel">
                <div class="panel-heading">
                    <div>
                        <span class="eyebrow">Rankings</span>
                        <h2>Compete with the community</h2>
                    </div>
                    <span class="subtle">Compare your level, streak, and wellness with other players</span>
                </div>
                <div class="rankings-container" id="rankings-container"></div>
            </section>
        </section>

        <section class="panel log-panel">
            <div class="panel-heading">
                <div>
                    <span class="eyebrow">Meal Log</span>
                    <h2>Your streak history</h2>
                </div>
                <span class="subtle">Your meal history and progress are saved to your account</span>
            </div>
            <div id="meal-log" class="meal-log"></div>
        </section>
        </section>

        <section id="rewards-section" class="app-section hidden">
            <section class="panel rewards-panel">
                <div class="panel-heading">
                    <div>
                        <span class="eyebrow">Rewards</span>
                        <h2>Healthy food vouchers</h2>
                    </div>
                    <span class="subtle">Unlock rewards by hitting level, streak, or wellness goals</span>
                </div>
                <div id="rewards-list" class="rewards-list"></div>
            </section>
        </section>
    </main>

    <script>
        window.KARINDERYA_DATA = <?php echo json_encode($initialData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    </script>
    <script src="app.js?v=<?php echo asset_version('app.js'); ?>" defer></script>
</body>
</html>