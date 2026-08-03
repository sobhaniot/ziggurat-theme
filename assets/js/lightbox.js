document.addEventListener("DOMContentLoaded", function () {
  if (typeof GLightbox !== "undefined") {
    GLightbox({
      selector: ".glightbox",
      loop: true,
      zoomable: true,
      touchNavigation: true,
      draggable: true,
    });
  }
});
