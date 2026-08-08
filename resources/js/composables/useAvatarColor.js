const COLOR_PAIRS = [
  ['#1228CC', '#0E1EC0'], // Sapphire primary
  ['#0891B2', '#0E7490'], // Cyan-navy
  ['#2563EB', '#1D4ED8'], // Royal blue
  ['#4338CA', '#3730A3'], // Indigo
  ['#7C3AED', '#5B21B6'], // Violet
  ['#0550A8', '#080E80'], // Deep navy
  ['#1E40AF', '#1E3A8A'], // Steel blue
  ['#0E7490', '#155E75'], // Dark cyan
];

export function useAvatarColor() {
  function avatarStyle(name) {
    if (!name) return { background: 'linear-gradient(135deg, #1228CC, #0E1EC0)', color: '#fff' };
    const hash = [...name].reduce((acc, ch) => acc + ch.charCodeAt(0), 0);
    const [from, to] = COLOR_PAIRS[hash % COLOR_PAIRS.length];
    return { background: `linear-gradient(135deg, ${from}, ${to})`, color: '#fff' };
  }
  return { avatarStyle };
}
