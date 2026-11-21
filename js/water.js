/* ============================================================
   メリクレット | water.js
   - ヒーローのアー写を「水面」として描く（WebGL。雨粒とカーソルで波紋が広がり、写真がゆらぐ）
   - 画面全体にうすい波紋を落とす（2D canvas）
   WebGL が使えない・動きを減らす設定のときは、何もせず元の <img> をそのまま見せる
   ============================================================ */
(function () {
  "use strict";

  const MAX_DROPS = 14;
  const LIFE = 4.2; // 波紋1つの寿命（秒）

  const VERT = `
    attribute vec2 aPos;
    varying vec2 vUv;
    void main() {
      vUv = vec2(aPos.x * 0.5 + 0.5, 0.5 - aPos.y * 0.5); // 左上が (0,0)
      gl_Position = vec4(aPos, 0.0, 1.0);
    }`;

  const FRAG = `
    precision highp float;
    varying vec2 vUv;
    uniform sampler2D uTex;
    uniform vec2 uRes;        // canvas の大きさ(px)
    uniform float uImgAspect; // 画像の横/縦
    uniform vec2 uFocus;      // はみ出す分のどこを見せるか(0..1)
    uniform float uZoom;
    uniform float uTime;
    uniform vec4 uDrops[${MAX_DROPS}]; // x, y(0..1), 落ちた時刻, 強さ

    vec2 coverUv(vec2 uv) {
      float ca = uRes.x / uRes.y;
      vec2 s = vec2(1.0);
      vec2 o = vec2(0.0);
      if (ca > uImgAspect) { s.y = uImgAspect / ca; o.y = (1.0 - s.y) * uFocus.y; }
      else                 { s.x = ca / uImgAspect; o.x = (1.0 - s.x) * uFocus.x; }
      uv = (uv - 0.5) / uZoom + 0.5;
      return uv * s + o;
    }

    void main() {
      float ca = uRes.x / uRes.y;
      vec2 p = vec2(vUv.x * ca, vUv.y);
      vec2 disp = vec2(0.0);
      float light = 0.0;

      for (int i = 0; i < ${MAX_DROPS}; i++) {
        vec4 d = uDrops[i];
        float age = uTime - d.z;
        float alive = step(0.0, age) * step(age, ${LIFE.toFixed(1)}) * step(0.001, d.w);
        vec2 dir = p - vec2(d.x * ca, d.y);
        float dist = length(dir);
        float front = age * 0.30;                 // 波の先頭の半径
        float x = dist - front;
        float env = exp(-x * x * 55.0)            // 先頭のまわりだけ
                  * exp(-age * 0.95)              // 時間で弱まる
                  * smoothstep(0.0, 0.06, age);   // 落ちた瞬間に跳ねない
        float wave = sin(x * 64.0) * env * d.w * alive;
        disp += (dir / max(dist, 0.0001)) * wave;
        light += wave;
      }

      // いつも少しだけゆれている
      disp += 0.10 * vec2(sin(vUv.y * 17.0 + uTime * 0.9), cos(vUv.x * 14.0 + uTime * 0.7));

      vec2 uv = coverUv(vUv + disp * 0.011);
      vec3 col = texture2D(uTex, clamp(uv, 0.0, 1.0)).rgb;
      col += vec3(0.86, 0.95, 1.0) * max(light, 0.0) * 0.22;   // 波の山に光
      col -= vec3(0.06, 0.05, 0.02) * max(-light, 0.0) * 0.9;  // 谷は少し沈む
      gl_FragColor = vec4(col, 1.0);
    }`;

  function compile(gl, type, src) {
    const sh = gl.createShader(type);
    gl.shaderSource(sh, src);
    gl.compileShader(sh);
    if (!gl.getShaderParameter(sh, gl.COMPILE_STATUS)) {
      const log = gl.getShaderInfoLog(sh);
      gl.deleteShader(sh);
      throw new Error(log);
    }
    return sh;
  }

  /* ---------- ヒーローの水面 ---------- */
  function initHero(opts) {
    const frame = opts.frame, img = opts.img, canvas = opts.canvas;
    if (!frame || !img || !canvas) return null;

    let gl;
    try {
      gl = canvas.getContext("webgl", { antialias: false, alpha: false, powerPreference: "low-power" });
    } catch (e) { gl = null; }
    if (!gl) return null;

    let prog;
    try {
      prog = gl.createProgram();
      gl.attachShader(prog, compile(gl, gl.VERTEX_SHADER, VERT));
      gl.attachShader(prog, compile(gl, gl.FRAGMENT_SHADER, FRAG));
      gl.linkProgram(prog);
      if (!gl.getProgramParameter(prog, gl.LINK_STATUS)) throw new Error(gl.getProgramInfoLog(prog));
    } catch (e) {
      return null;
    }
    gl.useProgram(prog);

    const buf = gl.createBuffer();
    gl.bindBuffer(gl.ARRAY_BUFFER, buf);
    gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 1, -1, -1, 1, 1, 1]), gl.STATIC_DRAW);
    const aPos = gl.getAttribLocation(prog, "aPos");
    gl.enableVertexAttribArray(aPos);
    gl.vertexAttribPointer(aPos, 2, gl.FLOAT, false, 0, 0);

    const U = {};
    ["uTex", "uRes", "uImgAspect", "uFocus", "uZoom", "uTime", "uDrops"].forEach((n) => {
      U[n] = gl.getUniformLocation(prog, n);
    });

    const drops = new Float32Array(MAX_DROPS * 4);
    let dropIdx = 0;
    let ready = false;
    let visible = true;
    let raf = 0;
    let zoom = 1.12;       // 入場時に少し寄った状態から引く
    let zoomTarget = 1.0;
    const t0 = performance.now();
    const now = () => (performance.now() - t0) / 1000;

    const addDrop = (x, y, strength) => {
      const i = dropIdx * 4;
      drops[i] = x; drops[i + 1] = y; drops[i + 2] = now(); drops[i + 3] = strength;
      dropIdx = (dropIdx + 1) % MAX_DROPS;
    };

    const upload = () => {
      // 大きすぎる画像は縮めてから渡す（スマホの GPU 対策）
      const maxSide = Math.min(gl.getParameter(gl.MAX_TEXTURE_SIZE), 2048);
      let src = img;
      const w = img.naturalWidth, h = img.naturalHeight;
      if (Math.max(w, h) > maxSide) {
        const k = maxSide / Math.max(w, h);
        const c = document.createElement("canvas");
        c.width = Math.round(w * k); c.height = Math.round(h * k);
        c.getContext("2d").drawImage(img, 0, 0, c.width, c.height);
        src = c;
      }
      const tex = gl.createTexture();
      gl.bindTexture(gl.TEXTURE_2D, tex);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR);
      gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGB, gl.RGB, gl.UNSIGNED_BYTE, src);
      gl.uniform1i(U.uTex, 0);
      gl.uniform1f(U.uImgAspect, w / h);
    };

    const resize = () => {
      const dpr = Math.min(window.devicePixelRatio || 1, 1.75);
      const r = frame.getBoundingClientRect();
      const w = Math.max(2, Math.round(r.width * dpr));
      const h = Math.max(2, Math.round(r.height * dpr));
      if (canvas.width !== w || canvas.height !== h) {
        canvas.width = w; canvas.height = h;
        gl.viewport(0, 0, w, h);
      }
    };

    const draw = () => {
      raf = 0;
      if (!ready || !visible) return;
      resize();
      const t = now();
      zoom += (zoomTarget - zoom) * 0.03;

      // 横が大きく切れる縦長の画面では、ゆっくり左右に視線を動かして3人とも見せる
      const ca = canvas.width / canvas.height;
      const ia = img.naturalWidth / img.naturalHeight;
      const cropX = ca < ia ? 1 - ca / ia : 0;
      const fx = cropX > 0.25 ? 0.5 + 0.42 * Math.sin(t * 0.16) : 0.5;

      gl.uniform2f(U.uRes, canvas.width, canvas.height);
      gl.uniform2f(U.uFocus, fx, 0.3);
      gl.uniform1f(U.uZoom, zoom);
      gl.uniform1f(U.uTime, t);
      gl.uniform4fv(U.uDrops, drops);
      gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
      raf = requestAnimationFrame(draw);
    };
    const kick = () => { if (!raf && ready && visible) raf = requestAnimationFrame(draw); };

    const start = () => {
      try {
        upload();
      } catch (e) {
        return; // 画像を渡せなければ元の <img> のまま
      }
      ready = true;
      frame.classList.add("has-gl");
      kick();
    };
    if (img.complete && img.naturalWidth) start();
    else img.addEventListener("load", start, { once: true });

    // 見えている間だけ描く
    if ("IntersectionObserver" in window) {
      new IntersectionObserver((es) => {
        visible = es[0].isIntersecting && !document.hidden;
        kick();
      }, { threshold: 0 }).observe(frame);
    }
    document.addEventListener("visibilitychange", () => {
      if (document.hidden) visible = false;
      else { const r = frame.getBoundingClientRect(); visible = r.bottom > 0 && r.top < window.innerHeight; kick(); }
    });
    canvas.addEventListener("webglcontextlost", (e) => {
      e.preventDefault();
      ready = false;
      frame.classList.remove("has-gl");
    });

    // 雨粒（ひとりでに落ちる）
    const rain = () => {
      if (visible && ready) addDrop(0.08 + Math.random() * 0.84, 0.08 + Math.random() * 0.84, 0.45 + Math.random() * 0.6);
      window.setTimeout(rain, 900 + Math.random() * 1700);
    };
    window.setTimeout(rain, 1400);

    // カーソル・指に反応
    let lastT = 0, lastX = 0, lastY = 0;
    const toUv = (e) => {
      const r = frame.getBoundingClientRect();
      return [(e.clientX - r.left) / r.width, (e.clientY - r.top) / r.height];
    };
    frame.addEventListener("pointermove", (e) => {
      const moved = Math.hypot(e.clientX - lastX, e.clientY - lastY);
      if (e.timeStamp - lastT > 110 && moved > 26) {
        lastT = e.timeStamp; lastX = e.clientX; lastY = e.clientY;
        const uv = toUv(e);
        addDrop(uv[0], uv[1], 0.5);
      }
    }, { passive: true });
    frame.addEventListener("pointerdown", (e) => {
      const uv = toUv(e);
      addDrop(uv[0], uv[1], 1.25);
    }, { passive: true });

    return {
      splash: (x, y, s) => addDrop(x, y, s),
      setZoom: (z) => { zoomTarget = z; },
    };
  }

  /* ---------- 画面全体のうすい波紋 ---------- */
  function initAmbient(canvas) {
    if (!canvas) return null;
    const ctx = canvas.getContext("2d");
    if (!ctx) return null;

    let w = 0, h = 0, dpr = 1;
    const rings = [];
    const MAX = 16;
    let raf = 0;

    const resize = () => {
      dpr = Math.min(window.devicePixelRatio || 1, 2);
      w = window.innerWidth; h = window.innerHeight;
      canvas.width = Math.round(w * dpr); canvas.height = Math.round(h * dpr);
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    };
    resize();
    window.addEventListener("resize", resize);

    const tick = (t) => {
      raf = 0;
      ctx.clearRect(0, 0, w, h);
      for (let i = rings.length - 1; i >= 0; i--) {
        const r = rings[i];
        const k = (t - r.t0) / r.life;
        if (k >= 1) { rings.splice(i, 1); continue; }
        const e = 1 - Math.pow(1 - k, 3);          // 外へ行くほどゆっくり
        const alpha = (1 - k) * (1 - k) * r.alpha;
        // 水たまりを斜め上から見たような、少しつぶれた輪を2重に
        for (let n = 0; n < 2; n++) {
          const rad = r.size * (e - n * 0.16);
          if (rad <= 0) continue;
          ctx.beginPath();
          ctx.ellipse(r.x, r.y, rad, rad * 0.82, 0, 0, Math.PI * 2);
          ctx.strokeStyle = "rgba(95,158,160," + (alpha * (n ? 0.5 : 1)).toFixed(3) + ")";
          ctx.lineWidth = 1;
          ctx.stroke();
        }
      }
      if (rings.length) raf = requestAnimationFrame(tick);
    };

    const spawn = (x, y, size, alpha) => {
      if (rings.length >= MAX || document.hidden) return;
      rings.push({ x: x, y: y, size: size, alpha: alpha, t0: performance.now(), life: 1900 + size * 6 });
      if (!raf) raf = requestAnimationFrame(tick);
    };

    const ambient = () => {
      spawn(Math.random() * w, Math.random() * h, 70 + Math.random() * 130, 0.34);
      window.setTimeout(ambient, 1100 + Math.random() * 2000);
    };
    window.setTimeout(ambient, 2600);

    window.addEventListener("pointerdown", (e) => spawn(e.clientX, e.clientY, 110 + Math.random() * 70, 0.6), { passive: true });

    return { spawn: spawn };
  }

  window.MelikretWater = { initHero: initHero, initAmbient: initAmbient };
})();
