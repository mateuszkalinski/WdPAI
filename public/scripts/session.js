const sessionPage = document.querySelector("[data-session-page]");

if (sessionPage) {
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || "";
  const sessionToggle = document.querySelector("[data-session-toggle]");
  const sessionToggleIcon = document.querySelector("[data-session-toggle-icon]");
  const sessionToggleLabel = document.querySelector("[data-session-toggle-label]");
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
  const sessionNotesInput = document.querySelector("[data-session-notes]");
  const nextExerciseLabel = document.querySelector(".next-exercise__label");
  const nextExerciseTitle = document.querySelector(".next-exercise__title");
  const weightHint = document.querySelector("[data-weight-hint]");
  const weightHintText = document.querySelector("[data-weight-hint-text]");
  const nextExerciseButton = document.querySelector("[data-next-exercise]");
  const nextExerciseButtonIcon = document.querySelector("[data-next-exercise-icon]");
  const nextExerciseButtonLabel = document.querySelector("[data-next-exercise-label]");
  const finishConfirmModal = document.querySelector("[data-finish-confirm-modal]");
  const finishConfirmAccept = document.querySelector("[data-finish-confirm-accept]");
  const finishConfirmCancel = document.querySelector("[data-finish-confirm-cancel]");

  let startedAt = sessionPage.dataset.sessionStartedAt || "";
  let timerId = null;
  let isSessionActive = Boolean(sessionPage.dataset.sessionId);

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

  const formatRpe = (value) =>
    Number(value).toLocaleString("pl-PL", {
      maximumFractionDigits: 1,
    });

  const setMessage = (message, type = "") => {
    if (!sessionMessage) {
      return;
    }

    sessionMessage.textContent = message;
    sessionMessage.classList.toggle("session-message--success", type === "success");
    sessionMessage.classList.toggle("session-message--error", type === "error");
  };

  const setSessionToggleState = (active, disabled = false) => {
    isSessionActive = active;

    if (!sessionToggle) {
      return;
    }

    sessionToggle.hidden = false;
    sessionToggle.disabled = disabled;
    sessionToggle.dataset.sessionActive = active ? "1" : "0";
    sessionToggle.classList.toggle("btn--primary", !active);
    sessionToggle.classList.toggle("btn--dark", active);

    if (sessionToggleIcon) {
      sessionToggleIcon.textContent = active ? "logout" : "play_arrow";
    }

    if (sessionToggleLabel) {
      sessionToggleLabel.textContent = active ? "Zakoncz trening" : "Rozpocznij trening";
    }
  };

  const postJson = async (url, payload = {}) => {
    const response = await fetch(url, {
      method: "POST",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-Token": csrfToken,
      },
      body: JSON.stringify(payload),
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
      throw new Error(data.error || "Nie udalo sie wykonac akcji.");
    }

    return data;
  };

  const planItems = () => Array.from(document.querySelectorAll("[data-plan-item]"));

  const findPlanItemByExerciseId = (exerciseId) =>
    planItems().find((item) => item.dataset.exerciseId === String(exerciseId));

  const findPlanItemByPlanId = (planExerciseId) =>
    planItems().find((item) => item.dataset.planExerciseId === String(planExerciseId));

  const firstIncompletePlanItem = () =>
    planItems().find((item) => {
      const { completed, skipped, target } = planProgress(item);
      return completed + skipped < target;
    });

  const isPlanItemComplete = (item) => {
    const { completed, skipped, target } = planProgress(item);
    return target > 0 && completed + skipped >= target;
  };

  const nextIncompletePlanItemAfter = (item) => {
    const items = planItems();
    const startIndex = items.indexOf(item);
    const nextItems = startIndex >= 0 ? items.slice(startIndex + 1) : items;

    return nextItems.find((candidate) => !isPlanItemComplete(candidate)) || null;
  };

  const planExerciseName = (item) =>
    item?.querySelector(".session-plan__name")?.textContent.trim() || "";

  const planProgress = (item) => ({
    completed: Number(item?.dataset.completedSets || 0),
    skipped: Number(item?.dataset.skippedSets || 0),
    target: Number(item?.dataset.targetSets || 0),
  });

  const currentPlanItem = () =>
    planItems().find((item) => item.classList.contains("session-plan__item--current"))
    || findPlanItemByExerciseId(exerciseSelect?.value || "");

  const shouldNextButtonFinish = () => {
    const item = currentPlanItem();
    return Boolean(item && !nextIncompletePlanItemAfter(item));
  };

  const updateNextExerciseButton = () => {
    if (!nextExerciseButton) {
      return;
    }

    const shouldFinish = shouldNextButtonFinish();

    if (nextExerciseButtonIcon) {
      nextExerciseButtonIcon.textContent = shouldFinish ? "logout" : "skip_next";
    }

    if (nextExerciseButtonLabel) {
      nextExerciseButtonLabel.textContent = shouldFinish ? "Zakoncz trening" : "Nastepne cwiczenie";
    }
  };

  const updateSkipButtons = () => {
    const hasCurrentPlanItem = Boolean(currentPlanItem());

    if (nextExerciseButton) {
      nextExerciseButton.disabled = !hasCurrentPlanItem;
    }

    updateNextExerciseButton();
  };

  const updatePlanItemState = (item) => {
    if (!item) {
      return;
    }

    const { completed, skipped, target } = planProgress(item);
    const progressValue = completed;
    const progress = item.querySelector("[data-plan-progress]");
    const skipNote = item.querySelector("[data-plan-skip-note]");

    if (progress) {
      progress.textContent = `${progressValue}/${target}`;
    }

    if (skipNote) {
      skipNote.textContent = `${skipped} pom.`;
      skipNote.hidden = skipped <= 0;
    }

    item.classList.toggle("session-plan__item--done", target > 0 && completed + skipped >= target);
    item.classList.toggle("session-plan__item--skipped", skipped > 0);
  };

  const updateCurrentPlanItem = () => {
    const selectedExerciseId = exerciseSelect?.value || "";

    planItems().forEach((item) => {
      item.classList.toggle(
        "session-plan__item--current",
        item.dataset.exerciseId === selectedExerciseId
      );
    });

    updateSkipButtons();
  };

  const updateNextExerciseCard = () => {
    const nextItem = firstIncompletePlanItem();

    if (!nextExerciseLabel || !nextExerciseTitle) {
      return;
    }

    if (!nextItem) {
      nextExerciseLabel.textContent = planItems().length ? "Plan wykonany" : "Nastepny krok";
      nextExerciseTitle.textContent = planItems().length
        ? "Mozesz zakonczyc trening"
        : "Zapisz serie albo zakoncz trening";
      return;
    }

    const { completed, target } = planProgress(nextItem);
    const progressValue = completed;
    nextExerciseLabel.textContent = "Nastepne z planu";
    nextExerciseTitle.textContent = `${planExerciseName(nextItem)} (${progressValue}/${target})`;
  };

  const updateWeightHint = (item) => {
    if (!weightHint || !weightHintText) {
      return;
    }

    const lastWeight = item?.dataset.lastWeight || "";

    if (!lastWeight || Number(lastWeight) <= 0) {
      weightHint.hidden = true;

      return;
    }

    const lastReps = item?.dataset.lastReps || "";
    const lastRpe = item?.dataset.lastRpe || "";
    const details = [
      `${formatWeight(lastWeight)} kg`,
      lastReps ? `${lastReps} powt.` : "",
      lastRpe ? `RPE ${formatRpe(lastRpe)}` : "",
    ].filter(Boolean).join(" / ");

    weightHintText.textContent = `Ostatnio: ${details}`;
    weightHint.hidden = false;

  };

  const applyPlanDefaults = (item) => {
    if (!item || !setForm) {
      updateWeightHint(item);
      return;
    }

    const repsInput = setForm.elements.reps;
    if (repsInput && item.dataset.targetRepsMin) {
      repsInput.value = item.dataset.targetRepsMin;
    }

    updateWeightHint(item);
  };

  const syncPlannedWorkout = (plannedWorkout) => {
    if (!plannedWorkout?.exercises) {
      return;
    }

    plannedWorkout.exercises.forEach((exercise) => {
      const item = findPlanItemByPlanId(exercise.plan_exercise_id);

      if (!item) {
        return;
      }

      item.dataset.completedSets = String(exercise.completed_sets || 0);
      item.dataset.skippedSets = String(exercise.skipped_sets || 0);
      item.dataset.targetSets = String(exercise.target_sets || item.dataset.targetSets || 0);
      item.dataset.lastWeight = exercise.last_weight_kg === null || exercise.last_weight_kg === undefined
        ? ""
        : String(exercise.last_weight_kg);
      item.dataset.lastReps = exercise.last_reps === null || exercise.last_reps === undefined
        ? ""
        : String(exercise.last_reps);
      item.dataset.lastRpe = exercise.last_rpe === null || exercise.last_rpe === undefined
        ? ""
        : String(exercise.last_rpe);
      updatePlanItemState(item);
    });
  };

  const selectExercise = (exerciseId, shouldApplyDefaults = true) => {
    if (!exerciseSelect) {
      return false;
    }

    const option = Array.from(exerciseSelect.options).find(
      (candidate) => candidate.value === String(exerciseId)
    );

    if (!option) {
      return false;
    }

    exerciseSelect.value = option.value;
    updateSelectedExercise(shouldApplyDefaults);
    return true;
  };

  const selectNextPlannedExercise = () => {
    const nextItem = firstIncompletePlanItem();

    if (!nextItem) {
      updateCurrentPlanItem();
      updateNextExerciseCard();
      return null;
    }

    selectExercise(nextItem.dataset.exerciseId);
    updateNextExerciseCard();
    return nextItem;
  };

  const skipCurrentPlanItem = async (triggerButton) => {
    const item = currentPlanItem();

    if (!item) {
      setMessage("Brak aktywnego cwiczenia z planu do pominiecia.", "error");
      return;
    }

    if (isPlanItemComplete(item)) {
      const nextItem = nextIncompletePlanItemAfter(item);

      if (!nextItem) {
        updateNextExerciseCard();
        setMessage("Plan dnia wykonany. Mozesz dodac serie ekstra albo zakonczyc trening.", "success");
        return;
      }

      selectExercise(nextItem.dataset.exerciseId);
      updateNextExerciseCard();
      setMessage(`Przechodzimy dalej: ${planExerciseName(nextItem)}.`, "success");
      return;
    }

    if (triggerButton) {
      triggerButton.disabled = true;
    }

    setMessage("Przechodze do nastepnego cwiczenia...");

    try {
      const data = await postJson("/api/workout/skip", {
        planExerciseId: item.dataset.planExerciseId,
      });

      applySession(data.session);
      syncPlannedWorkout(data.plannedWorkout);

      if (data.sets) {
        renderSets(data.sets);
      }

      const nextItem = nextIncompletePlanItemAfter(item) || firstIncompletePlanItem();

      if (nextItem) {
        selectExercise(nextItem.dataset.exerciseId);
      } else {
        updateCurrentPlanItem();
      }

      updateNextExerciseCard();
      setMessage(
        nextItem
          ? `Przechodzimy dalej: ${planExerciseName(nextItem)}.`
          : "Plan dnia wykonany.",
        "success"
      );
    } catch (error) {
      setMessage(error.message, "error");
    } finally {
      if (triggerButton) {
        triggerButton.disabled = false;
      }

      updateSkipButtons();
    }
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
      sessionStatus.textContent = session.status === "finished" ? "Sesja zakonczona" : "Sesja w toku";
    }

    setSessionToggleState(session.status !== "finished", session.status === "finished");

    if (session.status === "finished") {
      stopTimer();
    } else {
      startTimer();
    }
  };

  const renderSet = (set) => {
    const rpe = set.rpe === null || set.rpe === undefined || set.rpe === ""
      ? ""
      : `<span class="set-history__rpe">RPE ${escapeHtml(formatRpe(set.rpe))}</span>`;

    return `
          <div class="set-history__item">
            <div class="set-history__item-main">
              <div class="set-history__status set-history__status--done">
                <span class="material-symbols-outlined icon--filled set-history__status-icon">done</span>
              </div>
              <div>
                <span class="set-history__name">${escapeHtml(set.exercise_name)} - seria ${escapeHtml(set.set_order)}</span>
                <span class="set-history__meta">${formatWeight(set.weight_kg)} kg x ${escapeHtml(set.reps)}</span>
              </div>
            </div>
            ${rpe}
          </div>
        `;
  };

  const updateSetCount = () => {
    if (setCount && setList) {
      setCount.textContent = String(setList.querySelectorAll(".set-history__item").length);
    }
  };

  const updateEmptySets = () => {
    const nextEmpty = setList?.querySelector("[data-empty-sets]");

    if (nextEmpty) {
      nextEmpty.hidden = Boolean(setList?.querySelector(".set-history__item"));
    }
  };

  const renderSets = (sets = []) => {
    if (!setList) {
      return;
    }

    const rows = sets.map(renderSet).join("");

    const emptyMarkup = emptySets ? emptySets.outerHTML : "";
    setList.innerHTML = rows + emptyMarkup;
    updateEmptySets();
    updateSetCount();
  };

  const prependSet = (set) => {
    if (!setList || !set) {
      return;
    }

    const template = document.createElement("template");
    template.innerHTML = renderSet(set).trim();
    setList.insertBefore(template.content.firstElementChild, setList.firstElementChild);
    updateEmptySets();
    updateSetCount();
  };

  const updateSelectedExercise = (shouldApplyDefaults = true) => {
    if (!exerciseSelect) {
      return;
    }

    const option = exerciseSelect.selectedOptions[0];
    if (!option) {
      return;
    }

    const planItem = findPlanItemByExerciseId(option.value);
    const plannedName = planExerciseName(planItem);

    if (exerciseName) {
      exerciseName.textContent = plannedName || option.dataset.name || option.textContent.trim();
    }

    if (exerciseMeta) {
      const muscle = option.dataset.muscle || "Ogolne";
      const equipment = option.dataset.equipment || "Brak sprzetu";
      exerciseMeta.textContent = `${muscle} - ${equipment}`;
    }
    if (shouldApplyDefaults) {
      applyPlanDefaults(planItem);
    }

    updateCurrentPlanItem();
    updateNextExerciseCard();
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

  const startTimer = () => {
    if (timerId || !startedAt) {
      return;
    }

    updateTimer();
    timerId = window.setInterval(updateTimer, 1000);
  };

  const stopTimer = () => {
    if (!timerId) {
      return;
    }

    window.clearInterval(timerId);
    timerId = null;
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

  planItems().forEach((item) => {
    updatePlanItemState(item);
    item.addEventListener("click", () => {
      selectExercise(item.dataset.selectExercise || item.dataset.exerciseId);
    });
  });

  selectNextPlannedExercise();

  if (nextExerciseButton) {
    nextExerciseButton.addEventListener("click", () => {
      if (shouldNextButtonFinish()) {
        finishSession();
        return;
      }

      skipCurrentPlanItem(nextExerciseButton);
    });
  }

  const startSession = async () => {
    setSessionToggleState(false, true);
    setMessage("Rozpoczynam sesje...");

    try {
      const data = await postJson("/api/workout/start", {});
      applySession(data.session);
      syncPlannedWorkout(data.plannedWorkout);
      renderSets(data.sets || []);
      selectNextPlannedExercise();
      setMessage("Sesja rozpoczeta. Mozesz zapisac pierwsza serie.", "success");
    } catch (error) {
      setMessage(error.message, "error");
      setSessionToggleState(false, false);
    }
  };

  const confirmFinishSession = () => new Promise((resolve) => {
    if (!finishConfirmModal || !finishConfirmAccept || !finishConfirmCancel) {
      resolve(true);
      return;
    }

    const cleanup = (result) => {
      finishConfirmModal.hidden = true;
      document.body.classList.remove("modal-open");
      finishConfirmAccept.removeEventListener("click", onAccept);
      finishConfirmCancel.removeEventListener("click", onCancel);
      finishConfirmModal.removeEventListener("click", onBackdrop);
      document.removeEventListener("keydown", onKeydown);
      resolve(result);
    };

    const onAccept = () => cleanup(true);
    const onCancel = () => cleanup(false);
    const onBackdrop = (event) => {
      if (event.target === finishConfirmModal) {
        cleanup(false);
      }
    };
    const onKeydown = (event) => {
      if (event.key === "Escape") {
        cleanup(false);
      }
    };

    finishConfirmModal.hidden = false;
    document.body.classList.add("modal-open");
    finishConfirmAccept.addEventListener("click", onAccept);
    finishConfirmCancel.addEventListener("click", onCancel);
    finishConfirmModal.addEventListener("click", onBackdrop);
    document.addEventListener("keydown", onKeydown);
    finishConfirmCancel.focus();
  });

  if (setForm) {
    setForm.addEventListener("submit", async (event) => {
      event.preventDefault();

      const submitButton = setForm.querySelector("[type='submit']");
      const payload = {
        exerciseId: setForm.elements.exerciseId?.value,
        weightKg: setForm.elements.weightKg?.value,
        reps: setForm.elements.reps?.value,
        rpe: setForm.elements.rpe?.value,
        note: setForm.elements.note?.value,
      };

      submitButton.disabled = true;
      setMessage("Zapisuje serie...");

      try {
        const data = await postJson("/api/workout/set", payload);
        applySession(data.session);
        if (data.set) {
          prependSet(data.set);
        } else {
          renderSets(data.sets || []);
        }
        syncPlannedWorkout(data.plannedWorkout);
        updateCurrentPlanItem();
        updateNextExerciseCard();

        const item = currentPlanItem();
        setMessage(
          item && isPlanItemComplete(item)
            ? "Seria zapisana. Planowe serie tego cwiczenia sa gotowe, ale mozesz dopisac kolejna albo przejsc dalej."
            : "Seria zapisana. Mozesz dopisac kolejna serie albo przejsc dalej.",
          "success"
        );

        if (setForm.elements.note) {
          setForm.elements.note.value = "";
        }

        if (setForm.elements.rpe) {
          setForm.elements.rpe.value = "";
        }

      } catch (error) {
        setMessage(error.message, "error");
      } finally {
        submitButton.disabled = false;
      }
    });
  }

  const finishSession = async () => {
      if (!await confirmFinishSession()) {
        return;
      }

      setSessionToggleState(true, true);
      setMessage("Koncze trening...");

      try {
        const data = await postJson("/api/workout/finish", {
          notes: sessionNotesInput?.value || null,
        });

        applySession(data.session);
        syncPlannedWorkout(data.plannedWorkout);
        renderSets(data.sets || []);
        setMessage("Trening zakonczony. Przenosze do historii...", "success");
        window.setTimeout(() => {
          window.location.href = "/history";
        }, 700);
      } catch (error) {
        setMessage(error.message, "error");
        setSessionToggleState(true, false);
      }
  };

  if (sessionToggle) {
    sessionToggle.addEventListener("click", () => {
      if (isSessionActive) {
        finishSession();
        return;
      }

      startSession();
    });
  }

  setSessionToggleState(isSessionActive);
  startTimer();
}
