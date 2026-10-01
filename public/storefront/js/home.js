/*!
 * Radharani Jewellery Works - home page
 *  - "New this week" row rendered from RJ_DATA (pieces listed recently)
 *  - first-visit loader, hero carousel, pinned collections pan,
 *    price bill, bridal arch, exchange verbs, heritage words
 */
(function () {
  "use strict";
  var RJ = window.RJ,
    D = RJ.data,
    doc = document;

  /* ---------- new arrivals from data ---------- */
  var row = doc.querySelector("[data-row]");
  if (row) {
    var fresh = D.products.filter(function (p) {
      return p.isNew;
    });
    /* nothing listed recently: show the latest pieces rather than an empty row */
    if (!fresh.length)
      fresh = D.products
        .slice()
        .sort(function (a, b) {
          return b.listedAt - a.listedAt;
        })
        .slice(0, 8);
    if (!fresh.length) row.closest(".arrivals").hidden = true;
    row.innerHTML = fresh
      .map(function (p) {
        return RJ.card(p);
      })
      .join("");
    var step = function (dir) {
      row.scrollBy({ left: dir * row.clientWidth * 0.8, behavior: "smooth" });
    };
    var rp = doc.querySelector("[data-row-prev]"),
      rn = doc.querySelector("[data-row-next]");
    if (rp)
      rp.addEventListener("click", function () {
        step(-1);
      });
    if (rn)
      rn.addEventListener("click", function () {
        step(1);
      });
  }

  /* ---------- hero carousel (works with or without GSAP) ---------- */
  var stage = doc.querySelector("[data-carousel]");
  var slides = [].slice.call(stage.querySelectorAll(".slide"));
  var dots = [].slice.call(stage.querySelectorAll(".dot"));
  var cur = 0,
    busy = false,
    timer = null,
    DURATION = 6.5;
  var anim = function () {
    return window.gsap && !RJ.reduce;
  };

  function markDots() {
    dots.forEach(function (d, i) {
      d.classList.toggle("is-done", i < cur);
      d.setAttribute("aria-selected", i === cur ? "true" : "false");
      if (window.gsap)
        gsap.set(d.querySelector("i"), { scaleX: i < cur ? 1 : 0 });
    });
  }
  function runTimer() {
    if (!anim()) return;
    if (timer) timer.kill();
    timer = gsap.fromTo(
      dots[cur].querySelector("i"),
      { scaleX: 0 },
      {
        scaleX: 1,
        duration: DURATION,
        ease: "none",
        onComplete: function () {
          go(cur + 1, 1);
        },
      },
    );
  }
  function go(n, dir) {
    n = (n + slides.length) % slides.length;
    if (n === cur || busy) return;
    var prev = slides[cur],
      next = slides[n];
    cur = n;
    markDots();
    if (!anim()) {
      prev.classList.remove("is-active");
      next.classList.add("is-active");
      return;
    }
    busy = true;
    gsap.set(slides, { zIndex: 0 });
    gsap.set(prev, { zIndex: 1 });
    gsap.set(next, { zIndex: 2 });
    next.classList.add("is-active");
    gsap
      .timeline({
        onComplete: function () {
          prev.classList.remove("is-active");
          busy = false;
          runTimer();
        },
      })
      .fromTo(
        next,
        { clipPath: dir > 0 ? "inset(0% 0% 0% 100%)" : "inset(0% 100% 0% 0%)" },
        { clipPath: "inset(0% 0% 0% 0%)", duration: 1.2, ease: "expo.inOut" },
        0,
      )
      .fromTo(
        next.querySelector(".slide__media"),
        { scale: 1.15 },
        { scale: 1, duration: 1.8, ease: "expo.out" },
        0.1,
      )
      .to(
        prev.querySelector(".slide__media"),
        { xPercent: dir > 0 ? -12 : 12, duration: 1.2, ease: "expo.inOut" },
        0,
      )
      .set(prev.querySelector(".slide__media"), { xPercent: 0 })
      .fromTo(
        next.querySelectorAll("[data-anim]"),
        { y: 34, autoAlpha: 0 },
        { y: 0, autoAlpha: 1, duration: 1, stagger: 0.09, ease: "expo.out" },
        0.55,
      );
  }
  dots.forEach(function (d, i) {
    d.addEventListener("click", function () {
      go(i, i > cur ? 1 : -1);
    });
  });
  stage.querySelector("[data-prev]").addEventListener("click", function () {
    go(cur - 1, -1);
  });
  stage.querySelector("[data-next]").addEventListener("click", function () {
    go(cur + 1, 1);
  });
  stage.addEventListener("mouseenter", function () {
    if (timer) timer.pause();
  });
  stage.addEventListener("mouseleave", function () {
    if (timer) timer.resume();
  });
  var sx = null;
  stage.addEventListener(
    "touchstart",
    function (e) {
      sx = e.touches[0].clientX;
    },
    { passive: true },
  );
  stage.addEventListener("touchend", function (e) {
    if (sx === null) return;
    var dx = e.changedTouches[0].clientX - sx;
    if (Math.abs(dx) > 50) go(cur + (dx < 0 ? 1 : -1), dx < 0 ? 1 : -1);
    sx = null;
  });
  markDots();

  /* ---------- the loader plays once per visit; afterwards the curtain is enough ---------- */
  var seen = false;
  try {
    seen = sessionStorage.getItem("rj-seen") === "1";
    sessionStorage.setItem("rj-seen", "1");
  } catch (e) {}
  var loader = doc.querySelector(".loader");
  if (seen && loader) {
    loader.remove();
    loader = null;
  }

  RJ.boot({ smooth: true, keepLoader: !!loader }, function (ctx) {
    if (!ctx.gsap) {
      if (loader) loader.remove();
      return;
    }
    var mm = ctx.mm;

    /* first slide arrives after the loader (or curtain) */
    var first = slides[0];
    gsap.set(first.querySelectorAll("[data-anim]"), { y: 34, autoAlpha: 0 });
    gsap.set(stage, { clipPath: "inset(6% 6% 6% 6% round 24px)" });
    gsap.set(first.querySelector(".slide__media"), { scale: 1.2 });
    var heroIn = function () {
      gsap
        .timeline({ onComplete: runTimer })
        .to(
          stage,
          {
            clipPath: "inset(0% 0% 0% 0% round 24px)",
            duration: 1.5,
            ease: "expo.inOut",
          },
          0,
        )
        .to(
          first.querySelector(".slide__media"),
          { scale: 1, duration: 2.2, ease: "expo.out" },
          0.1,
        )
        .to(
          first.querySelectorAll("[data-anim]"),
          { y: 0, autoAlpha: 1, duration: 1.1, stagger: 0.1, ease: "expo.out" },
          0.6,
        );
    };
    if (loader) {
      var curtain = doc.querySelector(".curtain");
      if (curtain) curtain.classList.add("is-gone");
      gsap
        .timeline({
          onComplete: function () {
            loader.remove();
          },
        })
        .to(".loader__mark", {
          autoAlpha: 1,
          y: 0,
          duration: 0.8,
          ease: "expo.out",
        })
        .to(
          ".loader__bar i",
          { scaleX: 1, duration: 1, ease: "power2.inOut" },
          0.1,
        )
        .to(
          ".loader__inner",
          { autoAlpha: 0, y: -16, duration: 0.45, ease: "power2.in" },
          "+=0.05",
        )
        .to(
          ".loader",
          { autoAlpha: 0, duration: 0.6, ease: "power2.out" },
          "-=0.1",
        )
        .add(heroIn, "-=0.45");
    } else {
      gsap.delayedCall(0.35, heroIn);
    }

    /* category circles and new arrivals arrive as a group */
    gsap.from(".cat", {
      y: 30,
      autoAlpha: 0,
      scale: 0.94,
      duration: 0.9,
      ease: "expo.out",
      stagger: 0.05,
      scrollTrigger: { trigger: ".cat-grid", start: "top 88%", once: true },
    });
    gsap.from("[data-row] .pcard", {
      y: 40,
      autoAlpha: 0,
      duration: 1,
      ease: "expo.out",
      stagger: 0.08,
      scrollTrigger: { trigger: "[data-row]", start: "top 88%", once: true },
    });

    /* collections: pinned horizontal pan (desktop) */
    mm.add("(min-width: 900px)", function () {
      var section = doc.querySelector(".collections");
      var track = doc.querySelector(".col-track");
      if (!section || !track) return; // no collections on the site yet
      var dist = function () {
        return Math.max(0, track.scrollWidth - window.innerWidth);
      };
      var pan = gsap.to(track, {
        x: function () {
          return -dist();
        },
        ease: "none",
        scrollTrigger: {
          trigger: section,
          start: "top top",
          end: function () {
            return "+=" + dist();
          },
          pin: true,
          scrub: 1,
          invalidateOnRefresh: true,
        },
      });
      gsap.utils.toArray(".col-card .media img").forEach(function (im) {
        gsap.fromTo(
          im,
          { xPercent: -6, scale: 1.14 },
          {
            xPercent: 6,
            ease: "none",
            scrollTrigger: {
              trigger: im.closest(".col-card"),
              containerAnimation: pan,
              start: "left right",
              end: "right left",
              scrub: true,
            },
          },
        );
      });
    });

    /* price: heading holds while the bill fills (desktop) */
    mm.add("(min-width: 960px)", function () {
      ScrollTrigger.create({
        trigger: ".price-head",
        start: "top 120px",
        endTrigger: "[data-bill]",
        end: "bottom 70%",
        pin: true,
        pinSpacing: false,
      });
    });

    /* bill lines appear and numbers count up */
    var bill = doc.querySelector("[data-bill]");
    if (bill) {
      var rows = bill.querySelectorAll("[data-bill-row]");
      gsap.set(rows, { autoAlpha: 0, y: 16 });
      ScrollTrigger.create({
        trigger: bill,
        start: "top 72%",
        once: true,
        onEnter: function () {
          gsap.to(rows, {
            autoAlpha: 1,
            y: 0,
            duration: 0.7,
            ease: "expo.out",
            stagger: 0.22,
          });
          rows.forEach(function (r, i) {
            var b = r.querySelector("[data-count]");
            var end = parseFloat(b.getAttribute("data-count"));
            var pre = b.getAttribute("data-prefix") || "",
              suf = b.getAttribute("data-suffix") || "";
            var o = { v: 0 };
            gsap.to(o, {
              v: end,
              duration: 1.2,
              delay: i * 0.22,
              ease: "power2.out",
              onUpdate: function () {
                b.textContent =
                  pre + Math.round(o.v).toLocaleString("en-IN") + suf;
              },
            });
          });
        },
      });
    }

    /* bridal: arch opens to the full frame */
    mm.add(
      { desk: "(min-width: 900px)", mob: "(max-width: 899px)" },
      function (c) {
        var from = c.conditions.desk
          ? "inset(30% 33% 5% 33% round 999px 999px 18px 18px)"
          : "inset(22% 10% 6% 10% round 999px 999px 18px 18px)";
        gsap
          .timeline({
            scrollTrigger: {
              trigger: ".bridal",
              start: "top top",
              end: "+=140%",
              pin: true,
              scrub: 1,
            },
          })
          .fromTo(
            ".bridal__frame",
            { clipPath: from },
            {
              clipPath: "inset(0% 0% 0% 0% round 0px 0px 0px 0px)",
              ease: "none",
              duration: 1,
            },
            0,
          )
          .fromTo(
            ".bridal__frame img",
            { scale: 1.25 },
            { scale: 1, ease: "none", duration: 1 },
            0,
          )
          .to(".bridal__intro", { autoAlpha: 0, y: -40, duration: 0.3 }, 0)
          .fromTo(
            ".bridal__frame",
            { "--scrim": 0 },
            { "--scrim": 1, duration: 0.3 },
            0.6,
          )
          .fromTo(
            ".bridal__copy",
            { autoAlpha: 0, y: 40 },
            { autoAlpha: 1, y: 0, duration: 0.35, ease: "power2.out" },
            0.65,
          );
      },
    );

    /* heritage words light up at reading pace */
    var words = doc.querySelector("[data-words]");
    if (words) {
      var ws = new SplitText(words, { type: "words", wordsClass: "w" });
      gsap.to(ws.words, {
        opacity: 1,
        ease: "none",
        stagger: 0.1,
        scrollTrigger: {
          trigger: words,
          start: "top 80%",
          end: "bottom 45%",
          scrub: 1,
        },
      });
    }

    /* exchange verbs light up in order */
    gsap.utils.toArray(".verb").forEach(function (v, i) {
      ScrollTrigger.create({
        trigger: ".verbs",
        start: "top " + (80 - i * 13) + "%",
        toggleClass: { targets: v, className: "is-lit" },
      });
    });
  });
})();
