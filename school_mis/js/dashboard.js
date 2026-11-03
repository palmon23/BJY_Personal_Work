document.addEventListener("DOMContentLoaded", () => {
  // Highlight active navigation link
  const currentUrl = window.location.href.replace(/\/$/, '');
  const navLinks = document.querySelectorAll("nav a");
  navLinks.forEach(link => {
    const linkUrl = link.href.replace(/\/$/, '');
    if (linkUrl === currentUrl) {
      link.classList.add("active");
      link.setAttribute("aria-current", "page");
    }
  });

  // Confirmation before deleting records
  const deleteButtons = document.querySelectorAll(".delete-btn");
  deleteButtons.forEach(btn => {
    btn.addEventListener("click", (e) => {
      if (!confirm("Are you sure you want to delete this record?")) {
        e.preventDefault();
      }
    });
  });

  // Add time-based greeting
  const headerContent = document.querySelector(".header-content span");
  if (headerContent) {
    const hours = new Date().getHours();
    let greeting = "Welcome!";
    
    if (hours < 12) greeting = "Good Morning 🌞";
    else if (hours < 18) greeting = "Good Afternoon 🌤";
    else greeting = "Good Evening 🌙";
    
    headerContent.textContent += " | " + greeting;
  }

  // Form validation helper
  const forms = document.querySelectorAll("form");
  forms.forEach(form => {
    form.addEventListener("submit", (e) => {
      const inputs = form.querySelectorAll("input[required], textarea[required], select[required]");
      let isValid = true;
      
      inputs.forEach(input => {
        if (!input.value.trim()) {
          input.style.borderColor = "#d32f2f";
          isValid = false;
        } else {
          input.style.borderColor = "#ddd";
        }
      });
      
      if (!isValid) {
        e.preventDefault();
        alert("Please fill in all required fields");
      }
    });
  });
});