import { defineStore } from 'pinia';
import { ref } from 'vue';
import axios from 'axios';

/**
 * Shared, session-scoped cache for the reference lists (employees for a
 * dropdown, departments, shifts, campuses) that nearly every admin/HR page
 * independently re-fetched on its own mount — Attendance, Reports,
 * Tracking, Employees, Departments, Shifts, Admin Accounts all pulled the
 * full roster fresh every time the user navigated between them, even
 * though the data barely changes within a session. Each list is fetched
 * once and reused; call the matching fetch* again with force:true after an
 * action that actually changes that list (e.g. adding an employee).
 */
export const useReferenceDataStore = defineStore('referenceData', () => {
  const employees   = ref([]);
  const departments = ref([]);
  const shifts      = ref([]);
  const campuses     = ref([]);

  const loaded = ref({ employees: false, departments: false, shifts: false, campuses: false });
  const pending = {};

  function makeFetcher(key, url, target) {
    return async function (force = false) {
      if (loaded.value[key] && !force) return target.value;
      if (!force && pending[key]) return pending[key];

      pending[key] = axios.get(url)
        .then((res) => {
          target.value = res.data;
          loaded.value[key] = true;
          return target.value;
        })
        .finally(() => { pending[key] = null; });

      return pending[key];
    };
  }

  const fetchEmployees   = makeFetcher('employees', '/api/employees', employees);
  const fetchDepartments = makeFetcher('departments', '/api/departments', departments);
  const fetchShifts      = makeFetcher('shifts', '/api/shifts', shifts);
  const fetchCampuses    = makeFetcher('campuses', '/api/campuses', campuses);

  // Called on logout — a second account logging in on the same browser
  // session must never see the previous user's cached, campus-scoped lists.
  function reset() {
    employees.value = [];
    departments.value = [];
    shifts.value = [];
    campuses.value = [];
    loaded.value = { employees: false, departments: false, shifts: false, campuses: false };
  }

  return { employees, departments, shifts, campuses, loaded, fetchEmployees, fetchDepartments, fetchShifts, fetchCampuses, reset };
});
