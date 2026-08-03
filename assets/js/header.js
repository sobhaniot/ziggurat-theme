jQuery(document).ready(function ($) {
  $("#mobile-menu-btn").on("click", function () {
    $("#main-menu").toggleClass("active");
    $(this).toggleClass("open");
  });
  // بستن منو بعد از کلیک روی لینک
  $("#main-menu a").on("click", function () {
    $("#main-menu").removeClass("active");
    $("#mobile-menu-btn").removeClass("open");
  });
  $(window).on("scroll", function () {
    if ($(window).scrollTop() > 50) {
      $("#main-header").addClass("scrolled");
    } else {
      $("#main-header").removeClass("scrolled");
    }
  });
});
