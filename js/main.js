/* ============================================================
   メリクレット | melikret  OFFICIAL WEBSITE  -  main.js（リニューアル版 2026-11）
   - ローディング → 入場
   - ヘッダー / ハンバーガーメニュー
   - 出現アニメ（1文字ずつ・画像が開く・ふわっと上がる）
   - スクロールに連動する動き（読み進み・流れる文字・視差・世界観の場面）
   - API から描く：TOP売出項目 / NEWS / DISCOGRAPHY / MOVIE / LIVE
   - カーソルを追う輪・ライブ画像のプレビュー・傾くジャケット
   水面の描画は water.js
   ============================================================ */

document.addEventListener("DOMContentLoaded", () => {
  const body = document.body;
  const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  const finePointer = window.matchMedia("(hover: hover) and (pointer: fine)").matches;
  const clamp = (v, a, b) => Math.min(b, Math.max(a, v));

  const escHtml = (s) => String(s ?? "").replace(/[&<>"']/g, (c) =>
    ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
  const nl2brHtml = (s) => escHtml(s).replace(/\n/g, "<br>");

  /* ---------- 1文字ずつに分ける ---------- */
  const splitChars = (el) => {
    const text = el.textContent;
    el.setAttribute("aria-label", text);
    el.innerHTML = Array.from(text).map((ch, i) =>
      `<span class="char" aria-hidden="true" style="--i:${i}">${ch === " " ? "&nbsp;" : escHtml(ch)}</span>`).join("");
  };
  document.querySelectorAll("[data-split], [data-chars]").forEach(splitChars);

  /* ---------- 水面 ---------- */
  const water = window.MelikretWater || {};
  const heroFrame = document.getElementById("heroFrame");
  const heroImg = document.getElementById("heroImg");
  const heroWater = !reduceMotion && water.initHero
    ? water.initHero({ frame: heroFrame, img: heroImg, canvas: document.getElementById("heroCanvas") })
    : null;
  const ambient = !reduceMotion && water.initAmbient ? water.initAmbient(document.getElementById("ambient")) : null;

  /* ---------- 出現アニメ（入場が終わってから見張りはじめる） ---------- */
  let io = null;
  let revealStarted = false;
  const pending = [];
  const REVEAL_SEL = ".rv, .rv-clip, .split, .sec-head";

  const watch = (el) => {
    if (!revealStarted) { pending.push(el); return; }
    if (io) io.observe(el);
    else el.classList.add("is-in");
  };
  const startReveal = () => {
    if (revealStarted) return;
    revealStarted = true;
    if ("IntersectionObserver" in window && !reduceMotion) {
      io = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return;
          entry.target.classList.add("is-in");
          io.unobserve(entry.target);
        });
      }, { threshold: 0.12, rootMargin: "0px 0px -7% 0px" });
    }
    document.querySelectorAll(REVEAL_SEL).forEach(watch);
    pending.splice(0).forEach(watch);
  };

  /* ---------- ローディング → 入場 ---------- */
  const loader = document.getElementById("loader");
  const enter = () => {
    body.classList.remove("is-loading");
    body.classList.add("is-ready");
    startReveal();
    if (heroWater) window.setTimeout(() => heroWater.splash(0.5, 0.45, 1.5), 350);
  };
  if (!loader || reduceMotion) {
    if (loader) loader.classList.add("is-done");
    enter();
  } else {
    if (!(window.CSS && CSS.registerProperty)) loader.classList.add("no-prop");
    const minTime = new Promise((r) => window.setTimeout(r, 2050));
    const imgReady = new Promise((r) => {
      if (!heroImg || (heroImg.complete && heroImg.naturalWidth)) return r();
      heroImg.addEventListener("load", r, { once: true });
      heroImg.addEventListener("error", r, { once: true });
      window.setTimeout(r, 4000); // 回線が遅くても待たせすぎない
    });
    Promise.all([minTime, imgReady]).then(() => {
      loader.classList.add("is-open");
      enter();
      window.setTimeout(() => loader.classList.add("is-done"), 1350);
    });
  }

  /* ---------- ヘッダー / 先頭へ戻る ---------- */
  const header = document.getElementById("header");
  const toTop = document.getElementById("toTop");
  const onScroll = () => {
    const y = window.scrollY;
    header.classList.toggle("is-scrolled", y > 60);
    if (toTop) toTop.classList.toggle("is-visible", y > window.innerHeight * 0.6);
  };
  window.addEventListener("scroll", onScroll, { passive: true });
  onScroll();
  if (toTop) toTop.addEventListener("click", () => window.scrollTo({ top: 0, behavior: "smooth" }));

  /* ---------- ハンバーガーメニュー ---------- */
  const hamburger = document.getElementById("hamburger");
  const gnav = document.getElementById("gnav");
  gnav.querySelectorAll(".gnav__list li").forEach((li, i) => li.style.setProperty("--n", i));

  const closeMenu = () => {
    hamburger.classList.remove("is-open");
    gnav.classList.remove("is-open");
    header.classList.remove("nav-open");
    hamburger.setAttribute("aria-expanded", "false");
    hamburger.setAttribute("aria-label", "メニューを開く");
  };
  hamburger.addEventListener("click", () => {
    const opened = hamburger.classList.toggle("is-open");
    gnav.classList.toggle("is-open", opened);
    // header の backdrop-filter が #gnav(position:fixed) の containing block になるのを防ぐ
    header.classList.toggle("nav-open", opened);
    hamburger.setAttribute("aria-expanded", String(opened));
    hamburger.setAttribute("aria-label", opened ? "メニューを閉じる" : "メニューを開く");
  });
  gnav.querySelectorAll("a").forEach((a) => a.addEventListener("click", closeMenu));
  document.addEventListener("keydown", (e) => { if (e.key === "Escape") closeMenu(); });

  /* ---------- 波の境目を差し込む ---------- */
  const WAVE_A = "M0,50 C240,8 480,92 720,50 C960,8 1200,92 1440,50 L1440,91 L0,91 Z";
  const WAVE_B = "M0,50 C240,92 480,8 720,50 C960,92 1200,8 1440,50 L1440,91 L0,91 Z";
  const waveSvg = (cls, fill, dur) => `
    <svg class="wave ${cls}" viewBox="0 0 1440 90" preserveAspectRatio="none" aria-hidden="true">
      <path fill="${fill}" d="${WAVE_A}">${reduceMotion ? "" : `
        <animate attributeName="d" dur="${dur}s" repeatCount="indefinite" calcMode="spline"
                 keySplines="0.45 0 0.55 1;0.45 0 0.55 1" values="${WAVE_A};${WAVE_B};${WAVE_A}" />`}
      </path>
    </svg>`;
  document.querySelectorAll("[data-wave]").forEach((sec, i) => {
    const fill = getComputedStyle(sec).backgroundColor;
    sec.insertAdjacentHTML("afterbegin", waveSvg("wave--top", fill, 8 + (i % 3) * 2));
    if (!sec.classList.contains("footer")) {
      sec.insertAdjacentHTML("beforeend", waveSvg("wave--bottom", fill, 9 + (i % 3) * 2));
    }
  });

  /* ---------- スクロールに連動する動き ---------- */
  const progressBar = document.getElementById("progressBar");
  const scene = document.getElementById("scene");
  const parallaxEls = Array.from(document.querySelectorAll("[data-parallax]"))
    .map((el) => ({ el, box: el.parentElement, k: parseFloat(el.dataset.parallax) || 0.1 }));
  const marquees = Array.from(document.querySelectorAll("[data-marquee]")).map((el) => ({
    el, track: el.firstElementChild, dir: parseFloat(el.dataset.marquee) || -1, x: 0, w: 0,
  }));
  const measure = () => {
    marquees.forEach((m) => { m.w = m.track.scrollWidth / 2; if (m.dir > 0) m.x = -m.w; });
  };
  measure();
  window.addEventListener("resize", measure);
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(measure);

  if (reduceMotion) {
    if (scene) scene.style.setProperty("--p", "1");
  } else {
    let lastY = window.scrollY;
    let vel = 0;
    let sceneP = 0;
    const frame = () => {
      const y = window.scrollY;
      const vh = window.innerHeight;
      vel += ((y - lastY) - vel) * 0.18;
      lastY = y;

      // 読み進み
      const max = document.documentElement.scrollHeight - vh;
      if (progressBar) progressBar.style.transform = `scaleX(${max > 0 ? clamp(y / max, 0, 1) : 0})`;

      // ヒーロー：下へ進むと水面に少し寄る
      if (heroWater && y < vh * 1.2) heroWater.setZoom(1 + clamp(y / vh, 0, 1) * 0.1);

      // 流れる文字（スクロールの速さで加速する）
      marquees.forEach((m) => {
        if (!m.w) return;
        const speed = 0.55 + Math.min(Math.abs(vel) * 0.32, 14);
        m.x += m.dir * speed;
        if (m.x <= -m.w) m.x += m.w;
        if (m.x > 0) m.x -= m.w;
        m.track.style.transform = `translate3d(${m.x.toFixed(2)}px,0,0)`;
      });

      // 視差（枠の中で画像だけゆっくり動く）
      parallaxEls.forEach((p) => {
        const r = p.box.getBoundingClientRect();
        if (r.bottom < -100 || r.top > vh + 100) return;
        const off = (r.top + r.height / 2 - vh / 2) * p.k;
        p.el.style.translate = `0 ${off.toFixed(1)}px`;
      });

      // 世界観の場面：0→1 をなめらかに追いかける
      if (scene) {
        const r = scene.getBoundingClientRect();
        const span = r.height - vh;
        const target = span > 0 ? clamp(-r.top / span, 0, 1) : 1;
        if (Math.abs(target - sceneP) > 0.0004) {
          sceneP += (target - sceneP) * 0.14;
          scene.style.setProperty("--p", sceneP.toFixed(4));
        }
      }
      requestAnimationFrame(frame);
    };
    requestAnimationFrame(frame);
  }

  /* ---------- カーソルを追う輪（マウスのPCのみ） ---------- */
  const cursor = document.getElementById("cursor");
  const cursorLabel = document.getElementById("cursorLabel");
  if (cursor && finePointer && !reduceMotion) {
    let cx = -100, cy = -100, tx = -100, ty = -100;
    // いま指しているものに合わせて輪の形を変える（スクロールで下の要素が替わったときも見直す）
    const apply = (target) => {
      const el = target && target.closest ? target : null;
      const labelEl = el && el.closest("[data-cursor]");
      const linkEl = el && el.closest("a, button");
      cursor.classList.toggle("is-label", !!labelEl);
      cursor.classList.toggle("is-link", !labelEl && !!linkEl);
      if (labelEl) cursorLabel.textContent = labelEl.getAttribute("data-cursor");
    };
    window.addEventListener("pointermove", (e) => {
      if (e.pointerType !== "mouse") return;
      tx = e.clientX; ty = e.clientY;
      cursor.classList.add("is-on");
      apply(e.target);
    }, { passive: true });
    let scrollCheck = 0;
    window.addEventListener("scroll", () => {
      if (scrollCheck) return;
      scrollCheck = window.setTimeout(() => {
        scrollCheck = 0;
        if (tx >= 0) apply(document.elementFromPoint(tx, ty));
      }, 120);
    }, { passive: true });
    document.addEventListener("mouseleave", () => cursor.classList.remove("is-on"));
    const follow = () => {
      cx += (tx - cx) * 0.2; cy += (ty - cy) * 0.2;
      cursor.style.transform = `translate3d(${cx.toFixed(1)}px,${cy.toFixed(1)}px,0)`;
      requestAnimationFrame(follow);
    };
    requestAnimationFrame(follow);
  }

  /* ---------- カーソルに合わせて傾く（ポスター・ジャケット） ---------- */
  const bindTilt = (root, itemSel, imgSel, max) => {
    if (!finePointer || reduceMotion || !root) return;
    root.addEventListener("pointermove", (e) => {
      const item = e.target.closest(itemSel);
      if (!item) return;
      const img = item.querySelector(imgSel);
      if (!img) return;
      const r = img.getBoundingClientRect();
      const px = (e.clientX - r.left) / r.width - 0.5;
      const py = (e.clientY - r.top) / r.height - 0.5;
      img.style.setProperty("--ry", `${(px * max).toFixed(2)}deg`);
      img.style.setProperty("--rx", `${(-py * max).toFixed(2)}deg`);
    }, { passive: true });
    root.addEventListener("pointerout", (e) => {
      const item = e.target.closest(itemSel);
      if (!item || (e.relatedTarget && item.contains(e.relatedTarget))) return;
      const img = item.querySelector(imgSel);
      if (img) { img.style.setProperty("--rx", "0deg"); img.style.setProperty("--ry", "0deg"); }
    }, { passive: true });
  };

  /* ---------- 詳細ポップアップ（LIVE / NEWS 共通の枠） ---------- */
  const ensureDetailModal = (id, label) => {
    let modal = document.getElementById(id);
    if (!modal) {
      modal = document.createElement("div");
      modal.id = id;
      modal.className = "live-modal";
      modal.setAttribute("aria-hidden", "true");
      modal.innerHTML = `
        <div class="live-modal__overlay" data-close></div>
        <div class="live-modal__panel" role="dialog" aria-modal="true" aria-label="${label}">
          <button class="live-modal__close" data-close aria-label="閉じる">×</button>
          <div class="live-modal__body" id="${id}Body"></div>
        </div>`;
      document.body.appendChild(modal);
      const close = () => {
        modal.classList.remove("is-open");
        modal.setAttribute("aria-hidden", "true");
        document.body.style.overflow = "";
      };
      modal.addEventListener("click", (e) => { if (e.target.hasAttribute("data-close")) close(); });
      document.addEventListener("keydown", (e) => {
        if (e.key === "Escape" && modal.classList.contains("is-open")) close();
      });
    }
    return modal;
  };
  const showModal = (modal, html) => {
    const bodyEl = modal.querySelector(".live-modal__body");
    bodyEl.innerHTML = html;
    bodyEl.scrollTop = 0;
    modal.classList.add("is-open");
    modal.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";
  };

  /* LIVE の詳細ポップアップを、ライブ一覧の外（TOP売出項目のバナー）からも開けるようにする。
     ライブ一覧の読み込みが終わると { byId, openModal } で解決する（失敗時は null） */
  let resolveLiveDetail;
  const liveDetailReady = new Promise((r) => { resolveLiveDetail = r; });

  // ── TOP売出項目（複数バナー：PCは横並び／入りきらない分は自動スライド） ──
  (async () => {
    const holder = document.getElementById("featureBanner");
    if (!holder) return;
    const wrap = document.getElementById("pickup") || holder;
    let items;
    try {
      const res = await fetch("api/feature.php");
      if (!res.ok) throw new Error(res.status);
      items = await res.json();
    } catch {
      wrap.remove();
      return;
    }
    if (!Array.isArray(items) || !items.length) { wrap.remove(); return; }

    // 画像だけのバナー。live_id があればそのライブの詳細ポップアップ、無ければリンクURLを別タブで開く
    const slideHtml = (f) => {
      const alt = escHtml(String(f.title || "").replace(/\r?\n/g, " "));
      const attrs = f.live_id
        ? `href="#live" data-live="${escHtml(f.live_id)}" aria-haspopup="dialog"`
        : `href="${escHtml(f.link_url)}" target="_blank" rel="noopener"`;
      return `
        <li class="feature-slider__slide">
          <a class="tour-banner" ${attrs} aria-label="${alt}" data-cursor="View">
            <img class="tour-banner__img" src="${escHtml(f.image_url)}" alt="${alt}" loading="eager" />
          </a>
        </li>`;
    };

    const chevron = (d) => `<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="${d}" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>`;
    holder.className = "feature-slider rv";
    holder.setAttribute("aria-roledescription", "carousel");
    holder.setAttribute("aria-label", "注目のお知らせ");
    holder.innerHTML = `
      <div class="feature-slider__stage">
        <div class="feature-slider__viewport">
          <ul class="feature-slider__track"></ul>
        </div>
        <button type="button" class="feature-slider__arrow feature-slider__arrow--prev" aria-label="前のバナー">${chevron("M15 5l-7 7 7 7")}</button>
        <button type="button" class="feature-slider__arrow feature-slider__arrow--next" aria-label="次のバナー">${chevron("M9 5l7 7-7 7")}</button>
      </div>
      <div class="feature-slider__dots" role="tablist" aria-label="バナーの切り替え"></div>`;
    watch(holder);

    const viewport = holder.querySelector(".feature-slider__viewport");
    const track = holder.querySelector(".feature-slider__track");
    const dotsBox = holder.querySelector(".feature-slider__dots");
    const total = items.length;
    const INTERVAL = 3500;  // 1枚を表示しておく時間（ミリ秒）

    // 1画面に並べる枚数（画面幅で決める）
    const perViewFor = () => {
      const w = window.innerWidth;
      const pv = w >= 1100 ? 3 : w >= 700 ? 2 : 1;
      return Math.min(pv, total);
    };

    let perView = 0;
    let index = 0;          // 先頭に表示中のスライド番号（クローン込みの位置）
    let timer = null;
    let sliding = false;    // 枚数がはみ出していて、スライド動作をするか

    const slideEls = () => Array.from(track.children);

    const moveTo = (i, animate = true) => {
      index = i;
      const els = slideEls();
      const target = els[index];
      const offset = target && sliding ? target.offsetLeft - els[0].offsetLeft : 0;
      track.style.transition = animate ? "" : "none";
      track.style.transform = `translateX(${-offset}px)`;
      if (!animate) { void track.offsetWidth; track.style.transition = ""; }
      const cur = index % total;
      dotsBox.querySelectorAll("button").forEach((b, k) => {
        b.classList.toggle("is-active", k === cur);
        b.setAttribute("aria-selected", String(k === cur));
      });
    };

    // 末尾のクローン位置にいたら、見た目を変えずに本物の先頭側へ戻す（無限ループ用）
    // transitionend が来ない環境（裏に回った・省電力など）でも番号がずれないよう、進む前にも必ず呼ぶ
    const normalize = () => { if (index >= total) moveTo(index % total, false); };
    const next = () => { normalize(); moveTo(index + 1); };
    const prev = () => {
      normalize();
      if (index > 0) { moveTo(index - 1); return; }
      moveTo(total, false);   // 先頭のクローン（見た目は先頭と同じ）へ瞬間移動してから
      moveTo(total - 1);      // 最後の1枚へスライド
    };

    const stop = () => { if (timer) { clearInterval(timer); timer = null; } };
    const start = () => {
      stop();
      if (!sliding || reduceMotion) return;
      timer = setInterval(next, INTERVAL);
    };

    track.addEventListener("transitionend", (e) => {
      if (e.target === track && sliding) normalize();
    });

    // ライブの詳細を開くバナー。一覧の読み込み前に押されても、読み込みを待ってから開く
    track.addEventListener("click", async (e) => {
      const a = e.target.closest("a[data-live]");
      if (!a) return;
      e.preventDefault();
      stop();
      const live = await liveDetailReady;
      const item = live && live.byId[a.getAttribute("data-live")];
      if (item) {
        live.openModal(item);
        // ポップアップを閉じたら自動スライドを再開する
        const modal = document.getElementById("liveModal");
        if (modal) {
          const mo = new MutationObserver(() => {
            if (!modal.classList.contains("is-open")) { mo.disconnect(); start(); }
          });
          mo.observe(modal, { attributes: true, attributeFilter: ["class"] });
        }
      } else {
        // ライブが消されていた・読み込めなかったときは LIVE 欄へ移動する
        const sec = document.getElementById("live");
        if (sec) sec.scrollIntoView({ behavior: "smooth", block: "start" });
      }
    });

    const build = () => {
      const pv = perViewFor();
      if (pv === perView && track.children.length) {
        moveTo(index, false); // 幅だけ変わったときは位置の再計算のみ
        return;
      }
      perView = pv;
      sliding = total > perView;
      holder.style.setProperty("--pv", perView);
      holder.classList.toggle("is-static", !sliding);

      // 入りきらないときだけ、先頭 perView 枚のクローンを末尾に足してループさせる
      const list = sliding ? items.concat(items.slice(0, perView)) : items;
      track.innerHTML = list.map(slideHtml).join("");
      slideEls().forEach((el, k) => {
        if (k >= total) {
          el.setAttribute("aria-hidden", "true");
          el.querySelector("a").setAttribute("tabindex", "-1");
        }
      });

      dotsBox.innerHTML = sliding
        ? items.map((_, k) => `<button type="button" role="tab" aria-label="${k + 1}枚目を表示"></button>`).join("")
        : "";
      dotsBox.querySelectorAll("button").forEach((b, k) => {
        b.addEventListener("click", () => { normalize(); moveTo(k); resume(); });
      });

      moveTo(0, false);
      start();
    };

    // スワイプ（スマホ）
    let startX = null, startY = null;
    viewport.addEventListener("touchstart", (e) => {
      if (!sliding) return;
      startX = e.touches[0].clientX;
      startY = e.touches[0].clientY;
      stop();
    }, { passive: true });
    viewport.addEventListener("touchend", (e) => {
      if (!sliding || startX === null) return;
      const dx = e.changedTouches[0].clientX - startX;
      const dy = e.changedTouches[0].clientY - startY;
      startX = null;
      if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy)) {
        if (dx < 0) next(); else prev();
      }
      start();
    }, { passive: true });

    // マウスを載せている間・キーボード操作中・タブが裏にある間は止める
    // （スマホのタップで mouseenter が出て止まったままにならないよう、マウスのときだけ見る）
    let hovering = false;
    const keyboardFocus = () => {
      const el = document.activeElement;
      return !!(el && holder.contains(el) && el.matches(":focus-visible"));
    };
    // 手で動かしたあと：マウスが載っておらず、キーボード操作中でもなければ、間隔を数え直して自動送りを続ける
    const resume = () => { if (!hovering && !keyboardFocus()) start(); };
    holder.addEventListener("pointerenter", (e) => { if (e.pointerType === "mouse") { hovering = true; stop(); } });
    holder.addEventListener("pointerleave", (e) => { if (e.pointerType === "mouse") { hovering = false; start(); } });
    holder.addEventListener("focusin", stop);
    holder.addEventListener("focusout", () => { if (!hovering) start(); });
    document.addEventListener("visibilitychange", () => (document.hidden ? stop() : resume()));

    // 左右の矢印ボタン
    holder.querySelector(".feature-slider__arrow--prev").addEventListener("click", () => { if (sliding) { prev(); resume(); } });
    holder.querySelector(".feature-slider__arrow--next").addEventListener("click", () => { if (sliding) { next(); resume(); } });

    bindTilt(track, ".tour-banner", ".tour-banner__img", 9);

    let resizeTimer = null;
    window.addEventListener("resize", () => {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(build, 150);
    });

    build();
  })();

  // ── ニュース（詳細ポップアップ対応） ──────────────────────
  (async () => {
    const list = document.getElementById("newsList");
    if (!list) return;
    try {
      const res = await fetch("api/news.php");
      if (!res.ok) throw new Error(res.status);
      const items = await res.json();
      if (!items.length) {
        list.innerHTML = '<li class="news__loading">ニュースはありません。</li>';
        return;
      }

      const byId = {};
      items.forEach((it) => { byId[it.id] = it; });

      list.innerHTML = items.map((n, i) => `
        <li class="news__item rv" style="--d:${(i % 8) * 70}ms">
          <time class="news__date" datetime="${escHtml(n.date)}">${escHtml(n.date_display)}</time>
          <span class="news__cat">${escHtml(n.category)}</span>
          ${n.has_detail
            ? `<button type="button" class="news__text news__text-btn" data-news-detail="${n.id}">${escHtml(n.text)}</button>`
            : `<p class="news__text">${escHtml(n.text)}</p>`}
        </li>
      `).join("");
      list.querySelectorAll(".news__item").forEach(watch);

      if (items.some((n) => n.has_detail)) {
        const modal = ensureDetailModal("newsModal", "ニュース詳細");
        list.addEventListener("click", (e) => {
          const btn = e.target.closest("[data-news-detail]");
          if (!btn) return;
          const n = byId[btn.getAttribute("data-news-detail")];
          if (!n) return;
          const sec = [];
          if (n.image_url) sec.push(`<div class="lm__image"><img src="${escHtml(n.image_url)}" alt="" loading="lazy"></div>`);
          sec.push(`<h3 class="lm__title">${escHtml(n.title || n.text)}</h3>`);
          sec.push(`<p class="lm__lead" style="margin-bottom:0">${escHtml(n.date_display)}　${escHtml(n.category)}</p>`);
          if (n.description) sec.push(`<p class="lm__lead">${nl2brHtml(n.description)}</p>`);
          if (n.link_url) sec.push(`<div class="lm__block"><a class="disco__link" href="${escHtml(n.link_url)}" target="_blank" rel="noopener">${escHtml(n.link_label)}</a></div>`);
          showModal(modal, sec.join(""));
        });
      }
    } catch {
      list.innerHTML = '<li class="news__loading">読込に失敗しました。</li>';
    }
  })();

  // ── ディスコグラフィー（6件ごとのページ送り） ────────────
  (async () => {
    const list = document.getElementById("discoList");
    const pagination = document.getElementById("discoPagination");
    if (!list) return;
    try {
      const res = await fetch("api/discography.php");
      if (!res.ok) throw new Error(res.status);
      const items = await res.json();
      if (!items.length) {
        list.innerHTML = '<li class="news__loading">作品はありません。</li>';
        return;
      }

      list.innerHTML = items.map((d) => `
        <li class="disco__item rv">
          <a class="disco__jacket" href="${escHtml(d.link_url)}" target="_blank" rel="noopener" data-cursor="Listen">
            <img src="${escHtml(d.jacket_url)}" alt="${escHtml(d.title)} ジャケット" loading="lazy" />
          </a>
          <div class="disco__info">
            <p class="disco__date">${escHtml(d.date_display)}</p>
            <h3 class="disco__title">${escHtml(d.title)}</h3>
            <p class="disco__type">${escHtml(d.type)}</p>
            <a class="disco__link" href="${escHtml(d.link_url)}" target="_blank" rel="noopener">LISTEN &rarr;</a>
          </div>
        </li>
      `).join("");

      const discoItems = Array.from(list.querySelectorAll(".disco__item"));
      discoItems.forEach(watch);
      bindTilt(list, ".disco__jacket", "img", 12);

      if (pagination && discoItems.length > 6) {
        const PER_PAGE = 6;
        let page = 1;
        const total = Math.ceil(discoItems.length / PER_PAGE);
        const renderPager = (jump) => {
          const start = (page - 1) * PER_PAGE;
          discoItems.forEach((el, i) => {
            const show = i >= start && i < start + PER_PAGE;
            el.style.display = show ? "" : "none";
            if (show && jump) el.classList.add("is-in"); // ページを替えたときはすぐ見せる
          });
          pagination.innerHTML = `
            <button class="pager__btn" id="discoPagerPrev" ${page === 1 ? "disabled" : ""}>← 前の${PER_PAGE}件</button>
            <span class="pager__info">${page} / ${total}</span>
            <button class="pager__btn" id="discoPagerNext" ${page === total ? "disabled" : ""}>次の${PER_PAGE}件 →</button>
          `;
          const go = (d) => {
            page += d; renderPager(true);
            document.getElementById("discography").scrollIntoView({ behavior: "smooth", block: "start" });
          };
          document.getElementById("discoPagerPrev").addEventListener("click", () => { if (page > 1) go(-1); });
          document.getElementById("discoPagerNext").addEventListener("click", () => { if (page < total) go(1); });
        };
        renderPager(false);
      }
    } catch {
      list.innerHTML = '<li class="news__loading">読込に失敗しました。</li>';
    }
  })();

  // ── MOVIE（大きな1本＋サムネイルの列。押すまで YouTube を読み込まない） ──
  (async () => {
    const box = document.getElementById("movieList");
    if (!box) return;
    try {
      const res = await fetch("api/movie.php");
      if (!res.ok) throw new Error(res.status);
      const items = await res.json();
      if (!items.length) return;

      const thumb = (id, size) => `https://i.ytimg.com/vi/${encodeURIComponent(id)}/${size}.jpg`;
      box.innerHTML = `
        <div class="movie__stage rv-clip" id="movieStage"></div>
        <p class="movie__now rv" id="movieNow"></p>
        ${items.length > 1 ? `
        <div class="movie__rail rv" id="movieRail" role="list" aria-label="ほかの映像">
          ${items.map((m, i) => `
            <button type="button" class="movie__thumb${i === 0 ? " is-active" : ""}" data-index="${i}" role="listitem">
              <span class="movie__thumb-img"><img src="${thumb(m.youtube_id, "mqdefault")}" alt="" loading="lazy" draggable="false" /></span>
              <span class="movie__thumb-title">${escHtml(m.title)}</span>
            </button>`).join("")}
        </div>` : ""}`;

      const stage = document.getElementById("movieStage");
      const nowEl = document.getElementById("movieNow");
      const rail = document.getElementById("movieRail");

      const setPoster = (i) => {
        const m = items[i];
        stage.innerHTML = `
          <button type="button" class="movie__poster" aria-label="${escHtml(m.title)} を再生" data-cursor="Play">
            <img src="${thumb(m.youtube_id, "maxresdefault")}" alt="" />
            <span class="movie__play" aria-hidden="true"></span>
          </button>`;
        const img = stage.querySelector("img");
        // 高画質のサムネイルが無い動画は、標準のものに替える
        const fallback = () => { img.src = thumb(m.youtube_id, "hqdefault"); };
        img.addEventListener("error", fallback, { once: true });
        img.addEventListener("load", () => { if (img.naturalWidth && img.naturalWidth <= 120) fallback(); }, { once: true });
        stage.querySelector(".movie__poster").addEventListener("click", () => play(i));
        nowEl.textContent = m.title;
        if (rail) rail.querySelectorAll(".movie__thumb").forEach((b, k) => b.classList.toggle("is-active", k === i));
      };
      const play = (i) => {
        const m = items[i];
        stage.innerHTML = `
          <iframe src="https://www.youtube-nocookie.com/embed/${encodeURIComponent(m.youtube_id)}?autoplay=1&rel=0"
            title="${escHtml(m.title)}"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
            allowfullscreen></iframe>`;
        nowEl.textContent = m.title;
        if (rail) rail.querySelectorAll(".movie__thumb").forEach((b, k) => b.classList.toggle("is-active", k === i));
      };
      setPoster(0);
      [stage, nowEl, rail].forEach((el) => el && watch(el));

      if (rail) {
        // マウスでつかんで横に動かせる（動かしたときはクリック扱いにしない）
        let down = false, moved = 0, sx = 0, sl = 0;
        rail.addEventListener("pointerdown", (e) => {
          if (e.pointerType !== "mouse") return;
          down = true; moved = 0; sx = e.clientX; sl = rail.scrollLeft;
        });
        window.addEventListener("pointermove", (e) => {
          if (!down) return;
          const dx = e.clientX - sx;
          moved = Math.max(moved, Math.abs(dx));
          if (moved > 5) { rail.classList.add("is-drag"); rail.scrollLeft = sl - dx; }
        });
        window.addEventListener("pointerup", () => {
          if (!down) return;
          down = false;
          window.setTimeout(() => rail.classList.remove("is-drag"), 0);
        });
        rail.addEventListener("click", (e) => {
          const b = e.target.closest(".movie__thumb");
          if (!b) return;
          if (moved > 5) { moved = 0; return; }
          play(Number(b.dataset.index));
          stage.scrollIntoView({ behavior: "smooth", block: "center" });
        });
      }
    } catch {
      /* MOVIEは読込失敗時、見出しとYouTubeリンクのみ表示のまま */
    }
  })();

  // ── ライブ（取得後にページ送り・詳細ポップアップ・画像プレビュー） ──
  (async () => {
    const list = document.getElementById("liveList");
    const pagination = document.getElementById("livePagination");
    if (!list) return;
    try {
      const res = await fetch("api/live.php");
      if (!res.ok) throw new Error(res.status);
      const items = await res.json();
      if (!items.length) {
        list.innerHTML = '<li class="news__loading">ライブ情報はありません。</li>';
        resolveLiveDetail(null);
        return;
      }

      const esc = escHtml;
      const nl2br = nl2brHtml;
      const byId = {};
      items.forEach((it) => { byId[it.id] = it; });

      list.innerHTML = items.map((item, i) => {
        const title = item.has_detail
          ? `<button type="button" class="live__name-btn" data-detail="${item.id}">${esc(item.event_name)}</button>`
          : esc(item.event_name);
        return `
        <li class="live__item rv" style="--d:${(i % 10) * 70}ms"${item.image_url ? ` data-img="${esc(item.image_url)}"` : ""}>
          <div class="live__date">
            <span class="live__date-day">${esc(item.day_month)}</span>
            <span class="live__date-year">${esc(item.year_dow)}</span>
          </div>
          <div class="live__body">
            <h3 class="live__name">${title}</h3>
            <p class="live__place">${esc(item.venue)}${item.city ? ` &mdash; ${esc(item.city)}` : ""}</p>
          </div>
          ${item.has_detail ? `<div class="live__actions"><button type="button" class="live__detail-btn" data-detail="${item.id}" aria-label="${esc(item.event_name)} の詳細">詳細</button></div>` : ""}
        </li>`;
      }).join("");

      const liveItems = Array.from(list.querySelectorAll(".live__item"));
      liveItems.forEach(watch);

      /* ---- 詳細ポップアップ ---- */
      const modal = ensureDetailModal("liveModal", "ライブ詳細");
      const openModal = (item) => {
        const sec = [];
        if (item.image_url) {
          sec.push(`<div class="lm__image"><img src="${esc(item.image_url)}" alt="" loading="lazy"></div>`);
        }
        sec.push(`<h3 class="lm__title">${esc(item.event_name)}</h3>`);
        if (item.description) sec.push(`<p class="lm__lead">${nl2br(item.description)}</p>`);

        // 日時・会場
        const place = `${esc(item.venue)}${item.city ? ` ／ ${esc(item.city)}` : ""}`;
        let dateLine = esc(item.date_jp);
        const times = [];
        if (item.open_time)  times.push(`OPEN ${esc(item.open_time)}`);
        if (item.start_time) times.push(`START ${esc(item.start_time)}`);
        if (times.length) dateLine += `　${times.join(" / ")}`;
        sec.push(
          `<div class="lm__block"><h4 class="lm__head">日時・会場</h4>` +
          `<p class="lm__text">${place}<br>${dateLine}</p></div>`
        );

        // チケット（複数・表形式）
        if (Array.isArray(item.tickets) && item.tickets.length) {
          let t = `<div class="lm__block"><h4 class="lm__head">チケット</h4><div class="lm__tickets">`;
          item.tickets.forEach((tk) => {
            t += `<div class="lm__ticket-row">`;
            t += `<span class="lm__ticket-info">${tk.info ? nl2br(tk.info) : ""}</span>`;
            if (tk.url) t += `<a class="lm__ticket-link" href="${esc(tk.url)}" target="_blank" rel="noopener">購入 →</a>`;
            t += `</div>`;
          });
          t += `</div></div>`;
          sec.push(t);
        }

        // 備考
        if (item.notes) {
          sec.push(`<div class="lm__block"><h4 class="lm__head">備考</h4><p class="lm__text">${nl2br(item.notes)}</p></div>`);
        }
        showModal(modal, sec.join(""));
      };

      list.addEventListener("click", (e) => {
        const btn = e.target.closest("[data-detail]");
        if (!btn) return;
        const item = byId[btn.getAttribute("data-detail")];
        if (item) openModal(item);
      });

      // TOP売出項目のバナーからも同じポップアップを開けるようにする
      resolveLiveDetail({ byId, openModal });

      /* ---- カーソルについてくるライブ画像（マウスのPCのみ） ---- */
      if (finePointer && !reduceMotion && items.some((it) => it.image_url)) {
        const pv = document.createElement("div");
        pv.className = "live-preview";
        pv.setAttribute("aria-hidden", "true");
        pv.innerHTML = '<img alt="" />';
        document.body.appendChild(pv);
        const pvImg = pv.querySelector("img");
        let px = 0, py = 0, tx = 0, ty = 0, on = false, raf = 0;
        const loop = () => {
          px += (tx - px) * 0.16; py += (ty - py) * 0.16;
          pv.style.transform = `translate3d(${px.toFixed(1)}px,${py.toFixed(1)}px,0)`;
          raf = on || Math.abs(tx - px) > 0.5 ? requestAnimationFrame(loop) : 0;
        };
        list.addEventListener("pointermove", (e) => {
          if (e.pointerType !== "mouse") return;
          const li = e.target.closest(".live__item[data-img]");
          tx = e.clientX; ty = e.clientY;
          if (li) {
            const src = li.getAttribute("data-img");
            if (pvImg.getAttribute("src") !== src) pvImg.setAttribute("src", src);
            if (!on) { on = true; px = tx; py = ty; pv.classList.add("is-on"); }
            if (!raf) raf = requestAnimationFrame(loop);
          } else if (on) {
            on = false; pv.classList.remove("is-on");
          }
        }, { passive: true });
        list.addEventListener("pointerleave", () => { on = false; pv.classList.remove("is-on"); });
        window.addEventListener("scroll", () => { if (on) { on = false; pv.classList.remove("is-on"); } }, { passive: true });
      }

      // ページ送り（10件を超えるときのみ）
      if (pagination && liveItems.length > 10) {
        const PER_PAGE = 10;
        let page = 1;
        const total = Math.ceil(liveItems.length / PER_PAGE);
        const renderPager = (jump) => {
          const start = (page - 1) * PER_PAGE;
          liveItems.forEach((el, i) => {
            const show = i >= start && i < start + PER_PAGE;
            el.style.display = show ? "" : "none";
            if (show && jump) el.classList.add("is-in");
          });
          pagination.innerHTML = `
            <button class="pager__btn" id="pagerPrev" ${page === 1 ? "disabled" : ""}>← 前の10件</button>
            <span class="pager__info">${page} / ${total}</span>
            <button class="pager__btn" id="pagerNext" ${page === total ? "disabled" : ""}>次の10件 →</button>
          `;
          const go = (d) => {
            page += d; renderPager(true);
            document.getElementById("live").scrollIntoView({ behavior: "smooth", block: "start" });
          };
          document.getElementById("pagerPrev").addEventListener("click", () => { if (page > 1) go(-1); });
          document.getElementById("pagerNext").addEventListener("click", () => { if (page < total) go(1); });
        };
        renderPager(false);
      }
    } catch {
      list.innerHTML = '<li class="news__loading">読込に失敗しました。</li>';
      resolveLiveDetail(null);
    }
  })();
});
