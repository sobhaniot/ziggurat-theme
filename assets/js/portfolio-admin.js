(function ($) {
  "use strict";

  $(function () {
    const picker = document.querySelector("[data-portfolio-pdf-picker]");
    if (!picker || !window.wp || !wp.media) return;

    const idInput = picker.querySelector("[data-portfolio-pdf-id]");
    const status = picker.querySelector("[data-portfolio-pdf-status]");
    const name = picker.querySelector("[data-portfolio-pdf-name]");
    const select = picker.querySelector("[data-portfolio-pdf-select]");
    const remove = picker.querySelector("[data-portfolio-pdf-remove]");
    let frame;

    select.addEventListener("click", function () {
      if (!frame) {
        frame = wp.media({
          title: "انتخاب فایل PDF کاتالوگ",
          button: { text: "استفاده در پورتفولیو" },
          library: { type: "application/pdf" },
          multiple: false,
        });
        frame.on("select", function () {
          const attachment = frame.state().get("selection").first().toJSON();
          idInput.value = attachment.id || "";
          name.textContent = attachment.filename || attachment.title || "فایل PDF";
          status.hidden = false;
          remove.hidden = false;
          select.textContent = "تعویض PDF";
        });
      }
      frame.open();
    });

    remove.addEventListener("click", function () {
      idInput.value = "";
      name.textContent = "";
      status.hidden = true;
      remove.hidden = true;
      select.textContent = "انتخاب PDF";
    });
  });
})(jQuery);
