import { ref, watch, onUnmounted } from 'vue';

export function useDebounce(source, delay = 300) {
    const debounced = ref(source.value);
    let timer = null;

    const stop = watch(source, (val) => {
        clearTimeout(timer);
        timer = setTimeout(() => { debounced.value = val; }, delay);
    });

    onUnmounted(() => {
        clearTimeout(timer);
        stop();
    });

    return debounced;
}
