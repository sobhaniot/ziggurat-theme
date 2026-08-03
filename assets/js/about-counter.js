document.addEventListener("DOMContentLoaded", function () {
  const counters = document.querySelectorAll(".counter");
  if (!counters.length) {
    return;
  }
  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          const counter = entry.target;
          const target = parseInt(counter.dataset.count);
          let current = 0;
          const speed = 150;
          const update = () => {
            const increment = Math.ceil(target / speed);
            current += increment;
            if (current >= target) {
              counter.innerHTML = target;
              return;
            }
            counter.innerHTML = current;
            requestAnimationFrame(update);
          };
          update();
          observer.unobserve(counter);
        }
      });
    },
    {
      threshold: 0.5,
    },
  );
  counters.forEach((counter) => {
    observer.observe(counter);
  });
});
