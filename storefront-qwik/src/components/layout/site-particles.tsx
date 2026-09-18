import { component$, useSignal, useVisibleTask$ } from "@builder.io/qwik";
import { SITE_PARTICLES } from "~/lib/config";

interface Particle {
  x: number;
  y: number;
  r: number;
  vx: number;
  vy: number;
  a: number;
}

/**
 * Full-viewport ambient particles (dim + slow). Gated by PUBLIC_SITE_PARTICLES.
 * Skipped when the user prefers reduced motion.
 */
export const SiteParticles = component$(() => {
  const canvasRef = useSignal<HTMLCanvasElement>();

  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(({ cleanup }) => {
    if (!SITE_PARTICLES) {
      return;
    }
    if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
      return;
    }

    const canvas = canvasRef.value;
    if (!canvas) {
      return;
    }
    const ctx = canvas.getContext("2d");
    if (!ctx) {
      return;
    }

    let width = 0;
    let height = 0;
    let raf = 0;
    let particles: Particle[] = [];
    let running = true;

    const countForArea = () => {
      const area = width * height;
      return Math.max(28, Math.min(72, Math.round(area / 28000)));
    };

    const spawn = (): Particle => ({
      x: Math.random() * width,
      y: Math.random() * height,
      r: 0.8 + Math.random() * 1.8,
      vx: (Math.random() - 0.5) * 0.14,
      vy: -0.05 - Math.random() * 0.12,
      a: 0.18 + Math.random() * 0.22,
    });

    const resize = () => {
      const dpr = Math.min(window.devicePixelRatio || 1, 2);
      width = window.innerWidth;
      height = window.innerHeight;
      canvas.width = Math.floor(width * dpr);
      canvas.height = Math.floor(height * dpr);
      canvas.style.width = `${width}px`;
      canvas.style.height = `${height}px`;
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

      const nextCount = countForArea();
      if (particles.length === 0) {
        particles = Array.from({ length: nextCount }, spawn);
      } else if (particles.length < nextCount) {
        while (particles.length < nextCount) {
          particles.push(spawn());
        }
      } else if (particles.length > nextCount) {
        particles.length = nextCount;
      }
    };

    const tick = () => {
      if (!running) {
        return;
      }
      ctx.clearRect(0, 0, width, height);
      for (const p of particles) {
        p.x += p.vx;
        p.y += p.vy;
        if (p.y < -4) {
          p.y = height + 4;
          p.x = Math.random() * width;
        }
        if (p.x < -4) {
          p.x = width + 4;
        } else if (p.x > width + 4) {
          p.x = -4;
        }
        ctx.beginPath();
        ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
        ctx.fillStyle = `rgba(255, 200, 140, ${p.a})`;
        ctx.fill();
      }
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

    resize();
    raf = window.requestAnimationFrame(tick);
    window.addEventListener("resize", resize, { passive: true });
    document.addEventListener("visibilitychange", onVisibility);

    cleanup(() => {
      running = false;
      window.cancelAnimationFrame(raf);
      window.removeEventListener("resize", resize);
      document.removeEventListener("visibilitychange", onVisibility);
    });
  });

  if (!SITE_PARTICLES) {
    return null;
  }

  return (
    <canvas
      ref={canvasRef}
      class="site-particles"
      aria-hidden="true"
    />
  );
});
