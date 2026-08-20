/* ============================================================================
   Mini ERP — Tailwind token mapping
   Maps CSS custom properties (tokens.css) to Tailwind utilities so components
   use `bg-surface`, `text-secondary`, `text-income`, etc. — never hex values.
   Drop into tailwind.config.{js,ts} under theme.extend.
   Direction-aware spacing: prefer ms-/me-/ps-/pe-/inset-* (logical) over ml-/mr-.
   ============================================================================ */
const withOpacity = (v) => `rgb(from var(${v}) r g b / <alpha-value>)`;

module.exports = {
  colors: {
    background:        "var(--background)",
    surface:           "var(--surface)",
    "surface-elevated":"var(--surface-elevated)",
    "surface-muted":   "var(--surface-muted)",
    "surface-hover":   "var(--surface-hover)",
    "surface-active":  "var(--surface-active)",

    border:            "var(--border)",
    "border-strong":   "var(--border-strong)",

    "text-primary":    "var(--text-primary)",
    "text-secondary":  "var(--text-secondary)",
    "text-muted":      "var(--text-muted)",
    "text-disabled":   "var(--text-disabled)",
    "text-inverse":    "var(--text-inverse)",

    primary:           "var(--primary)",
    "primary-hover":   "var(--primary-hover)",
    "primary-active":  "var(--primary-active)",
    "primary-subtle":  "var(--primary-subtle)",
    "on-primary":      "var(--on-primary)",

    success:"var(--success)", "success-subtle":"var(--success-subtle)",
    warning:"var(--warning)", "warning-subtle":"var(--warning-subtle)",
    danger: "var(--danger)",  "danger-subtle":"var(--danger-subtle)",
    info:   "var(--info)",    "info-subtle":"var(--info-subtle)",

    // Financial semantics
    income:"var(--income)", expense:"var(--expense)",
    profit:"var(--profit)", loss:"var(--loss)",
    receivable:"var(--receivable)", payable:"var(--payable)",
    cash:"var(--cash)", bank:"var(--bank)",
    inventory:"var(--inventory)", tax:"var(--tax)",

    chart: {
      1:"var(--chart-1)",2:"var(--chart-2)",3:"var(--chart-3)",4:"var(--chart-4)",
      5:"var(--chart-5)",6:"var(--chart-6)",7:"var(--chart-7)",8:"var(--chart-8)",
    },
  },
  fontFamily: {
    sans:   ["var(--font-sans-en)"],
    ar:     ["var(--font-sans-ar)"],
    display:["var(--font-display)"],
    mono:   ["var(--font-mono)"],
  },
  fontSize: {
    "2xs":"var(--text-2xs)", xs:"var(--text-xs)", sm:"var(--text-sm)",
    base:"var(--text-base)", md:"var(--text-md)", lg:"var(--text-lg)",
    xl:"var(--text-xl)", "2xl":"var(--text-2xl)", "3xl":"var(--text-3xl)",
  },
  borderRadius: {
    xs:"var(--radius-xs)", sm:"var(--radius-sm)", md:"var(--radius-md)",
    lg:"var(--radius-lg)", xl:"var(--radius-xl)", full:"var(--radius-full)",
  },
  boxShadow: {
    sm:"var(--shadow-sm)", md:"var(--shadow-md)", lg:"var(--shadow-lg)",
  },
  ringColor: { DEFAULT: "var(--focus-ring)" },
  transitionTimingFunction: { ds: "var(--ease)" },
};

/* tailwind.config.ts usage:
   import tokens from "./foundation/tailwind.tokens";
   export default {
     darkMode: ['selector', '[data-theme="dark"]'],
     theme: { extend: { ...tokens } },
   }
*/
