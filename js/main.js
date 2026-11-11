/* ============================================================
   メリクレット | melikret  OFFICIAL WEBSITE  -  main.js
   - ヘッダーのスクロール変化
   - ハンバーガーメニュー（モバイル）
   - スクロール出現アニメ
   - 水たまりの波紋エフェクト（雨粒が水面に落ちるイメージ／カーソルにも反応）
   ============================================================ */

document.addEventListener("DOMContentLoaded", () => {

  /* ---------- ローディング画面を一定時間後に解除（最初に実行） ---------- */
  const loader = document.getElementById("loader");
  if (loader) {
    window.setTimeout(() => loader.classList.add("is-hidden"), 1500);
  }

  /* ---------- ヘッダー：スクロールで白背景を出す＋トップへ戻るボタンの表示 ---------- */
  const header = document.getElementById("header");
  const toTop = document.getElementById("toTop");
  const onScroll = () => {
    const y = window.scrollY;
    header.classList.toggle("is-scrolled", y > 60);
    if (toTop) toTop.classList.toggle("is-visible", y > window.innerHeight * 0.6);
  };
  window.addEventListener("scroll", onScroll, { passive: true });
  onScroll();

  if (toTop) {
    toTop.addEventListener("click", () => {
      window.scrollTo({ top: 0, behavior: "smooth" });
    });
  }

  /* ---------- ハンバーガーメニュー ---------- */
  const hamburger = document.getElementById("hamburger");
  const gnav = document.getElementById("gnav");

  const closeMenu = () => {
    hamburger.classList.remove("is-open");
    gnav.classList.remove("is-open");
    header.classList.remove("nav-open");
    hamburger.setAttribute("aria-expanded", "false");
  };

  hamburger.addEventListener("click", () => {
    const opened = hamburger.classList.toggle("is-open");
    gnav.classList.toggle("is-open", opened);
    // header.is-scrolled の backdrop-filter が #gnav(position:fixed) の containing block になり
    // フルスクリーン表示できなくなるのを防ぐため、メニュー展開中は header 側のフィルターを外す
    header.classList.toggle("nav-open", opened);
    hamburger.setAttribute("aria-expanded", String(opened));
  });

  gnav.querySelectorAll("a").forEach((a) =>
    a.addEventListener("click", closeMenu)
  );

  /* ---------- スクロール出現アニメ ---------- */
  const revealTargets = document.querySelectorAll(
    ".section__head, .news__item, .live__item, .keyvisual__copy, .profile, .member__item, .disco__item, .movie__frame, .contact"
  );
  revealTargets.forEach((el) => el.classList.add("reveal"));

  let io = null;
  if ("IntersectionObserver" in window) {
    io = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            const delay = entry.target.dataset.delay || 0;
            entry.target.style.transitionDelay = `${delay}ms`;
            entry.target.classList.add("is-visible");
            io.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.12, rootMargin: "0px 0px -8% 0px" }
    );

    // 同じ親内の連番要素にステップ遅延を付与
    const groups = [".news__item", ".live__item", ".member__item", ".disco__item", ".movie__frame"];
    groups.forEach((sel) => {
      document.querySelectorAll(sel).forEach((el, i) => {
        el.dataset.delay = (i % 8) * 90;
      });
    });

    revealTargets.forEach((el) => io.observe(el));
  } else {
    revealTargets.forEach((el) => el.classList.add("is-visible"));
  }

  /* ---------- API: TOP売出項目 / ニュース / ディスコグラフィー / MOVIE / ライブ ---------- */
  const observeEl = (el) => {
    if (io) { io.observe(el); }
    else { el.classList.add("is-visible"); }
  };

  const escHtml = (s) => String(s ?? "").replace(/[&<>"']/g, (c) =>
    ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
  const nl2brHtml = (s) => escHtml(s).replace(/\n/g, "<br>");

  /* 汎用の詳細モーダルを1つ用意し、live/newsで共有する */
  const ensureDetailModal = (id) => {
    let modal = document.getElementById(id);
    if (!modal) {
      modal = document.createElement("div");
      modal.id = id;
      modal.className = "live-modal";
      modal.setAttribute("aria-hidden", "true");
      modal.innerHTML = `
        <div class="live-modal__overlay" data-close></div>
        <div class="live-modal__panel" role="dialog" aria-modal="true" aria-label="詳細">
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
      modal._close = close;
    }
    return modal;
  };

  /* LIVE の詳細ポップアップを、ライブ一覧の外（TOP売出項目のバナー）からも開けるようにする。
     ライブ一覧の読み込みが終わると { byId, openModal } で解決する（失敗時は null） */
  let resolveLiveDetail;
  const liveDetailReady = new Promise((r) => { resolveLiveDetail = r; });

  // ── TOP売出項目（複数バナー：PCは横並び／入りきらない分は自動スライド） ──
  (async () => {
    const holder = document.getElementById("featureBanner");
    if (!holder) return;
    let items;
    try {
      const res = await fetch("api/feature.php");
      if (!res.ok) throw new Error(res.status);
      items = await res.json();
    } catch {
      holder.remove();
      return;
    }
    if (!Array.isArray(items) || !items.length) { holder.remove(); return; }

    // 画像だけのバナー。live_id があればそのライブの詳細ポップアップ、無ければリンクURLを別タブで開く
    const slideHtml = (f) => {
      const alt = escHtml(String(f.title || "").replace(/\r?\n/g, " "));
      const attrs = f.live_id
        ? `href="#live" data-live="${escHtml(f.live_id)}" aria-haspopup="dialog"`
        : `href="${escHtml(f.link_url)}" target="_blank" rel="noopener"`;
      return `
        <li class="feature-slider__slide">
          <a class="tour-banner" ${attrs} aria-label="${alt}">
            <img class="tour-banner__img" src="${escHtml(f.image_url)}" alt="${alt}" loading="eager" />
          </a>
        </li>`;
    };

    holder.className = "feature-slider";
    holder.setAttribute("aria-roledescription", "carousel");
    holder.setAttribute("aria-label", "注目のお知らせ");
    holder.innerHTML = `
      <div class="feature-slider__viewport">
        <ul class="feature-slider__track"></ul>
      </div>
      <div class="feature-slider__dots" role="tablist" aria-label="バナーの切り替え"></div>`;

    const viewport = holder.querySelector(".feature-slider__viewport");
    const track = holder.querySelector(".feature-slider__track");
    const dotsBox = holder.querySelector(".feature-slider__dots");
    const total = items.length;
    const INTERVAL = 4500;
    const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

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
        b.addEventListener("click", () => { normalize(); moveTo(k); start(); });
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
    holder.addEventListener("mouseenter", stop);
    holder.addEventListener("mouseleave", start);
    holder.addEventListener("focusin", stop);
    holder.addEventListener("focusout", start);
    document.addEventListener("visibilitychange", () => (document.hidden ? stop() : start()));

    let resizeTimer = null;
    window.addEventListener("resize", () => {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(build, 150);
    });

    build();
  })();

  // ── ニュース（詳細モーダル対応） ──────────────────────────
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

      list.innerHTML = items.map(n => `
        <li class="news__item reveal">
          <time class="news__date" datetime="${n.date}">${n.date_display}</time>
          <span class="news__cat">${n.category}</span>
          ${n.has_detail
            ? `<button type="button" class="news__text news__text-btn" data-news-detail="${n.id}">${escHtml(n.text)}</button>`
            : `<p class="news__text">${escHtml(n.text)}</p>`}
        </li>
      `).join("");
      list.querySelectorAll(".news__item").forEach((el, i) => {
        el.dataset.delay = (i % 8) * 90;
        observeEl(el);
      });

      if (items.some(n => n.has_detail)) {
        const modal = ensureDetailModal("newsModal");
        const modalBody = modal.querySelector("#newsModalBody");

        const openNewsModal = (n) => {
          const sec = [];
          if (n.image_url) sec.push(`<div class="lm__image"><img src="${escHtml(n.image_url)}" alt="" loading="lazy"></div>`);
          sec.push(`<h3 class="lm__title">${escHtml(n.title || n.text)}</h3>`);
          sec.push(`<p class="lm__lead" style="margin-bottom:0">${escHtml(n.date_display)}　${escHtml(n.category)}</p>`);
          if (n.description) sec.push(`<p class="lm__lead">${nl2brHtml(n.description)}</p>`);
          if (n.link_url) sec.push(`<div class="lm__block"><a class="disco__link" href="${escHtml(n.link_url)}" target="_blank" rel="noopener">${escHtml(n.link_label)}</a></div>`);
          modalBody.innerHTML = sec.join("");
          modalBody.scrollTop = 0;
          modal.classList.add("is-open");
          modal.setAttribute("aria-hidden", "false");
          document.body.style.overflow = "hidden";
        };

        list.addEventListener("click", (e) => {
          const btn = e.target.closest("[data-news-detail]");
          if (!btn) return;
          const item = byId[btn.getAttribute("data-news-detail")];
          if (item) openNewsModal(item);
        });
      }
    } catch {
      list.innerHTML = '<li class="news__loading">読込に失敗しました。</li>';
    }
  })();

  // ── ディスコグラフィー（6件ごとのページネーション） ────────
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

      list.innerHTML = items.map(d => `
        <li class="disco__item reveal">
          <a class="disco__jacket" href="${escHtml(d.link_url)}" target="_blank" rel="noopener">
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
      discoItems.forEach((el, i) => {
        el.dataset.delay = (i % 8) * 90;
        observeEl(el);
      });

      if (pagination && discoItems.length > 6) {
        const PER_PAGE = 6;
        let page = 1;
        const total = Math.ceil(discoItems.length / PER_PAGE);

        const renderPager = () => {
          const start = (page - 1) * PER_PAGE;
          discoItems.forEach((el, i) => {
            el.style.display = i >= start && i < start + PER_PAGE ? "" : "none";
          });
          pagination.innerHTML = `
            <button class="pager__btn" id="discoPagerPrev" ${page === 1 ? "disabled" : ""}>← 前の${PER_PAGE}件</button>
            <span class="pager__info">${page} / ${total} ページ</span>
            <button class="pager__btn" id="discoPagerNext" ${page === total ? "disabled" : ""}>次の${PER_PAGE}件 →</button>
          `;
          document.getElementById("discoPagerPrev").addEventListener("click", () => {
            if (page > 1) { page--; renderPager(); document.getElementById("discography").scrollIntoView({ behavior: "smooth", block: "start" }); }
          });
          document.getElementById("discoPagerNext").addEventListener("click", () => {
            if (page < total) { page++; renderPager(); document.getElementById("discography").scrollIntoView({ behavior: "smooth", block: "start" }); }
          });
        };
        renderPager();
      }
    } catch {
      list.innerHTML = '<li class="news__loading">読込に失敗しました。</li>';
    }
  })();

  // ── MOVIE ──────────────────────────────────────────────
  (async () => {
    const list = document.getElementById("movieList");
    if (!list) return;
    try {
      const res = await fetch("api/movie.php");
      if (!res.ok) throw new Error(res.status);
      const items = await res.json();
      if (!items.length) return;

      list.innerHTML = items.map(m => `
        <div class="movie__frame">
          <iframe
            src="https://www.youtube-nocookie.com/embed/${escHtml(m.youtube_id)}"
            title="${escHtml(m.title)}"
            loading="lazy"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
            allowfullscreen></iframe>
        </div>
      `).join("");
    } catch {
      /* MOVIEは読込失敗時、見出しとYouTubeリンクのみ表示のまま */
    }
  })();

  // ── ライブ（取得後にページネーション起動） ──────────────
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

      const esc = (s) => String(s ?? "").replace(/[&<>"']/g, (c) =>
        ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
      const nl2br = (s) => esc(s).replace(/\n/g, "<br>");

      const byId = {};
      items.forEach((it) => { byId[it.id] = it; });

      list.innerHTML = items.map(item => {
        const title = item.has_detail
          ? `<button type="button" class="live__name-btn" data-detail="${item.id}">${esc(item.event_name)}</button>`
          : esc(item.event_name);
        return `
        <li class="live__item reveal">
          <div class="live__date">
            <span class="live__date-day">${esc(item.day_month)}</span>
            <span class="live__date-year">${esc(item.year_dow)}</span>
          </div>
          <div class="live__body">
            <h3 class="live__name">${title}</h3>
            <p class="live__place">${esc(item.venue)}${item.city ? ` &mdash; ${esc(item.city)}` : ""}</p>
          </div>
          ${item.has_detail ? `<div class="live__actions"><button type="button" class="live__detail-btn" data-detail="${item.id}">詳細 →</button></div>` : ""}
        </li>`;
      }).join("");

      const liveItems = Array.from(list.querySelectorAll(".live__item"));
      liveItems.forEach((el, i) => {
        el.dataset.delay = (i % 8) * 90;
        observeEl(el);
      });

      /* ---- 詳細モーダル ---- */
      let modal = document.getElementById("liveModal");
      if (!modal) {
        modal = document.createElement("div");
        modal.id = "liveModal";
        modal.className = "live-modal";
        modal.setAttribute("aria-hidden", "true");
        modal.innerHTML = `
          <div class="live-modal__overlay" data-close></div>
          <div class="live-modal__panel" role="dialog" aria-modal="true" aria-label="ライブ詳細">
            <button class="live-modal__close" data-close aria-label="閉じる">×</button>
            <div class="live-modal__body" id="liveModalBody"></div>
          </div>`;
        document.body.appendChild(modal);
      }
      const modalBody = modal.querySelector("#liveModalBody");

      const closeModal = () => {
        modal.classList.remove("is-open");
        modal.setAttribute("aria-hidden", "true");
        document.body.style.overflow = "";
      };

      const openModal = (item) => {
        const sec = [];
        if (item.image_url) {
          sec.push(`<div class="lm__image"><img src="${esc(item.image_url)}" alt="" loading="lazy"></div>`);
        }
        sec.push(`<h3 class="lm__title">${esc(item.event_name)}</h3>`);
        if (item.description) {
          sec.push(`<p class="lm__lead">${nl2br(item.description)}</p>`);
        }

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

        modalBody.innerHTML = sec.join("");
        modalBody.scrollTop = 0;
        modal.classList.add("is-open");
        modal.setAttribute("aria-hidden", "false");
        document.body.style.overflow = "hidden";
      };

      list.addEventListener("click", (e) => {
        const btn = e.target.closest("[data-detail]");
        if (!btn) return;
        const item = byId[btn.getAttribute("data-detail")];
        if (item) openModal(item);
      });
      modal.addEventListener("click", (e) => {
        if (e.target.hasAttribute("data-close")) closeModal();
      });
      document.addEventListener("keydown", (e) => {
        if (e.key === "Escape" && modal.classList.contains("is-open")) closeModal();
      });

      // TOP売出項目のバナーからも同じポップアップを開けるようにする
      resolveLiveDetail({ byId, openModal });

      // ページネーション（10件以上のときのみ）
      if (pagination && liveItems.length > 10) {
        const PER_PAGE = 10;
        let page = 1;
        const total = Math.ceil(liveItems.length / PER_PAGE);

        const renderPager = () => {
          const start = (page - 1) * PER_PAGE;
          liveItems.forEach((el, i) => {
            el.style.display = i >= start && i < start + PER_PAGE ? "" : "none";
          });
          pagination.innerHTML = `
            <button class="pager__btn" id="pagerPrev" ${page === 1 ? "disabled" : ""}>← 前の10件</button>
            <span class="pager__info">${page} / ${total} ページ</span>
            <button class="pager__btn" id="pagerNext" ${page === total ? "disabled" : ""}>次の10件 →</button>
          `;
          document.getElementById("pagerPrev").addEventListener("click", () => {
            if (page > 1) { page--; renderPager(); document.getElementById("live").scrollIntoView({ behavior: "smooth", block: "start" }); }
          });
          document.getElementById("pagerNext").addEventListener("click", () => {
            if (page < total) { page++; renderPager(); document.getElementById("live").scrollIntoView({ behavior: "smooth", block: "start" }); }
          });
        };
        renderPager();
      }
    } catch {
      list.innerHTML = '<li class="news__loading">読込に失敗しました。</li>';
      resolveLiveDetail(null);
    }
  })();

  /* ---------- 水たまりの波紋エフェクト ---------- */
  const layer = document.getElementById("ripple");
  const prefersReduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  if (layer && !prefersReduced) {
    const MAX = 22;            // 同時に存在する波紋の上限（負荷対策）
    let active = 0;

    // 1つの波紋を生成（x, y は画面座標 / size は直径px）
    const spawn = (x, y, size) => {
      if (active >= MAX) return;
      active++;
      const r = document.createElement("span");
      r.className = "ripple";
      r.style.left = `${x}px`;
      r.style.top = `${y}px`;
      r.style.width = `${size}px`;
      r.style.height = `${size}px`;
      // 大きいほどゆっくり広がる方が水面らしい
      r.style.animationDuration = `${1.9 + size / 220}s`;
      layer.appendChild(r);
      r.addEventListener("animationend", () => {
        r.remove();
        active--;
      });
    };

    // ① 環境演出：一定間隔でランダムな位置に波紋を落とす（雨粒のイメージ）
    const ambient = () => {
      const x = Math.random() * window.innerWidth;
      const y = Math.random() * window.innerHeight;
      const size = 140 + Math.random() * 220;
      spawn(x, y, size);
      // 次の雨粒までの間隔をゆらがせる
      const next = 700 + Math.random() * 1500;
      window.setTimeout(ambient, next);
    };
    window.setTimeout(ambient, 600);

    // ② インタラクション：カーソル移動に水面が反応（間引き）
    let lastX = 0, lastY = 0, lastT = 0;
    window.addEventListener(
      "pointermove",
      (e) => {
        const now = e.timeStamp;
        const dx = e.clientX - lastX;
        const dy = e.clientY - lastY;
        const moved = Math.hypot(dx, dy);
        if (now - lastT > 150 && moved > 60) {
          lastT = now; lastX = e.clientX; lastY = e.clientY;
          spawn(e.clientX, e.clientY, 90 + Math.random() * 80);
        }
      },
      { passive: true }
    );

    // ③ クリック／タップで大きめの波紋
    window.addEventListener(
      "pointerdown",
      (e) => spawn(e.clientX, e.clientY, 200 + Math.random() * 120),
      { passive: true }
    );
  }
});
