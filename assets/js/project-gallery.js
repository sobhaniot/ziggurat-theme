jQuery(function ($) {
  let mediaUploader;
  $("#select_gallery").on("click", function (e) {
    e.preventDefault();
    // اگر قبلاً ساخته شده فقط بازش کن
    if (mediaUploader) {
      mediaUploader.open();
      return;
    }
    mediaUploader = wp.media({
      title: "انتخاب تصاویر پروژه",
      button: {
        text: "استفاده از تصاویر",
      },
      multiple: true,
    });
    mediaUploader.on("select", function () {
      let attachments = mediaUploader.state().get("selection").toJSON();
      let ids = [];
      let preview = $("#gallery-preview");
      preview.html("");
      attachments.forEach(function (attachment) {
        ids.push(attachment.id);
        let imageUrl = attachment.url;
        if (attachment.sizes) {
          if (attachment.sizes.medium) {
            imageUrl = attachment.sizes.medium.url;
          } else if (attachment.sizes.thumbnail) {
            imageUrl = attachment.sizes.thumbnail.url;
          }
        }
        preview.append(`
    <div class="gallery-thumb">
        <img src="${imageUrl}">
    </div>
`);
      });
      $("#project_gallery").val(ids.join(","));
    });
    mediaUploader.open();
  });
});
