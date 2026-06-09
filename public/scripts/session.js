const sessionPage = document.querySelector("[data-session-page]");

if (sessionPage) {
  const startButton = document.querySelector("[data-start-session]");
  const finishButton = document.querySelector("[data-finish-session]");
  const setForm = document.querySelector("[data-set-form]");
  const exerciseSelect = document.querySelector("[data-exercise-select]");
  const exerciseName = document.querySelector("[data-selected-exercise-name]");
  const exerciseMeta = document.querySelector("[data-selected-exercise-meta]");
  const sessionStatus = document.querySelector("[data-session-status]");
  const sessionTitle = document.querySelector("[data-session-title]");
  const sessionTimer = document.querySelector("[data-session-timer]");
  const sessionMessage = document.querySelector("[data-session-message]");
  const setList = document.querySelector("[data-set-list]");
  const setCount = document.querySelector("[data-set-count]");
  const emptySets = document.querySelector("[data-empty-sets]");
  const restPanel = document.querySelector("[data-rest-panel]");
  const restHideButton = document.querySelector("[data-rest-hide]");
  const sessionRpeInput = document.querySelector("[data-session-rpe]");
  const sessionNotesInput = document.querySelector("[data-session-notes]");

  let startedAt = sessionPage.dataset.sessionStartedAt || "";

  const escapeHtml = (value) =>
    String(value ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");

  const formatWeight = (value) =>
    Number(value || 0).toLocaleString("pl-PL", {
      maximumFractionDigits: 2,
    });

  const setMessage = (message, type = "") => {
    if (!sessionMessage) {
      return;
    }

    sessionMessage.textContent = message;
    sessionMessage.classList.toggle("session-message--success", type === "success");
    sessionMessage.classList.toggle("session-message--error", type === "error");
  };

  const postJson = async (url, payload = {}) => {
    const response = await fetch(url, {
      method: "POST",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(payload),
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
      throw new Error(data.error || "Nie udało się wykonać akcji.");
    }

    return data;
  };

  const applySession = (session) => {
    if (!session) {
      return;
    }

    sessionPage.dataset.sessionId = session.id || "";
    startedAt = session.started_at || "";
    sessionPage.dataset.sessionStartedAt = startedAt;

    if (sessionTitle) {
      sessionTitle.textContent = session.name || "Sesja treningowa";
    }

    if (sessionStatus) {
      sessionStatus.textContent = session.status === "finished" ? "Sesja zakończona" : "Sesja w toku";
    }

    if (startButton) {
      startButton.hidden = true;
    }

    if (finishButton) {
      finishButton.disabled = session.status === "finished";
    }
  };

  const renderSets = (sets = []) => {
    if (!setList) {
      return;
    }

    const rows = sets
      .map(
        (set) => `
          <div class="set-history__item">
            <div class="set-history__item-main">
              <div class="set-history__status set-history__status--done">
                <span class="material-symbols-outlined icon--filled set-history__status-icon">done</span>
              </div>
              <div>
                <span class="set-history__name">${escapeHtml(set.exercise_name)} · seria ${escapeHtml(set.set_order)}</span>
                <span class="set-history__meta">${formatWeight(set.weight_kg)} kg × ${escapeHtml(set.reps)}</span>
              </div>
            </div>
            <span class="set-history__rpe">RPE ${set.rpe === null || set.rpe === undefined ? "brak" : escapeHtml(Number(set.rpe).toFixed(1))}</span>
          </div>
        `
      )
      .join("");

    const emptyMarkup = emptySets ? emptySets.outerHTML : "";
    setList.innerHTML = rows + emptyMarkup;

    const nextEmpty = setList.querySelector("[data-empty-sets]");
    if (nextEmpty) {
      nextEmpty.hidden = sets.length !== 0;
    }

    if (setCount) {
      setCount.textContent = String(sets.length);
    }
  };

  const updateSelectedExercise = () => {
    if (!exerciseSelect) {
      return;
    }

    const option = exerciseSelect.selectedOptions[0];
    if (!option) {
      return;
    }

    if (exerciseName) {
      exerciseName.textContent = option.dataset.name || option.textContent.trim();
    }

    if (exerciseMeta) {
      const muscle = option.dataset.muscle || "Ogólne";
      const equipment = option.dataset.equipment || "Brak sprzętu";
      exerciseMeta.textContent = `${muscle} · ${equipment}`;
    }
  };

  const updateTimer = () => {
    if (!sessionTimer || !startedAt) {
      return;
    }

    const startTime = new Date(startedAt).getTime();
    if (Number.isNaN(startTime)) {
      return;
    }

    const seconds = Math.max(0, Math.floor((Date.now() - startTime) / 1000));
    const hours = String(Math.floor(seconds / 3600)).padStart(2, "0");
    const minutes = String(Math.floor((seconds % 3600) / 60)).padStart(2, "0");
    const restSeconds = String(seconds % 60).padStart(2, "0");
    sessionTimer.textContent = `${hours}:${minutes}:${restSeconds}`;
  };

  document.querySelectorAll("[data-step-target]").forEach((button) => {
    button.addEventListener("click", () => {
      const input = document.getElementById(button.dataset.stepTarget);
      if (!input) {
        return;
      }

      const step = Number(button.dataset.step || input.step || 1);
      const min = input.min === "" ? -Infinity : Number(input.min);
      const current = Number(input.value || 0);
      const nextValue = Math.max(min, current + step);
      input.value = String(Number.isInteger(nextValue) ? nextValue : nextValue.toFixed(1));
    });
  });

  if (exerciseSelect) {
    exerciseSelect.addEventListener("change", updateSelectedExercise);
    updateSelectedExercise();
  }

  if (startButton) {
    startButton.addEventListener("click", async () => {
      startButton.disabled = true;
      setMessage("Rozpoczynam sesję...");

      try {
        const data = await postJson("/api/workout/start", {});
        applySession(data.session);
        renderSets(data.sets || []);
        setMessage("Sesja rozpoczęta. Możesz zapisać pierwszą serię.", "success");
      } catch (error) {
        setMessage(error.message, "error");
        startButton.disabled = false;
      }
    });
  }

  if (setForm) {
    setForm.addEventListener("submit", async (event) => {
      event.preventDefault();

      const submitButton = setForm.querySelector("[type='submit']");
      const payload = {
        exerciseId: setForm.elements.exerciseId?.value,
        weightKg: setForm.elements.weightKg?.value,
        reps: setForm.elements.reps?.value,
        rpe: setForm.elements.rpe?.value,
        setType: setForm.elements.setType?.value,
        note: setForm.elements.note?.value,
      };

      submitButton.disabled = true;
      setMessage("Zapisuję serię...");

      try {
        const data = await postJson("/api/workout/set", payload);
        applySession(data.session);
        renderSets(data.sets || []);
        setMessage("Seria zapisana w bazie.", "success");

        if (setForm.elements.note) {
          setForm.elements.note.value = "";
        }

        if (restPanel) {
          restPanel.hidden = false;
        }
      } catch (error) {
        setMessage(error.message, "error");
      } finally {
        submitButton.disabled = false;
      }
    });
  }

  if (finishButton) {
    finishButton.addEventListener("click", async () => {
      finishButton.disabled = true;
      setMessage("Kończę trening...");

      try {
        const data = await postJson("/api/workout/finish", {
          sessionRpe: sessionRpeInput?.value || null,
          notes: sessionNotesInput?.value || null,
        });

        applySession(data.session);
        renderSets(data.sets || []);
        setMessage("Trening zakończony. Przenoszę do historii...", "success");
        window.setTimeout(() => {
          window.location.href = "/history";
        }, 700);
      } catch (error) {
        setMessage(error.message, "error");
        finishButton.disabled = false;
      }
    });
  }

  if (restHideButton && restPanel) {
    restHideButton.addEventListener("click", () => {
      restPanel.hidden = true;
    });
  }

  updateTimer();
  window.setInterval(updateTimer, 1000);
}
