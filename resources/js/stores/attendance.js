import { defineStore } from 'pinia';
import { ref } from 'vue';
import axios from 'axios';

export const useAttendanceStore = defineStore('attendance', () => {
    const rows    = ref([]);
    const summary = ref({ present: 0, late: 0, absent: 0 });
    const date    = ref(new Date().toISOString().split('T')[0]);
    const loading = ref(false);

    async function fetchToday(filters = {}) {
        loading.value = true;
        try {
            const params = { date: date.value, ...filters };
            const res    = await axios.get('/api/attendance/today', { params });
            rows.value    = res.data.rows;
            summary.value = res.data.summary;
            date.value    = res.data.date;
        } finally {
            loading.value = false;
        }
    }

    return { rows, summary, date, loading, fetchToday };
});
