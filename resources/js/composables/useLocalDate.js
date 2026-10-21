/**
 * Dates as the user's own wall clock shows them. Never build these from
 * toISOString() — that converts to UTC first, so in the Philippines it reads
 * 8 hours behind: before 8 AM "today" comes out as yesterday, and a
 * pre-filled punch time is 8 hours early.
 */
const pad = (n) => String(n).padStart(2, '0');

/** "2026-10-21" */
export function localDateStr(d = new Date()) {
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

/** "2026-10-21T14:05" — the format a datetime-local input expects. */
export function localDateTimeStr(d = new Date()) {
  return `${localDateStr(d)}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}
