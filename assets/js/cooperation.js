(function () {
  "use strict";
  function updateApplicationType() {
    var selected = document.querySelector('input[name="application_type"]:checked');
    var business = document.querySelector(".business-name-field");
    var profession = document.querySelector(".profession-label");
    if (!selected || !business || !profession) return;
    var supplier = selected.value === "supplier";
    business.style.display = supplier ? "grid" : "none";
    profession.textContent = supplier ? "حوزه تأمین یا تولید *" : "زمینه شغلی *";
  }
  document.addEventListener("DOMContentLoaded", function () {
    var choices = document.querySelectorAll('input[name="application_type"]');
    if (!choices.length) return;
    choices.forEach(function (choice) { choice.addEventListener("change", updateApplicationType); });
    updateApplicationType();
  });
})();
