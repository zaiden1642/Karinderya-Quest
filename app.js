const DATA = window.KARINDERYA_DATA;
const STORAGE_KEY = 'karinderya-quest-state-v1';
const LEVEL_THRESHOLDS = [0, 100, 250, 450, 700, 1000, 1350, 1750, 2200, 2700];
const LEVEL_TITLES = [
  'Street Rookie',
  'Suman Scout',
  'Calorie Cadet',
  'Broth Bard',
  'Karinderya Warrior',
  'Grill Guardian',
  'Veggie Vanguard',
  'Feast Strategist',
  'Balance Sage',
  'Nutrition Monk'
];

const STAT_META = [
  { key: 'hp', label: 'HP', accent: 'var(--accent-hp)' },
  { key: 'energy', label: 'Energy', accent: 'var(--accent-energy)' },
  { key: 'strength', label: 'Strength', accent: 'var(--accent-strength)' },
  { key: 'defense', label: 'Defense', accent: 'var(--accent-defense)' },
  { key: 'risk', label: 'Risk', accent: 'var(--accent-risk)' },
];

const QUEST_DEFS = [
  {
    id: 'vegetable',
    title: 'Eat 1 vegetable dish',
    description: 'Log a dish tagged as vegetable or balanced with veggies.',
    reward: 20,
    completed: (questState) => questState.hasVegetable,
    progress: (questState) => questState.hasVegetable ? 1 : 0,
    goal: 1,
  },
  {
    id: 'fried-free',
    title: 'Avoid fried food today',
    description: 'Keep your log free from fried street food choices.',
    reward: 20,
    completed: (questState) => !questState.hasFried,
    progress: (questState) => questState.hasFried ? 0 : 1,
    goal: 1,
  },
  {
    id: 'calorie-cap',
    title: 'Stay under 1500 calories',
    description: 'Keep the day balanced without overshooting the budget.',
    reward: 25,
    completed: (questState) => questState.dailyCalories <= 1500,
    progress: (questState) => Math.min(questState.dailyCalories, 1500),
    goal: 1500,
  },
  {
    id: 'food-groups',
    title: 'Eat 3 different food groups',
    description: 'Mix protein, vegetables, carbs, drinks, or street food wisely.',
    reward: 25,
    completed: (questState) => questState.foodGroups.size >= 3,
    progress: (questState) => Math.min(questState.foodGroups.size, 3),
    goal: 3,
  },
];

// ============ Gemini Helper Functions ============

/**
 * Call the Gemini API for food search
 */
async function searchFoodsWithGemini(query) {
  try {
    const res = await fetch('api/gemini.php?action=search', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ query }),
    });
    
    if (!res.ok) {
      console.warn('Food search returned non-OK', res.status);
      return [];
    }

    const json = await res.json();
    if (json.ok && json.results && Array.isArray(json.results)) {
      return json.results;
    }
    return [];
  } catch (err) {
    console.warn('searchFoodsWithGemini error', err);
    return [];
  }
}

/**
 * Call the Gemini API to get full food details
 */
async function getFoodDetailsFromGemini(foodName) {
  try {
    const res = await fetch(`api/gemini.php?action=getFood&name=${encodeURIComponent(foodName)}`);
    
    if (!res.ok) {
      console.warn('getFood returned non-OK', res.status);
      return null;
    }

    const json = await res.json();
    if (json.ok && json.food) {
      return json.food;
    }
    return null;
  } catch (err) {
    console.warn('getFoodDetailsFromGemini error', err);
    return null;
  }
}

/**
 * Call the Gemini API to estimate nutrients from ingredients
 */
async function estimateNutrientsWithGemini(ingredients) {
  try {
    // Format ingredients for Gemini
    const ingredientList = ingredients.map(ing => ({
      name: ing.name,
      servingSize: ing.servingSize || 1
    }));

    const res = await fetch('api/gemini.php?action=estimateNutrients', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ ingredients: ingredientList }),
    });
    
    if (!res.ok) {
      console.warn('Nutrient estimation returned non-OK', res.status);
      return null;
    }

    const json = await res.json();
    if (json.ok && json.nutrients) {
      return json.nutrients;
    }
    return null;
  } catch (err) {
    console.warn('estimateNutrientsWithGemini error', err);
    return null;
  }
}

/**
 * Call the Gemini API to generate RPG effects for a meal
 */
async function generateEffectsWithGemini(mealName, ingredients, calories) {
  try {
    const res = await fetch('api/gemini.php?action=generateEffects', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        mealName,
        ingredients,
        calories,
      }),
    });
    
    if (!res.ok) {
      console.warn('Effect generation returned non-OK', res.status);
      return null;
    }

    const json = await res.json();
    if (json.ok && json.effects) {
      // Ensure effects are clamped to reasonable ranges
      return {
        hp: Math.max(0, Math.min(20, json.effects.hp || 0)),
        energy: Math.max(0, Math.min(20, json.effects.energy || 0)),
        strength: Math.max(0, Math.min(20, json.effects.strength || 0)),
        defense: Math.max(0, Math.min(20, json.effects.defense || 0)),
        risk: Math.max(0, Math.min(20, json.effects.risk || 0)),
        xp: Math.max(8, Math.min(30, json.effects.xp || 12)),
      };
    }
    return null;
  } catch (err) {
    console.warn('generateEffectsWithGemini error', err);
    return null;
  }
}

const state = loadState();
const nodes = {};
let wizardState = {
  currentStep: 1,
  selectedDish: null,
  selectedIngredients: [],
  mainDishInput: '',
  ingredientServingSizes: {}, // e.g. { 'Chicken': 1.5, 'Rice': 1 }
  mealCalories: 0,
};

document.addEventListener('DOMContentLoaded', () => {
  cacheNodes();
  bindEvents();
  syncDay();
  render();
  initializeWizard();
});

function cacheNodes() {
  nodes.dayCount = document.getElementById('day-count');
  nodes.playerTitle = document.getElementById('player-title');
  nodes.playerLevel = document.getElementById('player-level');
  nodes.wellnessScore = document.getElementById('wellness-score');
  nodes.xpLabel = document.getElementById('xp-label');
  nodes.xpFill = document.getElementById('xp-fill');
  nodes.statsGrid = document.getElementById('stats-grid');
  nodes.questList = document.getElementById('quest-list');
  nodes.reportCard = document.getElementById('report-card');
  nodes.archiveList = document.getElementById('archive-list');
  nodes.budgetSuggestions = document.getElementById('budget-suggestions');
  nodes.foodDatabase = document.getElementById('food-database');
  nodes.mealLog = document.getElementById('meal-log');
  nodes.mealForm = document.getElementById('meal-form');
  nodes.mealType = document.getElementById('meal-type');
  nodes.mainDish = document.getElementById('main-dish');
  nodes.dishSuggestions = document.getElementById('dish-suggestions');
  nodes.ingredientsEditor = document.getElementById('ingredients-editor');
  nodes.customDish = document.getElementById('custom-dish');
  nodes.customCalories = document.getElementById('custom-calories');
}

function bindEvents() {
  nodes.mealForm.addEventListener('submit', (e) => {
    handleMealSubmit(e).catch(err => {
      console.error('Meal submission error:', err);
      alert('Error submitting meal. Please try again.');
    });
  });
  
  // Wizard navigation
  document.querySelectorAll('.step-next-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const nextStep = Number(btn.dataset.next);
      if (nextStep === 2 && nodes.mealType.value === '') {
        alert('Please select a meal type');
        return;
      }
      if (nextStep === 3 && nodes.mainDish.value.trim() === '') {
        alert('Please enter what you ate');
        return;
      }
      if (nextStep === 3) {
        processMainDishInput();
      }
      goToStep(nextStep);
    });
  });

  document.querySelectorAll('.step-prev-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      goToStep(Number(btn.dataset.prev));
    });
  });

  // Main dish autocomplete - use Gemini search
  nodes.mainDish.addEventListener('input', async (e) => {
    const query = e.target.value.trim().toLowerCase();
    wizardState.mainDishInput = e.target.value.trim();
    
    if (query.length < 1) {
      nodes.dishSuggestions.innerHTML = '';
      return;
    }

    // Show loading state
    nodes.dishSuggestions.innerHTML = '<div style="color: var(--muted); padding: 12px; font-size: 0.9rem;">Searching...</div>';

    // Try Gemini search first, then fallback to local filter
    let matches = await searchFoodsWithGemini(query);
    
    if (matches.length === 0) {
      // Fallback to local database filter if Gemini returns nothing
      matches = DATA.foodItems.filter(item => 
        item.name.toLowerCase().includes(query) || item.category.toLowerCase().includes(query)
      );
    }

    if (matches.length === 0) {
      nodes.dishSuggestions.innerHTML = '<div style="color: var(--muted); padding: 12px; font-size: 0.9rem;">No matches found. You can proceed with a custom dish.</div>';
      return;
    }

    // Display results from Gemini or local database
    nodes.dishSuggestions.innerHTML = matches.map((item, idx) => {
      const displayName = item.name || item.displayName;
      const category = item.category || item.estimatedCalories ? `~${item.estimatedCalories} cal` : 'Filipino Dish';
      return `<div class="suggestion-item" data-suggestion-index="${idx}"><strong>${displayName}</strong> · ${category}</div>`;
    }).join('');

    nodes.dishSuggestions.querySelectorAll('.suggestion-item').forEach((item, idx) => {
      item.addEventListener('click', async () => {
        const selectedItem = matches[idx];
        nodes.mainDish.value = selectedItem.name || selectedItem.displayName;
        nodes.dishSuggestions.innerHTML = '';
        
        // Try to get full details from Gemini
        const fullDetails = await getFoodDetailsFromGemini(selectedItem.name || selectedItem.displayName);
        if (fullDetails) {
          wizardState.selectedDish = fullDetails;
          wizardState.selectedIngredients = [...(fullDetails.ingredients || [])];
          wizardState.ingredientServingSizes = {};
          (fullDetails.ingredients || []).forEach(ing => {
            wizardState.ingredientServingSizes[ing] = 1;
          });
        } else {
          // Fallback: treat as selected from local database
          const localDish = DATA.foodItems.find(d => d.name.toLowerCase() === (selectedItem.name || selectedItem.displayName).toLowerCase());
          if (localDish) {
            wizardState.selectedDish = localDish;
            wizardState.selectedIngredients = [...(localDish.ingredients || [])];
            wizardState.ingredientServingSizes = {};
            (localDish.ingredients || []).forEach(ing => {
              wizardState.ingredientServingSizes[ing] = 1;
            });
          }
        }
      });
    });
  });
}

function loadState() {
  const today = dateKey(new Date());
  const stored = safeParse(localStorage.getItem(STORAGE_KEY));

  if (!stored) {
    return createDefaultState(today);
  }

  const next = {
    ...createDefaultState(today),
    ...stored,
    stats: { ...createDefaultState(today).stats, ...(stored.stats || {}) },
    logs: Array.isArray(stored.logs) ? stored.logs : [],
    archive: Array.isArray(stored.archive) ? stored.archive : [],
    rewardedQuestIds: Array.isArray(stored.rewardedQuestIds) ? stored.rewardedQuestIds : [],
  };

  next.dayCount = Number(next.dayCount || 1);
  next.lastVisited = stored.lastVisited || today;
  return next;
}

function createDefaultState(today) {
  return {
    version: 1,
    lastVisited: today,
    dayCount: 1,
    xp: 0,
    stats: {
      hp: 75,
      energy: 60,
      strength: 50,
      defense: 45,
      risk: 10,
    },
    logs: [],
    archive: [],
    rewardedQuestIds: [],
  };
}

function syncDay() {
  const today = dateKey(new Date());

  if (state.lastVisited === today) {
    return;
  }

  const daysPassed = Math.max(1, Math.round((new Date(today) - new Date(state.lastVisited)) / 86400000));

  if (state.logs.length) {
    state.archive.unshift(buildArchiveEntry(state.lastVisited));
    state.archive = state.archive.slice(0, 4);
  }

  state.logs = [];
  state.rewardedQuestIds = [];
  state.dayCount += daysPassed;
  state.lastVisited = today;
  persist();
}

async function handleMealSubmit(event) {
  event.preventDefault();

  let dish;
  let calculatedCalories = 0;
  
  if (wizardState.selectedDish) {
    // Use database dish with Gemini-estimated calories
    dish = wizardState.selectedDish;
    calculatedCalories = await estimateMealCaloriesWithGemini();
  } else {
    // Build custom dish with Gemini-generated effects
    const customName = nodes.customDish.value.trim();
    const customCalories = Number(nodes.customCalories.value);
    dish = await buildCustomDish(
      customName || nodes.mainDish.value.trim(),
      Number.isFinite(customCalories) && customCalories > 0 ? customCalories : null
    );
    calculatedCalories = dish.calories;
  }

  // Allow user to override calculated calories in custom calories field
  const overrideCalories = Number(nodes.customCalories.value);
  const finalCalories = Number.isFinite(overrideCalories) && overrideCalories > 0 ? overrideCalories : calculatedCalories;

  // Ensure effects are set (use Gemini effects if available, otherwise use dish effects)
  let finalEffects = dish.effects;
  if (!finalEffects || !finalEffects.hp) {
    const geminiEffects = await generateEffectsWithGemini(dish.name, wizardState.selectedIngredients, finalCalories);
    finalEffects = geminiEffects || { hp: 6, energy: 5, strength: 3, defense: 3, risk: 2, xp: 12 };
  }

  const entry = {
    id: createEntryId(),
    mealType: nodes.mealType.value,
    name: dish.name,
    category: dish.category,
    calories: finalCalories,
    price: dish.price,
    effects: finalEffects,
    tags: dish.tags,
    ingredients: wizardState.selectedIngredients.filter(i => i.trim()),
    ingredientServingSizes: { ...wizardState.ingredientServingSizes },
    ingredientBreakdown: buildIngredientBreakdown(),
    source: wizardState.selectedDish ? 'database' : 'custom',
    createdAt: new Date().toISOString(),
  };

  state.logs.unshift(entry);
  applyEffects(entry.effects);
  awardMealXp(entry.effects.xp || 10);
  awardQuestXp();
  persist();
  render();
  
  // Send meal to Gemini summarization (non-blocking)
  if (typeof sendToGemini === 'function') {
    sendToGemini(entry).catch(() => {});
  }

  // Reset wizard
  resetWizard();
  goToStep(1);
}

// Send meal entry to server-side Gemini proxy to generate a short summary
async function sendToGemini(entry) {
  try {
    const res = await fetch('/api/gemini.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(entry),
    });

    if (!res.ok) {
      console.warn('Gemini proxy returned non-OK', res.status);
      return null;
    }

    const json = await res.json();
    // Attach returned summary to the saved log entry and persist
    const idx = state.logs.findIndex(l => l.id === entry.id);
    if (idx !== -1) {
      state.logs[idx].gemini = json;
      persist();
      render();
    }

    return json;
  } catch (err) {
    console.warn('sendToGemini error', err);
    return null;
  }
}

function buildIngredientBreakdown() {
  const ingredientDb = DATA.ingredients || {};
  const breakdown = [];
  
  wizardState.selectedIngredients.forEach(ingredientName => {
    const info = ingredientDb[ingredientName] || { category: 'Other', baseCalories: 100, unit: 'serving' };
    const servingSize = wizardState.ingredientServingSizes[ingredientName] || 1;
    const calories = Math.round(info.baseCalories * servingSize);
    
    breakdown.push({
      name: ingredientName,
      category: info.category,
      servingSize: servingSize,
      baseCalories: info.baseCalories,
      calories: calories,
      unit: info.unit
    });
  });
  
  return breakdown;
}

function resetWizard() {
  nodes.mealType.value = '';
  nodes.mainDish.value = '';
  nodes.customDish.value = '';
  nodes.customCalories.value = '';
  nodes.dishSuggestions.innerHTML = '';
  wizardState = {
    currentStep: 1,
    selectedDish: null,
    selectedIngredients: [],
    mainDishInput: '',
    ingredientServingSizes: {},
    mealCalories: 0,
  };
}

function initializeWizard() {
  goToStep(1);
}

function goToStep(step) {
  document.querySelectorAll('.wizard-step').forEach(el => {
    if (Number(el.dataset.step) === step) {
      el.classList.remove('hidden');
    } else {
      el.classList.add('hidden');
    }
  });
  wizardState.currentStep = step;

  if (step === 3) {
    renderIngredientsEditor();
  }
  
  if (step === 4) {
    updateStep4Display();
  }
}

async function updateStep4Display() {
  // Update the calories placeholder to show the calculated amount
  const caloriesInput = nodes.customCalories;
  let calculatedCals = wizardState.mealCalories || calculateMealCalories();
  
  // If we have a selected dish with ingredients, try Gemini estimation
  if (wizardState.selectedDish && wizardState.selectedIngredients.length > 0) {
    const geminiEstimate = await estimateMealCaloriesWithGemini();
    if (geminiEstimate > 0) {
      calculatedCals = geminiEstimate;
    }
  }
  
  caloriesInput.placeholder = `Leave blank to use calculated: ${calculatedCals} cal`;
}

function processMainDishInput() {
  const input = nodes.mainDish.value.trim().toLowerCase();
  
  // Try to find exact or partial match in database
  const match = DATA.foodItems.find(item => 
    item.name.toLowerCase() === input || item.name.toLowerCase().includes(input)
  );

  if (match) {
    wizardState.selectedDish = match;
    wizardState.selectedIngredients = [...(match.ingredients || [])];
    // Initialize serving sizes to 1x for all ingredients
    wizardState.ingredientServingSizes = {};
    match.ingredients.forEach(ing => {
      wizardState.ingredientServingSizes[ing] = 1;
    });
    wizardState.mealCalories = calculateMealCalories();
  } else {
    // No database match - clear selections for custom dish
    wizardState.selectedDish = null;
    wizardState.selectedIngredients = [];
    wizardState.ingredientServingSizes = {};
    wizardState.mealCalories = 0;
  }
}

function calculateMealCalories() {
  const ingredientDb = DATA.ingredients || {};
  let totalCalories = 0;
  
  wizardState.selectedIngredients.forEach(ingredientName => {
    const info = ingredientDb[ingredientName] || { baseCalories: 100 };
    const servingSize = wizardState.ingredientServingSizes[ingredientName] || 1;
    totalCalories += info.baseCalories * servingSize;
  });
  
  return Math.round(totalCalories);
}

/**
 * Async version: use Gemini to estimate calories more accurately
 */
async function estimateMealCaloriesWithGemini() {
  if (wizardState.selectedIngredients.length === 0) {
    return 0;
  }

  const ingredients = wizardState.selectedIngredients.map(name => ({
    name,
    servingSize: wizardState.ingredientServingSizes[name] || 1
  }));

  const nutrients = await estimateNutrientsWithGemini(ingredients);
  if (nutrients && nutrients.calories) {
    wizardState.mealCalories = Math.round(nutrients.calories);
    return wizardState.mealCalories;
  }

  // Fallback to local calculation
  return calculateMealCalories();
}

function renderIngredientsEditor() {
  if (!wizardState.selectedDish) {
    nodes.ingredientsEditor.innerHTML = '<div style="color: var(--muted); padding: 16px; text-align: center;">No matching dish found. Proceeding with custom dish entry.</div>';
    return;
  }

  // Group ingredients by category
  const ingredientDb = DATA.ingredients || {};
  const grouped = {};
  
  wizardState.selectedIngredients.forEach(ingredientName => {
    const info = ingredientDb[ingredientName] || { category: 'Other', baseCalories: 100, unit: 'serving' };
    const category = info.category;
    if (!grouped[category]) {
      grouped[category] = [];
    }
    grouped[category].push({ name: ingredientName, ...info });
  });

  // Build HTML with serving size controls
  let html = '';
  const categoryOrder = ['Protein', 'Carb', 'Vegetable', 'Liquid', 'Drink', 'Condiment', 'Spice', 'Other'];
  
  categoryOrder.forEach(category => {
    if (!grouped[category]) return;
    
    html += `<div style="margin-bottom: 20px;">
      <h3 style="color: var(--accent-2); font-size: 0.95rem; margin-bottom: 12px; font-weight: 600;">${category}</h3>`;
    
    grouped[category].forEach((ingredient, idx) => {
      const servingSize = wizardState.ingredientServingSizes[ingredient.name] || 1;
      const calories = Math.round(ingredient.baseCalories * servingSize);
      
      html += `
        <div class="ingredient-item">
          <div style="flex: 1;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
              <span style="font-weight: 500;">${ingredient.name}</span>
              <span style="color: var(--muted); font-size: 0.85rem;">${ingredient.unit}</span>
            </div>
            <div style="display: flex; gap: 12px; align-items: center;">
              <input type="range" min="0.5" max="3" step="0.5" value="${servingSize}" data-ingredient="${ingredient.name}" class="ingredient-size-slider" style="flex: 1; min-width: 120px;">
              <span style="min-width: 60px; text-align: right;">
                <strong>${servingSize}x</strong>
              </span>
              <span style="min-width: 50px; text-align: right; color: var(--accent-2); font-weight: 600;">
                ${calories}<span style="font-size: 0.85rem;">cal</span>
              </span>
            </div>
          </div>
          <button type="button" class="ingredient-remove" data-ingredient="${ingredient.name}" style="padding: 6px 10px; margin-top: 8px;">Remove</button>
        </div>
      `;
    });
    
    html += `</div>`;
  });

  html += `<button type="button" class="ingredient-add-btn">+ Add/Modify Ingredient</button>`;
  
  nodes.ingredientsEditor.innerHTML = html;

  // Bind serving size slider changes
  nodes.ingredientsEditor.querySelectorAll('.ingredient-size-slider').forEach(slider => {
    slider.addEventListener('input', (e) => {
      const ingredientName = e.target.dataset.ingredient;
      const newSize = Number(e.target.value);
      wizardState.ingredientServingSizes[ingredientName] = newSize;
      wizardState.mealCalories = calculateMealCalories();
      renderIngredientsEditor(); // Re-render to update calorie display
    });
  });

  // Bind remove buttons
  nodes.ingredientsEditor.querySelectorAll('.ingredient-remove').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const ingredientName = btn.dataset.ingredient;
      wizardState.selectedIngredients = wizardState.selectedIngredients.filter(i => i !== ingredientName);
      delete wizardState.ingredientServingSizes[ingredientName];
      renderIngredientsEditor();
    });
  });

  // Bind add button
  const addBtn = nodes.ingredientsEditor.querySelector('.ingredient-add-btn');
  if (addBtn) {
    addBtn.addEventListener('click', (e) => {
      e.preventDefault();
      const newIngredient = prompt('Enter ingredient name (or choose from: Chicken, Pork, Rice, Onion, Garlic, Tomato, Oil, etc.):');
      if (newIngredient && newIngredient.trim()) {
        const trimmed = newIngredient.trim();
        if (!wizardState.selectedIngredients.includes(trimmed)) {
          wizardState.selectedIngredients.push(trimmed);
          wizardState.ingredientServingSizes[trimmed] = 1;
        }
        renderIngredientsEditor();
      }
    });
  }
  
  // Calculate and display total meal calories
  wizardState.mealCalories = calculateMealCalories();
}

async function buildCustomDish(name, calories) {
  const normalized = name.toLowerCase();
  const baseEffects = { hp: 6, energy: 5, strength: 3, defense: 3, risk: 2, xp: 12 };
  const tags = ['custom'];
  let category = 'Custom';
  let price = calories ? Math.max(15, Math.round(calories / 8)) : 35;

  // Try to get effects from Gemini
  const geminiEffects = await generateEffectsWithGemini(name, [], calories || 300);
  if (geminiEffects) {
    return {
      name: name.trim(),
      category: 'Custom (AI Generated)',
      calories: calories || 300,
      price,
      effects: geminiEffects,
      tags,
    };
  }

  // Fallback to local heuristics if Gemini fails
  let effects = { ...baseEffects };

  if (/(gulay|pinakbet|talong|kangkong|laing|sayote|kalabasa|ampalaya|vegetable)/.test(normalized)) {
    effects.defense += 7;
    effects.hp += 4;
    effects.risk -= 2;
    category = 'Vegetable';
    tags.push('vegetable');
  }

  if (/(fried|pritong|kwek|lumpia|crispy|sisig|chicharon|longganisa|tocino|isaw)/.test(normalized)) {
    effects.risk += 7;
    effects.energy += 4;
    category = 'Street Food';
    tags.push('fried');
  }

  if (/(adobo|manok|chicken|beef|pork|isda|tilapia|bangus|egg|itlog|tuna)/.test(normalized)) {
    effects.strength += 7;
    category = category === 'Vegetable' ? 'Balanced' : 'Protein';
    tags.push('protein');
  }

  if (/(rice|kanin|pancit|noodle|lugaw|sopas|bread|pandesal|siopao)/.test(normalized)) {
    effects.energy += 7;
    category = category === 'Protein' ? 'Balanced' : 'Carb';
    tags.push('carb');
  }

  if (/(sinigang|tinola|nilaga|sabaw|broth|soup)/.test(normalized)) {
    effects.hp += 8;
    effects.defense += 3;
    category = 'Healthy';
    tags.push('broth', 'vegetable');
  }

  const inferredCalories = calories || Math.max(120, Math.round((effects.energy + effects.strength + effects.hp) * 18));

  return {
    name: name.trim(),
    category,
    calories: inferredCalories,
    price,
    effects,
    tags,
  };
}

function applyEffects(effects) {
  state.stats.hp = clamp(state.stats.hp + effects.hp, 0, 100);
  state.stats.energy = clamp(state.stats.energy + effects.energy, 0, 100);
  state.stats.strength = clamp(state.stats.strength + effects.strength, 0, 100);
  state.stats.defense = clamp(state.stats.defense + effects.defense, 0, 100);
  state.stats.risk = clamp(state.stats.risk + effects.risk, 0, 100);
}

function awardMealXp(amount) {
  state.xp += Math.max(0, amount);
}

function awardQuestXp() {
  const questState = computeQuestState();

  QUEST_DEFS.forEach((quest) => {
    const alreadyClaimed = state.rewardedQuestIds.includes(quest.id);
    if (!alreadyClaimed && quest.completed(questState)) {
      state.rewardedQuestIds.push(quest.id);
      state.xp += quest.reward;
    }
  });
}

function render() {
  const questState = computeQuestState();
  renderHero();
  renderStats();
  renderQuests(questState);
  renderReport(questState);
  renderBudgetSuggestions();
  renderFoodDatabase();
  renderMealLog();
  renderArchive();
}

function renderHero() {
  const levelInfo = getLevelInfo(state.xp);
  const nextThreshold = LEVEL_THRESHOLDS[levelInfo.level] || LEVEL_THRESHOLDS[LEVEL_THRESHOLDS.length - 1] + 500;
  const currentThreshold = LEVEL_THRESHOLDS[levelInfo.level - 1] || 0;
  const progress = Math.min(1, (state.xp - currentThreshold) / Math.max(1, nextThreshold - currentThreshold));

  nodes.dayCount.textContent = `Day ${state.dayCount}`;
  nodes.playerTitle.textContent = levelInfo.title;
  nodes.playerLevel.textContent = String(levelInfo.level);
  nodes.wellnessScore.textContent = String(getWellnessScore());
  nodes.xpLabel.textContent = `${state.xp} / ${nextThreshold}`;
  nodes.xpFill.style.width = `${Math.round(progress * 100)}%`;
}

function renderStats() {
  nodes.statsGrid.innerHTML = STAT_META.map((stat) => {
    const value = state.stats[stat.key];
    return `
      <article class="stat-card">
        <div class="stat-head">
          <span>${stat.label}</span>
          <strong>${value}</strong>
        </div>
        <div class="bar-track"><div class="bar-fill" style="width:${value}%; background:${stat.accent};"></div></div>
      </article>
    `;
  }).join('');
}

function renderQuests(questState) {
  nodes.questList.innerHTML = QUEST_DEFS.map((quest) => {
    const done = quest.completed(questState);
    const progress = quest.progress(questState);
    const percent = Math.min(100, Math.round((progress / quest.goal) * 100));
    return `
      <article class="quest-card ${done ? 'is-complete' : ''}">
        <div class="quest-top">
          <div>
            <h3>${quest.title}</h3>
            <p>${quest.description}</p>
          </div>
          <span class="reward">+${quest.reward} XP</span>
        </div>
        <div class="quest-progress">
          <div class="bar-track"><div class="bar-fill" style="width:${percent}%"></div></div>
          <div class="quest-meta">
            <span>${done ? 'Completed' : `${progress} / ${quest.goal}`}</span>
            <span>${done ? 'Bonus claimed' : 'Quest active'}</span>
          </div>
        </div>
      </article>
    `;
  }).join('');
}

function renderReport(questState) {
  const summary = buildReportSummary(questState);
  nodes.reportCard.innerHTML = `
    <div class="report-hero">
      <h3>You survived ${summary.dayLabel}</h3>
      <p>${summary.motivation}</p>
    </div>
    <div class="report-grid">
      <div><span>Meals Logged</span><strong>${summary.meals}</strong></div>
      <div><span>Calories</span><strong>${summary.calories}</strong></div>
      <div><span>Food Groups</span><strong>${summary.groups}</strong></div>
      <div><span>Quest XP</span><strong>${summary.questXp}</strong></div>
    </div>
    <div class="report-tip">
      <span>Suggested improvement</span>
      <p>${summary.tip}</p>
    </div>
  `;
}

function renderBudgetSuggestions() {
  const combos = generateBudgetCombos(100);
  nodes.budgetSuggestions.innerHTML = combos.map((combo, index) => `
    <article class="combo-card ${index === 0 ? 'featured' : ''}">
      <div class="combo-top">
        <h3>${combo.title}</h3>
        <span>₱${combo.cost}</span>
      </div>
      <p>${combo.description}</p>
      <div class="chip-row">
        ${combo.items.map((item) => `<span class="chip">${item.name}</span>`).join('')}
      </div>
      <div class="combo-footer">
        <span>Score ${combo.score}</span>
        <span>${combo.calories} cal</span>
      </div>
    </article>
  `).join('');
}

function renderFoodDatabase() {
  nodes.foodDatabase.innerHTML = DATA.foodItems.map((item) => `
    <article class="food-card">
      <div class="food-top">
        <h3>${item.name}</h3>
        <span>₱${item.price}</span>
      </div>
      <p>${item.category}</p>
      <div class="chip-row">
        ${item.tags.map((tag) => `<span class="chip">${tag}</span>`).join('')}
      </div>
      <div class="effect-grid">
        <span>HP ${signed(item.effects.hp)}</span>
        <span>Energy ${signed(item.effects.energy)}</span>
        <span>Strength ${signed(item.effects.strength)}</span>
        <span>Defense ${signed(item.effects.defense)}</span>
        <span>Risk ${signed(item.effects.risk)}</span>
      </div>
    </article>
  `).join('');
}

function renderMealLog() {
  if (!state.logs.length) {
    nodes.mealLog.innerHTML = '<div class="empty-state">No meals logged yet. Log your first dish to start the quest.</div>';
    return;
  }

  nodes.mealLog.innerHTML = state.logs.map((log) => {
    let ingredientDisplay = '';
    if (log.ingredientBreakdown && log.ingredientBreakdown.length > 0) {
      ingredientDisplay = `<div style="margin-top: 8px; padding-top: 8px; border-top: 1px solid rgba(255,255,255,0.1); font-size: 0.85rem; color: var(--muted);">
        ${log.ingredientBreakdown.map(ing => `<div style="margin-bottom: 4px;"><strong>${ing.name}</strong> (${ing.servingSize}x) — ${ing.calories} cal</div>`).join('')}
      </div>`;
    }
    
    return `
      <article class="log-card">
        <div class="log-top">
          <div>
            <h3>${log.mealType}</h3>
            <p>${log.name} · ${log.category}</p>
            ${ingredientDisplay}
          </div>
          <span>${log.calories} cal</span>
        </div>
        <div class="effect-grid compact">
          <span>HP ${signed(log.effects.hp)}</span>
          <span>Energy ${signed(log.effects.energy)}</span>
          <span>Strength ${signed(log.effects.strength)}</span>
          <span>Defense ${signed(log.effects.defense)}</span>
          <span>Risk ${signed(log.effects.risk)}</span>
        </div>
      </article>
    `;
  }).join('');
}

function renderArchive() {
  if (!state.archive.length) {
    nodes.archiveList.innerHTML = '<div class="empty-state subtle-box">Past day snapshots will appear here after the app rolls over to a new date.</div>';
    return;
  }

  nodes.archiveList.innerHTML = state.archive.map((item) => `
    <article class="archive-card">
      <strong>${item.dayLabel}</strong>
      <p>${item.summary}</p>
      <span>${item.calories} cal · ${item.meals} meals · score ${item.score}</span>
    </article>
  `).join('');
}

function buildReportSummary(questState) {
  const calories = questState.dailyCalories;
  const meals = state.logs.length;
  const groups = questState.foodGroups.size;
  const questXp = QUEST_DEFS.filter((quest) => quest.completed(questState) && state.rewardedQuestIds.includes(quest.id)).reduce((sum, quest) => sum + quest.reward, 0);

  return {
    dayLabel: `Day ${state.dayCount}`,
    meals,
    calories,
    groups,
    questXp,
    motivation: meals ? `Your current build leans ${questState.bestStat}. The stats are telling a Filipino breakfast-to-dinner story.` : 'Start with a single meal log to unlock your first RPG stat swing.',
    tip: questState.tip,
  };
}

function buildArchiveEntry(lastVisited) {
  const questState = computeQuestState();
  const summary = buildReportSummary(questState);
  return {
    dayLabel: `Day ${state.dayCount}`,
    summary: `${summary.motivation} ${summary.tip}`,
    calories: summary.calories,
    meals: summary.meals,
    score: getWellnessScore(),
    date: lastVisited,
  };
}

function computeQuestState() {
  const dailyCalories = state.logs.reduce((sum, log) => sum + log.calories, 0);
  const foodGroups = new Set(state.logs.map((log) => log.group || getFoodGroup(log)));
  const hasVegetable = state.logs.some((log) => log.tags.includes('vegetable'));
  const hasFried = state.logs.some((log) => log.tags.includes('fried'));
  const bestStat = getBestStatName();

  return {
    dailyCalories,
    foodGroups,
    hasVegetable,
    hasFried,
    bestStat,
    tip: buildTip({ dailyCalories, foodGroups, hasVegetable, hasFried }),
  };
}

function buildTip(questState) {
  if (!questState.dailyCalories) {
    return 'Log a meal to start building your RPG health bar.';
  }

  if (!questState.hasVegetable) {
    return 'Try adding a vegetable dish tomorrow to push your Defense stat higher.';
  }

  if (questState.hasFried) {
    return 'Swap one fried item for sinigang or tinola to lower Risk and recover faster.';
  }

  if (questState.dailyCalories > 1500) {
    return 'Trim one heavy meal or reduce rice to stay under the 1500 calorie quest limit.';
  }

  if (questState.foodGroups.size < 3) {
    return 'Mix in a new food group so your loadout feels more balanced.';
  }

  return 'Keep your current pattern. That is a strong balance build for a hackathon demo.';
}

function generateBudgetCombos(budget) {
  const combos = [];
  const items = DATA.foodItems;

  for (let i = 0; i < items.length; i += 1) {
    for (let j = i; j < items.length; j += 1) {
      for (let k = j; k < items.length; k += 1) {
        const group = [items[i], items[j], items[k]].filter((item, index, array) => array.indexOf(item) === index);
        if (!group.length || group.length > 3) {
          continue;
        }

        const cost = group.reduce((sum, item) => sum + item.price, 0);
        if (cost > budget) {
          continue;
        }

        const calories = group.reduce((sum, item) => sum + item.calories, 0);
        const score = Math.round(group.reduce((sum, item) => sum + comboScore(item), 0));
        combos.push({
          title: group.map((item) => item.name).join(' + '),
          description: comboDescription(group),
          items: group,
          cost,
          calories,
          score,
        });
      }
    }
  }

  const unique = [];
  const seen = new Set();

  combos.sort((a, b) => b.score - a.score || a.cost - b.cost || a.items.length - b.items.length);
  combos.forEach((combo) => {
    const key = combo.items.map((item) => item.id).sort().join('-');
    if (!seen.has(key)) {
      seen.add(key);
      unique.push(combo);
    }
  });

  if (!unique.length) {
    return DATA.foodItems.slice(0, 3).map((item) => ({
      title: item.name,
      description: item.category,
      items: [item],
      cost: item.price,
      calories: item.calories,
      score: comboScore(item),
    }));
  }

  return unique.slice(0, 3);
}

function comboScore(item) {
  return (item.effects.hp * 1.1)
    + (item.effects.energy * 1)
    + (item.effects.strength * 1.3)
    + (item.effects.defense * 1.2)
    - (item.effects.risk * 1.4)
    + (item.tags.includes('vegetable') ? 4 : 0)
    + (item.tags.includes('balanced') ? 2 : 0)
    - (item.tags.includes('fried') ? 5 : 0);
}

function comboDescription(items) {
  const labels = items.map((item) => item.category).join(' / ');
  if (items.some((item) => item.tags.includes('vegetable')) && items.some((item) => item.tags.includes('protein'))) {
    return `Balanced build with ${labels.toLowerCase()}.`;
  }

  if (items.some((item) => item.tags.includes('fried'))) {
    return 'High-energy street build that comes with a Risk warning.';
  }

  return 'Practical karinderya build for the selected budget.';
}

function getFoodGroup(log) {
  const matched = DATA.foodItems.find((item) => item.name === log.name);
  return matched ? matched.group : 'Custom';
}

function getLevelInfo(xp) {
  let level = 1;
  for (let index = 0; index < LEVEL_THRESHOLDS.length; index += 1) {
    if (xp >= LEVEL_THRESHOLDS[index]) {
      level = index + 1;
    }
  }
  return { level, title: LEVEL_TITLES[level - 1] || LEVEL_TITLES[LEVEL_TITLES.length - 1] };
}

function getBestStatName() {
  const entries = Object.entries(state.stats).filter(([key]) => key !== 'risk');
  const best = entries.sort((a, b) => b[1] - a[1])[0];
  return best ? best[0].charAt(0).toUpperCase() + best[0].slice(1) : 'Balance';
}

function getWellnessScore() {
  return Math.max(0, Math.round(((state.stats.hp + state.stats.energy + state.stats.strength + state.stats.defense) / 4) - (state.stats.risk * 0.3)));
}

function persist() {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
}

function safeParse(value) {
  try {
    return value ? JSON.parse(value) : null;
  } catch {
    return null;
  }
}

function dateKey(date) {
  return date.toISOString().slice(0, 10);
}

function clamp(value, min, max) {
  return Math.min(max, Math.max(min, value));
}

function createEntryId() {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID();
  }

  return `log-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

function signed(value) {
  return `${value >= 0 ? '+' : ''}${value}`;
}