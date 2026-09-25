(function () {
  "use strict";

  const toPersianDigits = (value) =>
    String(value).replace(/\d/g, (digit) => "۰۱۲۳۴۵۶۷۸۹"[Number(digit)]);

  const initializeFlipbook = async (viewer) => {
    if (viewer.dataset.initialized === "true") return;
    viewer.dataset.initialized = "true";

    const config = window.ziguratPortfolioConfig || {};
    const pdfUrl = viewer.dataset.pdfUrl;
    const book = viewer.querySelector("[data-flipbook-book]");
    const stage = viewer.querySelector("[data-flipbook-stage]");
    const loading = viewer.querySelector("[data-flipbook-loading]");
    const status = viewer.querySelector("[data-flipbook-status]");
    const current = viewer.querySelector("[data-flipbook-current]");
    const total = viewer.querySelector("[data-flipbook-total]");
    const previous = viewer.querySelector("[data-flipbook-prev]");
    const next = viewer.querySelector("[data-flipbook-next]");
    const fullscreen = viewer.querySelector("[data-flipbook-fullscreen]");
    let pageFlipInstance = null;

    const fail = (error) => {
      console.error("Ziggurat portfolio flipbook:", error);
      viewer.classList.add("is-error");
      loading.hidden = false;
      loading.innerHTML = `<strong>${config.labels?.error || "نمایش کاتالوگ با مشکل روبه‌رو شد."}</strong><button type="button" data-flipbook-retry>تلاش دوباره</button>`;
      status.textContent = "خطا در نمایش";
      loading.querySelector("[data-flipbook-retry]")?.addEventListener("click", () => {
        if (pageFlipInstance) {
          pageFlipInstance.destroy();
          pageFlipInstance = null;
        }
        book.replaceChildren();
        viewer.classList.remove("is-error", "is-ready");
        viewer.dataset.initialized = "false";
        initializeFlipbook(viewer);
      }, { once: true });
    };

    try {
      if (!pdfUrl || !config.pdfModuleUrl || !window.St?.PageFlip) {
        throw new Error("Flipbook dependencies are unavailable.");
      }

      const pdfjs = await import(config.pdfModuleUrl);
      pdfjs.GlobalWorkerOptions.workerSrc = config.workerUrl;
      let pdf;
      try {
        pdf = await pdfjs.getDocument({ url: pdfUrl }).promise;
        viewer.dataset.loadMode = "stream";
      } catch (firstError) {
        console.warn("Streaming PDF load failed; retrying as a complete file.", firstError);
        status.textContent = "تلاش دوباره برای دریافت فایل…";
        const retryUrl = `${pdfUrl}${pdfUrl.includes("?") ? "&" : "?"}zigurat_pdf_retry=${Date.now()}`;
        pdf = await pdfjs.getDocument({
          url: retryUrl,
          disableRange: true,
          disableStream: true,
        }).promise;
        viewer.dataset.loadMode = "full";
      }
      const firstPage = await pdf.getPage(1);
      const firstViewport = firstPage.getViewport({ scale: 1 });
      const aspect = firstViewport.height / firstViewport.width;
      const pageWidth = 720;
      const pageHeight = Math.round(pageWidth * aspect);
      const pageElements = [];
      const renderTasks = new Map();

      total.textContent = toPersianDigits(pdf.numPages);
      status.textContent = `${toPersianDigits(pdf.numPages)} صفحه`;

      for (let pageNumber = 1; pageNumber <= pdf.numPages; pageNumber += 1) {
        const pageElement = document.createElement("div");
        pageElement.className = "portfolio-flipbook__page";
        pageElement.dataset.pageNumber = String(pageNumber);
        if (pageNumber === 1 || pageNumber === pdf.numPages) {
          pageElement.dataset.density = "hard";
        }

        const canvas = document.createElement("canvas");
        canvas.setAttribute("aria-label", `صفحه ${toPersianDigits(pageNumber)} کاتالوگ`);
        const pageLabel = document.createElement("span");
        pageLabel.className = "portfolio-flipbook__page-number";
        pageLabel.textContent = toPersianDigits(pageNumber);
        pageElement.append(canvas, pageLabel);
        book.appendChild(pageElement);
        pageElements.push(pageElement);
      }

      const renderPage = (pageIndex) => {
        if (pageIndex < 0 || pageIndex >= pdf.numPages) return Promise.resolve();
        if (renderTasks.has(pageIndex)) return renderTasks.get(pageIndex);

        const task = (async () => {
          const page = pageIndex === 0 ? firstPage : await pdf.getPage(pageIndex + 1);
          const baseViewport = page.getViewport({ scale: 1 });
          const visiblePageWidth = window.innerWidth <= 720
            ? Math.max(320, Math.min(720, stage.clientWidth - 28))
            : Math.max(520, Math.min(900, stage.clientWidth / 2));
          const pixelRatio = Math.min(window.devicePixelRatio || 1, 1.6);
          const targetWidth = Math.min(1400, Math.round(visiblePageWidth * pixelRatio));
          const viewport = page.getViewport({ scale: targetWidth / baseViewport.width });
          const canvas = pageElements[pageIndex].querySelector("canvas");
          canvas.width = Math.round(viewport.width);
          canvas.height = Math.round(viewport.height);
          await page.render({ canvasContext: canvas.getContext("2d", { alpha: false }), viewport }).promise;
          pageElements[pageIndex].classList.add("is-rendered");
        })();

        renderTasks.set(pageIndex, task);
        return task;
      };

      const renderAround = (pageIndex) => {
        const indexes = [pageIndex - 2, pageIndex - 1, pageIndex, pageIndex + 1, pageIndex + 2];
        indexes.forEach((index) => renderPage(index).catch((error) => {
          console.error(`Could not render portfolio page ${index + 1}:`, error);
        }));
      };

      await Promise.all([renderPage(0), pdf.numPages > 1 ? renderPage(1) : Promise.resolve()]);

      const pageFlip = new window.St.PageFlip(book, {
        width: pageWidth,
        height: pageHeight,
        size: "stretch",
        minWidth: 260,
        maxWidth: pageWidth,
        minHeight: Math.round(260 * aspect),
        maxHeight: pageHeight,
        maxShadowOpacity: 0.48,
        showCover: true,
        mobileScrollSupport: true,
        usePortrait: true,
        flippingTime: 850,
        drawShadow: true,
        autoSize: true,
      });
      pageFlipInstance = pageFlip;

      pageFlip.loadFromHTML(pageElements);
      viewer.classList.add("is-ready");
      loading.hidden = true;
      renderAround(0);

      const updateControls = (pageIndex) => {
        current.textContent = toPersianDigits(Math.min(pageIndex + 1, pdf.numPages));
        previous.disabled = pageIndex <= 0;
        next.disabled = pageIndex >= pdf.numPages - 1;
        renderAround(pageIndex);
      };

      pageFlip.on("flip", (event) => updateControls(Number(event.data) || 0));
      pageFlip.on("changeOrientation", () => renderAround(pageFlip.getCurrentPageIndex()));
      updateControls(0);

      previous.addEventListener("click", () => pageFlip.flipPrev("top"));
      next.addEventListener("click", () => pageFlip.flipNext("top"));
      fullscreen.addEventListener("click", async () => {
        try {
          if (document.fullscreenElement) {
            await document.exitFullscreen();
          } else if (viewer.requestFullscreen) {
            await viewer.requestFullscreen();
          }
        } catch (error) {
          console.warn("Fullscreen is unavailable:", error);
        }
      });

      document.addEventListener("fullscreenchange", () => {
        viewer.classList.toggle("is-fullscreen", document.fullscreenElement === viewer);
        fullscreen.textContent = document.fullscreenElement === viewer ? "خروج از تمام‌صفحه" : "تمام‌صفحه";
      });

      viewer.addEventListener("keydown", (event) => {
        if (event.key === "ArrowLeft") pageFlip.flipNext("top");
        if (event.key === "ArrowRight") pageFlip.flipPrev("top");
      });
      viewer.tabIndex = 0;
    } catch (error) {
      fail(error);
    }
  };

  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("[data-portfolio-flipbook]").forEach((viewer) => {
      if (!("IntersectionObserver" in window)) {
        initializeFlipbook(viewer);
        return;
      }
      const observer = new IntersectionObserver(
        (entries) => {
          if (!entries.some((entry) => entry.isIntersecting)) return;
          observer.disconnect();
          initializeFlipbook(viewer);
        },
        { rootMargin: "500px 0px" }
      );
      observer.observe(viewer);
    });
  });
})();
