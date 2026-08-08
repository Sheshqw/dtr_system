import { ref, watch } from 'vue';

export function useCountUp(source, duration = 900) {
  const display = ref(0);
  let rafId = null;

  function animate(from, to) {
    if (rafId) cancelAnimationFrame(rafId);
    const start = performance.now();
    function step(now) {
      const t    = Math.min((now - start) / duration, 1);
      const ease = 1 - Math.pow(1 - t, 4); // ease-out-quart
      display.value = Math.round(from + (to - from) * ease);
      if (t < 1) rafId = requestAnimationFrame(step);
    }
    rafId = requestAnimationFrame(step);
  }

  watch(source, (val, old) => {
    animate(old ?? 0, val ?? 0);
  }, { immediate: true });

  return display;
}
