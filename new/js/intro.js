document.addEventListener("DOMContentLoaded", () => {
  // Smooth reveal animation for the redesigned Reunite homepage.
  const elements = document.querySelectorAll(
    ".hero-content > *, .tips-card, .stat, .step, .story-card, .community"
  );

  const isTipsCard = (el) => el.classList.contains("tips-card");

  // If the browser does not support IntersectionObserver,
  // show everything immediately.
  if (!("IntersectionObserver" in window)) {
    elements.forEach((element) => {
      element.style.opacity = "1";
    });
    return;
  }

  elements.forEach((element) => {
    element.style.opacity = "0";
    if (!isTipsCard(element)) {
      element.style.transform = "translateY(14px)";
    }
    element.style.transition =
      "opacity 0.55s ease, transform 0.55s ease";
  });

  const observer = new IntersectionObserver(
    (entries, obs) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;

        entry.target.style.opacity = "1";
        if (!isTipsCard(entry.target)) {
          entry.target.style.transform = "translateY(0)";
        }
        obs.unobserve(entry.target);
      });
    },
    {
      threshold: 0.12
    }
  );

  elements.forEach((element) => observer.observe(element));
});
