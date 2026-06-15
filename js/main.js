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
    hamburger.setAttribute("aria-expanded", "false");
  };

  hamburger.addEventListener("click", () => {
    const opened = hamburger.classList.toggle("is-open");
    gnav.classList.toggle("is-open", opened);
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

  if ("IntersectionObserver" in window) {
    const io = new IntersectionObserver(
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
