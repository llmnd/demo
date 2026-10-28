(() => {
  const slides = Array.from(document.querySelectorAll(".slide"));
  const dotNav = document.getElementById("dot-nav");
  const currentLabel = document.getElementById("current-slide");
  const progressFill = document.getElementById("progress-fill");
  const previousButton = document.getElementById("previous-slide");
  const nextButton = document.getElementById("next-slide");
  const fullscreenButton = document.querySelector(".fullscreen-button");
  let activeIndex = 0;
  let touchStartX = 0;
  let touchStartY = 0;

  const totalSlides = slides.length;
  const counterTotal = document.querySelector(".slide-counter > span");
  const coverTotal = document.querySelector(".cover-index span:last-child");
  if (counterTotal) counterTotal.textContent = String(totalSlides);
  if (coverTotal) coverTotal.textContent = String(totalSlides).padStart(2, "0");
  slides.forEach((slide, index) => {
    slide.setAttribute("aria-label", `Diapositive ${index + 1} sur ${totalSlides}`);
    const numberTotal = slide.querySelector(".slide-number span");
    if (numberTotal) numberTotal.textContent = `/ ${totalSlides}`;
  });

  const dots = slides.map((slide, index) => {
    const button = document.createElement("button");
    button.type = "button";
    button.setAttribute("aria-label", `Aller à la diapositive ${index + 1} : ${slide.dataset.title}`);
    button.title = `${String(index + 1).padStart(2, "0")} · ${slide.dataset.title}`;
    button.addEventListener("click", () => showSlide(index));
    dotNav.appendChild(button);
    return button;
  });

  function showSlide(index, updateHash = true) {
    activeIndex = Math.max(0, Math.min(index, slides.length - 1));

    slides.forEach((slide, slideIndex) => {
      const active = slideIndex === activeIndex;
      slide.classList.toggle("is-active", active);
      slide.setAttribute("aria-hidden", String(!active));
      if (active) slide.scrollTop = 0;
    });

    dots.forEach((dot, dotIndex) => {
      if (dotIndex === activeIndex) {
        dot.setAttribute("aria-current", "true");
      } else {
        dot.removeAttribute("aria-current");
      }
    });

    const readableIndex = String(activeIndex + 1).padStart(2, "0");
    currentLabel.textContent = readableIndex;
    progressFill.style.width = `${((activeIndex + 1) / slides.length) * 100}%`;
    previousButton.disabled = activeIndex === 0;
    nextButton.disabled = activeIndex === slides.length - 1;
    previousButton.setAttribute("aria-disabled", String(previousButton.disabled));
    nextButton.setAttribute("aria-disabled", String(nextButton.disabled));

    if (updateHash) {
      history.replaceState(null, "", `#slide-${activeIndex + 1}`);
    }
  }

  function goNext() {
    if (activeIndex < slides.length - 1) showSlide(activeIndex + 1);
  }

  function goPrevious() {
    if (activeIndex > 0) showSlide(activeIndex - 1);
  }

  function toggleFullscreen() {
    if (!document.fullscreenElement) {
      document.documentElement.requestFullscreen();
    } else {
      document.exitFullscreen();
    }
  }

  previousButton.addEventListener("click", goPrevious);
  nextButton.addEventListener("click", goNext);
  fullscreenButton.addEventListener("click", toggleFullscreen);

  document.querySelectorAll("[data-go]").forEach((button) => {
    button.addEventListener("click", () => showSlide(Number(button.dataset.go) - 1));
  });

  document.addEventListener("keydown", (event) => {
    const target = event.target;
    const isTyping = target instanceof HTMLElement &&
      (target.isContentEditable || ["INPUT", "TEXTAREA", "SELECT"].includes(target.tagName));
    if (isTyping || event.altKey || event.ctrlKey || event.metaKey) return;

    if (["ArrowRight", "PageDown", " "].includes(event.key)) {
      event.preventDefault();
      goNext();
    } else if (["ArrowLeft", "PageUp"].includes(event.key)) {
      event.preventDefault();
      goPrevious();
    } else if (event.key === "Home") {
      showSlide(0);
    } else if (event.key === "End") {
      showSlide(slides.length - 1);
    } else if (event.key.toLowerCase() === "f") {
      toggleFullscreen();
    } else if (event.key === "Escape" && document.fullscreenElement) {
      document.exitFullscreen();
    }
  });

  document.addEventListener("fullscreenchange", () => {
    const fullscreen = Boolean(document.fullscreenElement);
    fullscreenButton.setAttribute("aria-label", fullscreen ? "Quitter le plein écran" : "Activer le plein écran");
    fullscreenButton.title = fullscreen ? "Quitter le plein écran (Échap)" : "Plein écran (F)";
  });

  const deck = document.getElementById("deck");
  deck.addEventListener("touchstart", (event) => {
    touchStartX = event.changedTouches[0].clientX;
    touchStartY = event.changedTouches[0].clientY;
  }, { passive: true });

  deck.addEventListener("touchend", (event) => {
    const deltaX = event.changedTouches[0].clientX - touchStartX;
    const deltaY = event.changedTouches[0].clientY - touchStartY;
    if (Math.abs(deltaX) < 55 || Math.abs(deltaX) < Math.abs(deltaY) * 1.25) return;
    if (deltaX < 0) goNext();
    else goPrevious();
  }, { passive: true });

  const requestedSlide = Number(window.location.hash.match(/^#slide-(\d+)$/)?.[1] || 1);
  showSlide(requestedSlide - 1, false);
})();
