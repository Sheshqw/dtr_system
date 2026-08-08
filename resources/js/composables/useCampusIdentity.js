import { computed } from 'vue';
import { useAuthStore } from '@/stores/auth';

/**
 * Per-campus visual identity.
 *
 * heroBand   — campus accent color for use on WHITE/light backgrounds
 *              (panel accent bars, campus band strip, avatar text)
 * colorDim   — very light tint for avatar backgrounds on white cards
 * textColor  — campus color for text/elements on DARK hero backgrounds
 * heroGradient — dark gradient for the dashboard topbar
 * tagline    — geographic subtitle shown in the topbar
 */
const HERO = 'linear-gradient(135deg, #080E88 0%, #0B18A8 40%, #1020BC 70%, #0A18A8 100%)';

const CAMPUS_THEMES = {
  CAN: {
    heroBand:     '#C9A227',
    colorDim:     '#FEF9E7',
    textColor:    '#FDE68A',
    heroGradient: HERO,
    tagline:      'Cantilan, Surigao del Sur',
  },
  TAN: {
    heroBand:     '#15803D',
    colorDim:     '#F0FDF4',
    textColor:    '#86EFAC',
    heroGradient: HERO,
    tagline:      'Tandag City, Surigao del Sur',
  },
  LIA: {
    heroBand:     '#0369A1',
    colorDim:     '#F0F9FF',
    textColor:    '#7DD3FC',
    heroGradient: HERO,
    tagline:      'Lianga, Surigao del Sur',
  },
  BIS: {
    heroBand:     '#6D28D9',
    colorDim:     '#F5F3FF',
    textColor:    '#C4B5FD',
    heroGradient: HERO,
    tagline:      'City of Bislig, Surigao del Sur',
  },
  SMC: {
    heroBand:     '#B91C1C',
    colorDim:     '#FEF2F2',
    textColor:    '#FCA5A5',
    heroGradient: HERO,
    tagline:      'San Miguel, Surigao del Sur',
  },
  CAG: {
    heroBand:     '#B45309',
    colorDim:     '#FFFBEB',
    textColor:    '#FCD34D',
    heroGradient: HERO,
    tagline:      'Cagwait, Surigao del Sur',
  },
  TAG: {
    heroBand:     '#0F766E',
    colorDim:     '#F0FDFA',
    textColor:    '#5EEAD4',
    heroGradient: HERO,
    tagline:      'Tagbina, Surigao del Sur',
  },
};

const DEFAULT_THEME = {
  heroBand:     '#C9A227',
  colorDim:     '#FEF9E7',
  textColor:    '#FDE68A',
  heroGradient: 'linear-gradient(135deg, #080E80 0%, #0A16A0 50%, #1020C4 100%)',
  tagline:      'Northern Eastern Mindanao State University',
};

export function useCampusIdentity() {
  const auth = useAuthStore();

  const campusCode = computed(() => auth.employee?.campus?.code ?? null);
  const campusName = computed(() => auth.employee?.campus?.name ?? 'All Campuses');
  const campus     = computed(() => CAMPUS_THEMES[campusCode.value] ?? DEFAULT_THEME);

  return { campus, campusCode, campusName };
}
