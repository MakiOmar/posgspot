import { component$, useSignal, useVisibleTask$ } from "@builder.io/qwik";

interface Particle {
  x: number;
  y: number;
  r: number;
  vx: number;
  vy: number;
  a: number;
  life: number;
  maxLife: number;
  hue: number;
}

/**
 * Energetic particles scoped to the page title bar (sparks rising with glow).
 * Honors prefers-reduced-motion.
 */
export const PageTitleBarParticles = component$(() => {
  const canvasRef = useSignal<HTMLCanvasElement>();

  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(({ cleanup }) => {
    if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
      return;
    }

    const canvas = canvasRef.value;
    if (!canvas) return;
    const parent = canvas.parentElement;
    if (!parent) return;
    const ctx = canvas.getContext("2d");
    if (!ctx) return;

    let width = 0;
    let height = 0;
    let raf = 0;
    let particles: Particle[] = [];
    let running = true;

    const accent = getComputedStyle(parent).getPropertyValue("--gs-accent").trim() || "#f97316";

    const countForArea = () => Math.max(36, Math.min(90, Math.round((width * height) / 4200)));

    const spawn = (fromBottom = true): Particle => {
      const warm = Math.random() > 0.35;
      return {
        x: Math.random() * width,
        y: fromBottom ? height + Math.random() * 12 : Math.random() * height,
        r: 0.6 + Math.random() * 2.4,
        vx: (Math.random() - 0.5) * 0.55,
        vy: -0.35 - Math.random() * 1.15,
        a: 0.35 + Math.random() * 0.55,
        life: 0,
        maxLife: 90 + Math.random() * 140,
        hue: warm ? 0 : 1,
      };
    };

    const resize = () => {
      const dpr = Math.min(window.devicePixelRatio || 1, 2);
      const rect = parent.getBoundingClientRect();
      width = Math.max(1, Math.floor(rect.width));
      height = Math.max(1, Math.floor(rect.height));
      canvas.width = Math.floor(width * dpr);
      canvas.height = Math.floor(height * dpr);
      canvas.style.width = `${width}px`;
      canvas.style.height = `${height}px`;
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

      const next = countForArea();
      if (particles.length === 0) {
        particles = Array.from({ length: next }, () => spawn(false));
      } else if (particles.length < next) {
        while (particles.length < next) particles.push(spawn(false));
      } else if (particles.length > next) {
        particles.length = next;
      }
    };

    const fillFor = (p: Particle) => {
      const fade = 1 - p.life / p.maxLife;
      const alpha = Math.max(0, p.a * fade);
      if (p.hue === 0) {
        return accent.startsWith("#")
          ? hexToRgba(accent, alpha)
          : `rgba(249, 115, 22, ${alpha})`;
      }
      return `rgba(56, 189, 248, ${alpha * 0.85})`;
    };

    const tick = () => {
      if (!running) return;
      ctx.clearRect(0, 0, width, height);
      ctx.globalCompositeOperation = "lighter";

      for (const p of particles) {
        p.x += p.vx;
        p.y += p.vy;
        p.vx += (Math.random() - 0.5) * 0.04;
        p.life += 1;

        if (p.life >= p.maxLife || p.y < -8 || p.x < -8 || p.x > width + 8) {
          Object.assign(p, spawn(true));
        }

        ctx.beginPath();
        ctx.shadowBlur = 8 + p.r * 4;
        ctx.shadowColor = fillFor(p);
        ctx.fillStyle = fillFor(p);
        ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
        ctx.fill();
      }

      ctx.shadowBlur = 0;
      ctx.globalCompositeOperation = "source-over";
      raf = window.requestAnimationFrame(tick);
    };

    const onVisibility = () => {
      if (document.hidden) {
        running = false;
        window.cancelAnimationFrame(raf);
        return;
      }
      if (!running) {
        running = true;
        raf = window.requestAnimationFrame(tick);
      }
    };

    const ro = typeof ResizeObserver !== "undefined" ? new ResizeObserver(resize) : null;
    ro?.observe(parent);
    resize();
    raf = window.requestAnimationFrame(tick);
    window.addEventListener("resize", resize, { passive: true });
    document.addEventListener("visibilitychange", onVisibility);

    cleanup(() => {
      running = false;
      window.cancelAnimationFrame(raf);
      window.removeEventListener("resize", resize);
      document.removeEventListener("visibilitychange", onVisibility);
      ro?.disconnect();
    });
  });

  return <canvas ref={canvasRef} class="page-title-bar__particles" aria-hidden="true" />;
});

function hexToRgba(hex: string, alpha: number): string {
  const raw = hex.replace("#", "");
  const full =
    raw.length === 3
      ? raw
          .split("")
          .map((c) => c + c)
          .join("")
      : raw;
  if (full.length !== 6) {
    return `rgba(249, 115, 22, ${alpha})`;
  }
  const n = Number.parseInt(full, 16);
  const r = (n >> 16) & 255;
  const g = (n >> 8) & 255;
  const b = n & 255;
  return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}
