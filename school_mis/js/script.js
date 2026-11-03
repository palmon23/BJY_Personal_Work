document.addEventListener("DOMContentLoaded", () => {
  const form = document.querySelector("form");
  
  if (!form) return;

  form.addEventListener("submit", (e) => {
    e.preventDefault();
    
    if (validateForm(form)) {
      form.submit();
    }
  });

  // Real-time validation
  const inputs = form.querySelectorAll("input[required], textarea[required], select[required]");
  inputs.forEach(input => {
    input.addEventListener("blur", () => validateField(input));
    input.addEventListener("input", () => clearError(input));
  });
});

function validateForm(form) {
  const inputs = form.querySelectorAll("input[required], textarea[required], select[required]");
  let isValid = true;

  inputs.forEach(input => {
    if (!validateField(input)) {
      isValid = false;
    }
  });

  return isValid;
}

function validateField(input) {
  const value = input.value.trim();
  const type = input.type;

  if (!value) {
    showError(input, "This field is required");
    return false;
  }

  if (type === "email" && !isValidEmail(value)) {
    showError(input, "Please enter a valid email");
    return false;
  }

  if (type === "password" && value.length < 6) {
    showError(input, "Password must be at least 6 characters");
    return false;
  }

  clearError(input);
  return true;
}

function isValidEmail(email) {
  const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return regex.test(email);
}

function showError(input, message) {
  input.classList.add("input-error");
  input.setAttribute("aria-invalid", "true");
  
  let errorMsg = input.nextElementSibling;
  if (!errorMsg || !errorMsg.classList.contains("error-message")) {
    errorMsg = document.createElement("span");
    errorMsg.className = "error-message";
    input.parentNode.insertBefore(errorMsg, input.nextSibling);
  }
  errorMsg.textContent = message;
}

function clearError(input) {
  input.classList.remove("input-error");
  input.setAttribute("aria-invalid", "false");
  
  const errorMsg = input.nextElementSibling;
  if (errorMsg && errorMsg.classList.contains("error-message")) {
    errorMsg.remove();
  }
}