const searchInput = document.querySelector("[data-exercise-search]");
const exerciseGrid = document.querySelector("[data-exercise-grid]");
const emptyState = document.querySelector("[data-empty-state]");
const resultsCount = document.querySelector("[data-results-count]");
const filterContainer = document.querySelector("[data-muscle-filters]");

if (searchInput && exerciseGrid && emptyState && resultsCount && filterContainer) {
  const difficultyLabels = {
    beginner: "Początkujący",
    intermediate: "Średni",
    advanced: "Zaawansowany",
  };

  let activeMuscleGroupId = "";
  let debounceTimer = null;
  let requestNumber = 0;

  const escapeHtml = (value) =>
    String(value ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");

  const renderExercise = (exercise) => {
    const equipment = exercise.equipment || "Bez sprzętu";
    const difficulty = difficultyLabels[exercise.difficulty] || exercise.difficulty;
    const primaryMuscle = exercise.primary_muscle_group || "Ogólne";
    const muscleGroups = exercise.muscle_groups || "Brak danych";

    return `
      <article class="exercise-card">
        <div class="exercise-card__media exercise-card__media--db">
          <span class="material-symbols-outlined exercise-card__media-icon">fitness_center</span>
          <div class="exercise-card__badge">${escapeHtml(primaryMuscle)}</div>
        </div>
        <div class="exercise-card__body">
          <h3 class="exercise-card__title">${escapeHtml(exercise.name)}</h3>
          <div class="exercise-card__meta">
            <span class="material-symbols-outlined exercise-card__meta-icon">fitness_center</span>
            <span class="exercise-card__meta-text">${escapeHtml(equipment)} • ${escapeHtml(difficulty)}</span>
          </div>
          <p class="exercise-card__description">${escapeHtml(exercise.description)}</p>
          <div class="exercise-card__stats">
            <span class="exercise-card__stat-label">Partie</span>
            <span class="exercise-card__stat-value">${escapeHtml(muscleGroups)}</span>
          </div>
        </div>
      </article>
    `;
  };

  const renderExercises = (exercises) => {
    exerciseGrid.innerHTML = exercises.map(renderExercise).join("");
    resultsCount.textContent = exercises.length;
    emptyState.hidden = exercises.length !== 0;
  };

  const searchExercises = async () => {
    const currentRequest = ++requestNumber;

    const response = await fetch("/api/exercises/search", {
      method: "POST",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        search: searchInput.value.trim(),
        muscleGroupId: activeMuscleGroupId || null,
      }),
    });

    if (!response.ok) {
      return;
    }

    const data = await response.json();

    if (currentRequest === requestNumber) {
      renderExercises(data.exercises || []);
    }
  };

  const scheduleSearch = () => {
    window.clearTimeout(debounceTimer);
    debounceTimer = window.setTimeout(searchExercises, 250);
  };

  searchInput.addEventListener("input", scheduleSearch);

  filterContainer.addEventListener("click", (event) => {
    const button = event.target.closest("[data-muscle-id]");

    if (!button) {
      return;
    }

    filterContainer.querySelectorAll(".filter-chip").forEach((chip) => {
      chip.classList.remove("filter-chip--active");
    });

    button.classList.add("filter-chip--active");
    activeMuscleGroupId = button.dataset.muscleId || "";
    scheduleSearch();
  });
}
