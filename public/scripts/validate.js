const form = document.querySelector(".auth-form");

if (form) {
    const emailInput = form.querySelector('input[name="email"]');
    const passwordInput = form.querySelector('input[name="password"]');
    const confirmedPasswordInput = form.querySelector('input[name="password2"]');

    function isEmail(email) {
        return /\S+@\S+\.\S+/.test(email);
    }

    function arePasswordsSame(password, confirmedPassword) {
        return password === confirmedPassword;
    }

    function markValidation(element, condition) {
        // Używamy klasy zgodnej z Twoją metodologią BEM
        if (!condition) {
            element.classList.add('auth-form__field--invalid');
        } else {
            element.classList.remove('auth-form__field--invalid');
        }
    }

    function validateEmail() {
        setTimeout(function () {
            markValidation(emailInput, isEmail(emailInput.value));
        }, 1000);
    }

    function validatePassword() {
        setTimeout(function () {
            // Wyciągamy wartość bezpośrednio z inputa, zamiast polegać na ułożeniu w HTML
            const condition = arePasswordsSame(passwordInput.value, confirmedPasswordInput.value);
            markValidation(confirmedPasswordInput, condition);
        }, 1000);
    }

    if (emailInput) {
        emailInput.addEventListener('keyup', validateEmail);
    }
    
    if (confirmedPasswordInput) {
        confirmedPasswordInput.addEventListener('keyup', validatePassword);
    }
}