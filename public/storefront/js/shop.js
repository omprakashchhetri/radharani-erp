/*!
 * Radharani Jewellery Works - listing page (/shop)
 *
 * URL parameters (comma separated for more than one value):
 *   category, metal, purity, budget, collection, occasion, for, q, sort, saved=1
 * e.g. /shop?category=earrings,rings&budget=0-25000&sort=price-asc
 */
(function () {
  "use strict";
  var RJ = window.RJ,
    D = RJ.data,
    doc = document,
    esc = RJ.esc;
  var $ = function (s) {
    return doc.querySelector(s);
  };

  var GROUPS = [
    {
      key: "category",
      title: "Category",
      options: D.categories.map(function (c) {
        return { v: c.slug, label: c.name };
      }),
      test: function (p, v) {
        return p.category === v;
      },
    },
    {
      key: "budget",
      title: "Price",
      options: D.budgets.map(function (b) {
        return { v: b.slug, label: b.label };
      }),
      test: function (p, v) {
        var b = RJ.find(D.budgets, v);
        return b && p.price >= b.min && p.price < b.max;
      },
    },
    {
      key: "metal",
      title: "Metal",
      options: ["gold", "silver", "platinum", "titanium"]
        .filter(function (m) {
          return D.metals.indexOf(m) > -1;
        })
        .map(function (m) {
          return { v: m, label: m.charAt(0).toUpperCase() + m.slice(1) };
        }),
      test: function (p, v) {
        return p.metal === v;
      },
    },
    {
      key: "purity",
      title: "Purity",
      options: Object.keys(D.purities).map(function (k) {
        return { v: k, label: D.purities[k].label };
      }),
      test: function (p, v) {
        return p.purity === v;
      },
    },
    {
      key: "occasion",
      title: "Occasion",
      options: D.occasions.map(function (o) {
        return { v: o.slug, label: o.name };
      }),
      test: function (p, v) {
        return (p.occasions || []).indexOf(v) > -1;
      },
    },
    {
      key: "for",
      title: "For",
      options: D.audiences.map(function (a) {
        return { v: a.slug, label: a.name };
      }),
      test: function (p, v) {
        return (p["for"] || []).indexOf(v) > -1;
      },
    },
    {
      key: "collection",
      title: "Collection",
      options: D.collections.map(function (c) {
        return { v: c.slug, label: c.name };
      }),
      test: function (p, v) {
        return p.collection === v;
      },
    },
  ];
  var groupBy = {};
  GROUPS.forEach(function (g) {
    groupBy[g.key] = g;
  });

  /* ---------- state from URL ---------- */
  var url = new URLSearchParams(location.search);
  var state = {
    q: (url.get("q") || "").trim(),
    sort: url.get("sort") || "featured",
    saved: url.get("saved") === "1",
  };
  GROUPS.forEach(function (g) {
    var raw = url.get(g.key);
    state[g.key] = raw ? raw.split(",").filter(Boolean) : [];
  });

  var labelOf = function (key, v) {
    var o = groupBy[key].options.filter(function (x) {
      return x.v === v;
    })[0];
    return o ? o.label : v;
  };

  /* ---------- matching ---------- */
  var matchesQuery = function (p) {
    if (!state.q) return true;
    var cat = RJ.find(D.categories, p.category),
      col = p.collection ? RJ.find(D.collections, p.collection) : null;
    var hay = [
      p.name,
      p.code,
      cat && cat.name,
      col && col.name,
      p.stones,
      p.description,
      D.purities[p.purity].label,
      (p.occasions || []).join(" "),
    ]
      .join(" ")
      .toLowerCase();
    return state.q
      .toLowerCase()
      .split(/\s+/)
      .every(function (t) {
        var stem = t.replace(/s$/, "");
        return hay.indexOf(t) > -1 || hay.indexOf(stem) > -1;
      });
  };
  var matches = function (p, skipKey) {
    if (state.saved && RJ.saved().indexOf(p.id) < 0) return false;
    if (!matchesQuery(p)) return false;
    for (var i = 0; i < GROUPS.length; i++) {
      var g = GROUPS[i],
        sel = state[g.key];
      if (g.key === skipKey || !sel.length) continue;
      if (
        !sel.some(function (v) {
          return g.test(p, v);
        })
      )
        return false;
    }
    return true;
  };
  var sorters = {
    featured: function (a, b) {
      return (
        (b.isBestseller ? 2 : 0) +
        (b.isNew ? 1 : 0) -
        ((a.isBestseller ? 2 : 0) + (a.isNew ? 1 : 0))
      );
    },
    new: function (a, b) {
      return b.listedAt - a.listedAt;
    },
    popular: function (a, b) {
      return (b.isBestseller ? 1 : 0) - (a.isBestseller ? 1 : 0);
    },
    "price-asc": function (a, b) {
      return a.price - b.price;
    },
    "price-desc": function (a, b) {
      return b.price - a.price;
    },
    "weight-desc": function (a, b) {
      return b.netWt - a.netWt;
    },
  };

  /* ---------- heading, crumbs, art ---------- */
  var only = function (key) {
    var n = 0;
    GROUPS.forEach(function (g) {
      n += state[g.key].length;
    });
    return state[key].length === 1 && n === 1 && !state.q
      ? state[key][0]
      : null;
  };
  function heading() {
    var t = "All jewellery",
      blurb =
        "Every piece is BIS hallmarked, weighed in front of you and priced at today's rate.",
      art = null;
    var c;
    if (state.saved) {
      t = "Saved <em>pieces</em>";
      blurb =
        "Pieces you saved on this device. Open one to enquire, or show this list in the showroom.";
    } else if (state.q) {
      t = "Results for <em>&ldquo;" + esc(state.q) + "&rdquo;</em>";
      blurb =
        "Not what you meant? Try a category below, or ask us in the showroom.";
    } else if ((c = only("category")) && RJ.find(D.categories, c)) {
      var cat = RJ.find(D.categories, c);
      t = esc(cat.name);
      blurb = cat.blurb || blurb;
      art = cat.img;
    } else if ((c = only("collection")) && RJ.find(D.collections, c)) {
      var col = RJ.find(D.collections, c);
      t = esc(col.name) + " <em>collection</em>";
      blurb = col.blurb || blurb;
      art = col.img;
    } else if ((c = only("occasion"))) {
      t =
        c === "bridal"
          ? "Bridal <em>jewellery</em>"
          : labelOf("occasion", c) + " <em>jewellery</em>";
      if (c === "bridal") {
        blurb =
          "Sets, bangles, naths and mangalsutras for the wedding, held for your family between visits.";
        art = "1733937108021-d5db469f2216";
      }
    } else if ((c = only("for"))) {
      t = "For <em>" + labelOf("for", c).toLowerCase() + "</em>";
    } else if ((c = only("metal"))) {
      t = labelOf("metal", c) + " <em>jewellery</em>";
      if (c === "silver") {
        blurb =
          "92.5 hallmarked silver for gifting, daily wear and the pooja room.";
        art = "1573408301185-9146fe634ad0";
      }
    } else if ((c = only("purity"))) {
      t = labelOf("purity", c) + " <em>jewellery</em>";
    } else if ((c = only("budget"))) {
      t = labelOf("budget", c);
      blurb =
        "Prices are worked out from today's rate, so a piece can move between bands as the rate changes.";
    }

    $("[data-title]").innerHTML = t;
    $("[data-blurb]").textContent = blurb;
    doc.title = $("[data-title]").textContent + " | Radharani Jewellery Works";
    var artEl = $("[data-art]");
    if (art) {
      artEl.hidden = false;
      artEl.querySelector("img").src = RJ.img(art, { w: 400, h: 540 });
    } else artEl.hidden = true;

    var crumbs =
      '<a href="' + RJ.urls.home + '">Home</a><i class="ph ph-caret-right"></i>';
    var plain = $("[data-title]").textContent;
    crumbs +=
      plain === "All jewellery"
        ? '<span aria-current="page">All jewellery</span>'
        : '<a href="' + RJ.shop() + '">Jewellery</a><i class="ph ph-caret-right"></i><span aria-current="page">' +
          esc(plain) +
          "</span>";
    $("[data-crumbs]").innerHTML = crumbs;
  }

  /* ---------- category strip ---------- */
  function strip() {
    var onlyCat = state.category.length === 1 ? state.category[0] : null;
    var noneOn = !state.category.length && !state.metal.length;
    var html =
      '<a href="' + RJ.shop() + '" data-strip-cat="" class="' +
      (noneOn ? "is-on" : "") +
      '"><span class="strip__all"><i class="ph ph-sparkle"></i></span>All</a>';
    html += D.categories
      .map(function (c) {
        return (
          '<a href="' +
          RJ.shop({ category: c.slug }) +
          '" data-strip-cat="' +
          c.slug +
          '" class="' +
          (onlyCat === c.slug ? "is-on" : "") +
          '"><figure class="media"><img loading="lazy" src="' +
          RJ.img(c.img, { w: 160, h: 160 }) +
          '" alt=""></figure>' +
          esc(c.name) +
          "</a>"
        );
      })
      .join("");
    if (D.metals.indexOf("silver") > -1)
    html +=
      '<a href="' +
      RJ.shop({ metal: "silver" }) +
      '" data-strip-metal="silver" class="' +
      (state.metal.length === 1 &&
      state.metal[0] === "silver" &&
      !state.category.length
        ? "is-on"
        : "") +
      '"><figure class="media"><img loading="lazy" src="' +
      RJ.img("1573408301185-9146fe634ad0", { w: 160, h: 160 }) +
      '" alt=""></figure>Silver</a>';
    $("[data-strip]").innerHTML = html;
  }
  $("[data-strip]").addEventListener("click", function (e) {
    var a = e.target.closest("a");
    if (!a) return;
    e.preventDefault();
    e.stopPropagation();
    GROUPS.forEach(function (g) {
      state[g.key] = [];
    });
    state.q = "";
    state.saved = false;
    if (a.hasAttribute("data-strip-metal"))
      state.metal = [a.getAttribute("data-strip-metal")];
    else if (a.getAttribute("data-strip-cat"))
      state.category = [a.getAttribute("data-strip-cat")];
    update(true);
  });

  /* ---------- filter groups with live counts ---------- */
  function groups() {
    $("[data-groups]").innerHTML = GROUPS.map(function (g, gi) {
      var opts = g.options
        .map(function (o) {
          var n = D.products.filter(function (p) {
            return matches(p, g.key) && g.test(p, o.v);
          }).length;
          var on = state[g.key].indexOf(o.v) > -1;
          if (!n && !on) return "";
          return (
            '<li><label class="opt"><input type="checkbox" data-key="' +
            g.key +
            '" value="' +
            esc(o.v) +
            '"' +
            (on ? " checked" : "") +
            "><span>" +
            esc(o.label) +
            "</span><small>" +
            n +
            "</small></label></li>"
          );
        })
        .join("");
      if (!opts) return "";
      var open = gi < 3 || state[g.key].length ? " open" : "";
      return (
        '<details class="fgroup"' +
        open +
        "><summary>" +
        g.title +
        ' <i class="ph ph-caret-down"></i></summary><ul>' +
        opts +
        "</ul></details>"
      );
    }).join("");
  }
  $("[data-groups]").addEventListener("change", function (e) {
    var inp = e.target;
    if (!inp.matches("input[data-key]")) return;
    var key = inp.getAttribute("data-key"),
      v = inp.value,
      list = state[key];
    var i = list.indexOf(v);
    if (inp.checked && i < 0) list.push(v);
    if (!inp.checked && i > -1) list.splice(i, 1);
    update(true);
  });

  /* ---------- active pills ---------- */
  function pills() {
    var out = [];
    if (state.saved)
      out.push(
        '<button class="pill" data-pill="saved">Saved only <i class="ph ph-x"></i></button>',
      );
    if (state.q)
      out.push(
        '<button class="pill" data-pill="q">&ldquo;' +
          esc(state.q) +
          '&rdquo; <i class="ph ph-x"></i></button>',
      );
    GROUPS.forEach(function (g) {
      state[g.key].forEach(function (v) {
        out.push(
          '<button class="pill" data-pill="' +
            g.key +
            '" data-v="' +
            esc(v) +
            '">' +
            esc(labelOf(g.key, v)) +
            ' <i class="ph ph-x"></i></button>',
        );
      });
    });
    $("[data-pills]").innerHTML = out.join("");
  }
  $("[data-pills]").addEventListener("click", function (e) {
    var b = e.target.closest("[data-pill]");
    if (!b) return;
    var k = b.getAttribute("data-pill");
    if (k === "saved") state.saved = false;
    else if (k === "q") state.q = "";
    else
      state[k] = state[k].filter(function (v) {
        return v !== b.getAttribute("data-v");
      });
    update(true);
  });

  /* ---------- results ---------- */
  function results(animate) {
    var list = D.products
      .filter(function (p) {
        return matches(p);
      })
      .slice();
    list.sort(sorters[state.sort] || sorters.featured);
    var n = list.length;
    $("[data-count]").textContent = n + (n === 1 ? " piece" : " pieces");
    doc.querySelectorAll("[data-show]").forEach(function (b) {
      b.textContent = "Show " + n + (n === 1 ? " piece" : " pieces");
    });

    var grid = $("[data-grid]");
    if (!n) {
      grid.innerHTML =
        state.saved &&
        !state.q &&
        !GROUPS.some(function (g) {
          return state[g.key].length;
        })
          ? '<div class="empty"><i class="ph ph-heart"></i><h3>Nothing saved yet</h3><p>Tap the heart on any piece to keep it here. Your list stays on this device.</p><a class="btn btn--solid" href="' + RJ.shop() + '">Browse jewellery</a></div>'
          : '<div class="empty"><i class="ph ph-magnifying-glass"></i><h3>No pieces match</h3><p>Try removing a filter. Many more pieces are in the showroom than online.</p><button class="btn btn--solid" data-clear>Clear all filters</button></div>';
    } else {
      var html = list.map(function (p) {
        return RJ.card(p);
      });
      if (n >= 8 && !state.saved) {
        html.splice(
          6,
          0,
          '<aside class="promo"><figure class="media"><img loading="lazy" src="' +
            RJ.img("1768359666502-306694fa6fcf", { w: 700 }) +
            '" alt="Gold bracelets on display in the showroom"></figure>' +
            '<div class="promo__txt"><h3>Most of our pieces never make it online.</h3><p>New stock arrives every week and much of it sells in the showroom first. Come and see the full trays.</p><div><a class="link" href="' + RJ.urls.home + '#visit">Plan a visit <i class="ph ph-arrow-right"></i></a></div></div></aside>',
        );
      }
      grid.innerHTML = html.join("");
    }
    RJ.paintSaved();
    if (animate) RJ.reveal(grid.children);
  }

  /* ---------- sort ---------- */
  var sortSel = $("[data-sort]");
  sortSel.value = sorters[state.sort] ? state.sort : "featured";
  sortSel.addEventListener("change", function () {
    state.sort = sortSel.value;
    update(true);
  });

  /* ---------- clear ---------- */
  doc.addEventListener("click", function (e) {
    if (!e.target.closest("[data-clear]")) return;
    GROUPS.forEach(function (g) {
      state[g.key] = [];
    });
    state.q = "";
    state.saved = false;
    update(true);
  });

  /* ---------- mobile filter sheet ---------- */
  var sheet = $("[data-filters]"),
    scrim = $("[data-scrim]");
  var setSheet = function (open) {
    sheet.classList.toggle("is-open", open);
    scrim.classList.toggle("is-on", open);
    doc.body.classList.toggle("is-locked", open);
  };
  $("[data-open-filters]").addEventListener("click", function () {
    setSheet(true);
  });
  doc.querySelectorAll("[data-close-filters]").forEach(function (b) {
    b.addEventListener("click", function () {
      setSheet(false);
    });
  });
  scrim.addEventListener("click", function () {
    setSheet(false);
  });

  /* saved list updates live when a heart is tapped */
  doc.addEventListener("rj:saved", function () {
    if (state.saved) update(false);
  });

  /* ---------- URL sync ---------- */
  function syncUrl() {
    var p = {};
    GROUPS.forEach(function (g) {
      if (state[g.key].length) p[g.key] = state[g.key].join(",");
    });
    if (state.q) p.q = state.q;
    if (state.sort !== "featured") p.sort = state.sort;
    if (state.saved) p.saved = "1";
    history.replaceState(null, "", RJ.shop(p));
    var input = doc.getElementById("q");
    if (input) input.value = state.q;
  }

  function update(animate) {
    heading();
    strip();
    groups();
    pills();
    results(animate);
    syncUrl();
    if (window.ScrollTrigger) ScrollTrigger.refresh();
  }

  update(false);

  RJ.boot({ smooth: false, keepHeader: true }, function (ctx) {
    if (!ctx.gsap) return;
    gsap.from(".plp-head__txt > *", {
      y: 30,
      autoAlpha: 0,
      duration: 1,
      ease: "expo.out",
      stagger: 0.08,
      delay: 0.35,
    });
    gsap.from("[data-art]", {
      clipPath: "inset(100% 0% 0% 0%)",
      duration: 1.3,
      ease: "expo.inOut",
      delay: 0.3,
    });
    gsap.from(".strip > a", {
      y: 20,
      autoAlpha: 0,
      duration: 0.8,
      ease: "expo.out",
      stagger: 0.04,
      delay: 0.5,
    });
    gsap.from("[data-grid] > *", {
      y: 40,
      autoAlpha: 0,
      duration: 1,
      ease: "expo.out",
      stagger: 0.05,
      delay: 0.55,
      clearProps: "transform",
    });
  });
})();
