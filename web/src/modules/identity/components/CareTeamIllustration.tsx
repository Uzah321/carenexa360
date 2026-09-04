/**
 * A flat, geometric approximation of "care team + patient" artwork built
 * from primitive shapes rather than a licensed illustration asset — keeps
 * the login page on-brand (paper/ink/teal tokens) without an external image
 * dependency.
 */
export function CareTeamIllustration({ className = "" }: { className?: string }) {
  return (
    <svg viewBox="0 0 420 260" className={className} aria-hidden>
      <ellipse cx="210" cy="242" rx="160" ry="13" fill="var(--line)" opacity="0.5" />

      {/* Heart + pulse halo behind the group */}
      <path
        d="M210 78 C178 42 116 52 116 98 C116 140 168 172 210 198 C252 172 304 140 304 98 C304 52 242 42 210 78 Z"
        fill="var(--tealtint)"
      />
      <path
        d="M128 118 L172 118 L185 92 L200 138 L213 108 L223 118 L292 118"
        fill="none"
        stroke="var(--teal)"
        strokeWidth="3"
        strokeLinecap="round"
        strokeLinejoin="round"
        opacity="0.55"
      />

      {/* ID card, tucked behind the nurse */}
      <g transform="translate(50 132)">
        <rect width="58" height="74" rx="8" fill="white" stroke="var(--line)" strokeWidth="2" />
        <circle cx="29" cy="25" r="10" fill="var(--skytint)" />
        <rect x="13" y="42" width="32" height="5" rx="2.5" fill="var(--line)" />
        <rect x="13" y="53" width="22" height="5" rx="2.5" fill="var(--line)" />
      </g>

      {/* Shield + check badge, upper right of the group */}
      <g transform="translate(280 46)">
        <path
          d="M20 0 L38 7 V20 C38 32 30 41 20 45 C10 41 2 32 2 20 V7 Z"
          fill="var(--limetint)"
          stroke="var(--lime)"
          strokeWidth="2"
        />
        <path
          d="M12 22 L18 28 L29 15"
          fill="none"
          stroke="var(--lime)"
          strokeWidth="3"
          strokeLinecap="round"
          strokeLinejoin="round"
        />
      </g>

      {/* Nurse — left */}
      <g transform="translate(88 122)">
        <path d="M6 128 C6 88 13 68 39 68 C65 68 72 88 72 128 Z" fill="var(--teal)" />
        <rect x="30" y="96" width="26" height="32" rx="3" fill="white" stroke="var(--line)" strokeWidth="1.5" />
        <rect x="35" y="104" width="16" height="3" rx="1.5" fill="var(--line)" />
        <rect x="35" y="111" width="16" height="3" rx="1.5" fill="var(--line)" />
        <circle cx="39" cy="36" r="26" fill="#f3c9a1" />
        <path
          d="M13 33 C13 8 65 8 65 33 C65 20 57 13 39 13 C21 13 13 20 13 33 Z"
          fill="var(--ink)"
        />
      </g>

      {/* Patient — center, slightly shorter and set back */}
      <g transform="translate(178 138)">
        <path
          d="M4 112 C4 78 10 60 33 60 C56 60 62 78 62 112 Z"
          fill="var(--skytint)"
          stroke="var(--sky)"
          strokeWidth="1.5"
        />
        <circle cx="33" cy="30" r="23" fill="#ecd6c2" />
        <path
          d="M10 26 C10 4 56 4 56 26 C56 32 54 22 51 18 C42 24 24 24 15 18 C12 22 10 20 10 26 Z"
          fill="#d9d9d9"
        />
      </g>

      {/* Doctor — right */}
      <g transform="translate(258 116)">
        <path d="M4 132 C4 90 10 70 38 70 C66 70 72 90 72 132 Z" fill="white" stroke="var(--line)" strokeWidth="2" />
        <path d="M38 70 L32 90 L38 98 L44 90 Z" fill="var(--ink)" />
        <rect x="14" y="116" width="48" height="8" rx="4" fill="var(--teal)" opacity="0.3" />
        <circle cx="38" cy="36" r="26" fill="#f3c9a1" />
        <path
          d="M12 31 C12 7 64 7 64 31 C64 18 56 12 38 12 C20 12 12 18 12 31 Z"
          fill="var(--ink)"
        />
      </g>
    </svg>
  );
}
