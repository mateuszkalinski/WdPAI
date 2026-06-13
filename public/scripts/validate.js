const form = document.querySelector(".auth-form");

if (form) {
  const emailInput = form.querySelector('input[name="email"]');
  const passwordInput = form.querySelector('input[name="password"]');
  const confirmedPasswordInput = form.querySelector('input[name="password2"]');

  const ensureMessage = (input) => {
    let message = input.nextElementSibling;

    if (!message || !message.classList.contains("auth-form__field-error")) {
      message = document.createElement("span");
      message.className = "auth-form__field-error";
      message.setAttribute("aria-live", "polite");
      input.insertAdjacentElement("afterend", message);
    }

    return message;
  };

  const setFieldState = (input, messageText = "") => {
    const message = ensureMessage(input);
    const isInvalid = messageText !== "";

    input.classList.toggle("auth-form__field--invalid", isInvalid);
    input.setAttribute("aria-invalid", String(isInvalid));
    message.textContent = messageText;
    message.hidden = !isInvalid;

    return !isInvalid;
  };

  const validateEmail = () => {
    if (!emailInput) {
      return true;
    }

    const email = emailInput.value.trim();
    const isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    return setFieldState(emailInput, isValid ? "" : "Podaj poprawny adres e-mail.");
  };

  const validatePasswordLength = () => {
    if (!passwordInput) {
      return true;
    }

    return setFieldState(
      passwordInput,
      passwordInput.value.length >= 6 ? "" : "Haslo musi miec minimum 6 znakow."
    );
  };

  const validatePasswordMatch = () => {
    if (!passwordInput || !confirmedPasswordInput) {
      return true;
    }

    return setFieldState(
      confirmedPasswordInput,
      passwordInput.value === confirmedPasswordInput.value ? "" : "Hasla nie sa zgodne."
    );
  };

  emailInput?.addEventListener("input", validateEmail);
  passwordInput?.addEventListener("input", () => {
    validatePasswordLength();
    validatePasswordMatch();
  });
  confirmedPasswordInput?.addEventListener("input", validatePasswordMatch);

  form.addEventListener("submit", (event) => {
    const isValid = [validateEmail(), validatePasswordLength(), validatePasswordMatch()].every(Boolean);

    if (!isValid) {
      event.preventDefault();
    }
  });
}
