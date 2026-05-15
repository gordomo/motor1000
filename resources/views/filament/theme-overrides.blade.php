<style>
    :root {
        --m1-bg: #eef2f7;
        --m1-surface: #2c3441;
        --m1-card: #f8fafc;
        --m1-border: #dbe1ea;
        --m1-text: #111827;
        --m1-text-soft: #6b7280;
        --m1-accent: #f59e0b;
        --m1-success: #10b981;
        --m1-danger: #ef4444;
        --m1-radius: 12px;
        --tenant-primary: {{ \App\Helpers\TenantBranding::primary() }};
        --tenant-primary-rgb: {{ \App\Helpers\TenantBranding::primaryRgb() }};
        --tenant-secondary: {{ \App\Helpers\TenantBranding::secondary() }};
        --tenant-secondary-rgb: {{ \App\Helpers\TenantBranding::secondaryRgb() }};
    }

    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

    html,
    body {
        font-family: 'Inter', 'SF Pro Text', 'Segoe UI', sans-serif;
    }

    .fi-layout {
        background: var(--m1-bg);
    }

    .fi-main {
        background: var(--m1-bg) !important;
    }

    .fi-main-ctn {
        max-width: 1560px;
        margin-inline: auto;
        padding-inline: 0.9rem;
    }

    .fi-topbar,
    .fi-sidebar {
        background: var(--m1-surface) !important;
    }

    .fi-topbar {
        background: transparent !important;
        border-bottom: 0 !important;
        box-shadow: none !important;
        margin-bottom: 0 !important;
        padding-top: 0.85rem !important;
        padding-bottom: 0.35rem !important;
    }

    .fi-topbar > div {
        background: transparent !important;
    }

    .m1-topbar-context {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        margin-left: 0.25rem;
        padding: 0.45rem 0.8rem;
        border: 1px solid #dbe1ea;
        border-radius: 999px;
        background: rgba(248, 250, 252, 0.96);
        color: #111827;
        box-shadow: 0 1px 3px rgba(17, 24, 39, 0.06);
    }

    .m1-topbar-context__eyebrow {
        color: #6b7280;
        font-size: 0.66rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .m1-topbar-context__value {
        color: #111827;
        font-size: 0.84rem;
        font-weight: 700;
        white-space: nowrap;
    }

    .m1-topbar-context__logo {
        height: 1.2rem;
        width: auto;
        max-width: 3rem;
        border-radius: 4px;
        object-fit: contain;
    }

    .fi-sidebar {
        border-right: 1px solid rgba(255, 255, 255, 0.06) !important;
    }

    .fi-sidebar-header {
        background: rgba(255, 255, 255, 0.02) !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06) !important;
    }

    .fi-sidebar-nav-groups {
        padding: 0.35rem 0.45rem 0.7rem;
    }

    .fi-sidebar .fi-brand,
    .fi-sidebar .fi-sidebar-item-label {
        color: #f3f4f6 !important;
        font-weight: 500;
    }

    .fi-sidebar .fi-sidebar-item-icon {
        color: #cbd5e1 !important;
    }

    .fi-sidebar-nav-groups .fi-sidebar-group-label {
        color: #cbd5e1 !important;
        font-size: 0.66rem;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        margin-top: 0.65rem;
        margin-bottom: 0.35rem;
    }

    .fi-sidebar-item-button {
        border-radius: 10px !important;
        min-height: 2.25rem;
        padding-inline: 0.55rem !important;
        border: 1px solid transparent;
        transition: background-color 0.12s ease, border-color 0.12s ease;
    }

    .fi-sidebar-item-button:hover {
        background: rgba(255, 255, 255, 0.04) !important;
        border-color: rgba(255, 255, 255, 0.06) !important;
    }

    .fi-sidebar-item-active .fi-sidebar-item-button {
        background: rgba(var(--tenant-primary-rgb), 0.1) !important;
        border-color: rgba(var(--tenant-primary-rgb), 0.3) !important;
    }

    .fi-sidebar-item-active .fi-sidebar-item-label,
    .fi-sidebar-item-active .fi-sidebar-item-icon {
        color: #fff7d6 !important;
    }

    .fi-sidebar-item-badge {
        background: rgba(var(--tenant-primary-rgb), 0.14) !important;
        color: #fff !important;
        border: 1px solid rgba(var(--tenant-primary-rgb), 0.28) !important;
        border-radius: 999px !important;
    }

    .fi-section,
    .fi-ta,
    .fi-wi,
    .fi-modal-window {
        background: var(--m1-card) !important;
        border: 1px solid var(--m1-border) !important;
        border-radius: var(--m1-radius) !important;
        box-shadow: 0 1px 3px rgba(17, 24, 39, 0.06);
    }

    .fi-header-heading,
    .fi-ta-header-heading,
    .fi-wi-stats-overview-stat-value,
    .fi-section-header-heading {
        color: var(--m1-text) !important;
        font-weight: 700;
        letter-spacing: -0.01em;
    }

    .fi-header-subheading,
    .fi-ta-header-description,
    .fi-wi-stats-overview-stat-description,
    .fi-ta-text,
    .fi-section-header-description {
        color: var(--m1-text-soft) !important;
    }

    .fi-ta-table thead tr {
        background: #f3f6fb;
    }

    .fi-ta-table tbody tr {
        border-bottom-color: #e5e7eb !important;
        transition: background-color 0.12s ease;
    }

    .fi-ta-table tbody tr:hover {
        background: #f3f6fb;
    }

    .fi-ta-table th,
    .fi-ta-table td {
        padding: 0.5rem 0.55rem !important;
        font-size: 0.82rem;
        vertical-align: middle;
    }

    .fi-ta-table td {
        white-space: normal;
    }

    .fi-input,
    .fi-select-input,
    .fi-textarea,
    .fi-date-time-picker-input {
        background: #ffffff !important;
        border-color: #d1d5db !important;
        color: #111827 !important;
    }

    .fi-input:focus,
    .fi-select-input:focus,
    .fi-textarea:focus,
    .fi-date-time-picker-input:focus {
        border-color: var(--tenant-primary) !important;
        box-shadow: 0 0 0 3px rgba(var(--tenant-primary-rgb), 0.16) !important;
    }

    .fi-btn {
        border-radius: 10px !important;
    }

    .fi-badge {
        border-radius: 999px !important;
        font-weight: 600;
    }

    .m1-kpi {
        border-radius: 12px;
        border: 1px solid #dbe1ea;
        background: #ffffff;
        box-shadow: 0 1px 3px rgba(17, 24, 39, 0.08);
        padding: 1rem 1rem 0.9rem;
    }

    .m1-kpi__header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.55rem;
    }

    .m1-kpi__title {
        color: #6b7280;
        font-size: 0.76rem;
        font-weight: 600;
        letter-spacing: 0.03em;
        text-transform: uppercase;
    }

    .m1-kpi__icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.75rem;
        height: 1.75rem;
        border-radius: 8px;
        background: #f3f4f6;
        color: #374151;
    }

    .m1-kpi__icon svg {
        width: 0.9rem;
        height: 0.9rem;
    }

    .m1-kpi__value-row {
        margin-top: 0.55rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
    }

    .m1-kpi__value {
        color: #111827;
        font-size: 1.45rem;
        font-weight: 700;
        line-height: 1.15;
    }

    .m1-kpi__trend {
        font-size: 0.74rem;
        font-weight: 600;
        border-radius: 999px;
        padding: 0.15rem 0.45rem;
    }

    .m1-kpi__trend.is-positive { background: #ecfdf5; color: #047857; }
    .m1-kpi__trend.is-negative { background: #fef2f2; color: #b91c1c; }

    .m1-kpi__hint,
    .m1-kpi__caption {
        margin-top: 0.3rem;
        color: #6b7280;
        font-size: 0.8rem;
    }

    .m1-kpi--amber .m1-kpi__icon { background: #fef3c7; color: #92400e; }
    .m1-kpi--blue .m1-kpi__icon { background: #dbeafe; color: #1d4ed8; }
    .m1-kpi--green .m1-kpi__icon { background: #d1fae5; color: #047857; }
    .m1-kpi--red .m1-kpi__icon { background: #fee2e2; color: #b91c1c; }

    .m1-badge {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 0.14rem 0.5rem;
        font-size: 0.72rem;
        font-weight: 600;
        border: 1px solid #d1d5db;
    }

    .m1-badge--amber { background: #fffbeb; color: #92400e; }
    .m1-badge--blue { background: #eff6ff; color: #1d4ed8; }
    .m1-badge--green { background: #ecfdf5; color: #047857; }
    .m1-badge--red { background: #fef2f2; color: #b91c1c; }
    .m1-badge--slate { background: #f3f4f6; color: #374151; }

    .m1-activity {
        display: grid;
        grid-template-columns: auto 1fr auto;
        gap: 0.55rem;
        align-items: center;
        padding: 0.6rem;
        border: 1px solid #dbe1ea;
        border-radius: 10px;
        background: #ffffff;
    }

    .m1-activity__icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.7rem;
        height: 1.7rem;
        border-radius: 8px;
        background: #f3f4f6;
        color: #374151;
    }

    .m1-activity__icon svg {
        width: 0.9rem;
        height: 0.9rem;
    }

    .m1-activity__title {
        margin: 0;
        color: #111827;
        font-size: 0.85rem;
        font-weight: 600;
    }

    .m1-activity__subtitle,
    .m1-activity__time {
        margin: 0;
        color: #6b7280;
        font-size: 0.76rem;
    }

    .m1-board {
        display: grid;
        gap: 0.75rem;
        grid-template-columns: repeat(6, minmax(250px, 1fr));
        overflow-x: auto;
        padding-bottom: 0.2rem;
    }

    .m1-board__col {
        border-radius: 12px;
        border: 1px solid #dbe1ea;
        background: #f3f6fb;
        min-height: 380px;
        display: flex;
        flex-direction: column;
    }

    .m1-board__col-header {
        padding: 0.72rem;
        border-bottom: 1px solid #dbe1ea;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.4rem;
    }

    .m1-board__cards {
        padding: 0.62rem;
        display: grid;
        gap: 0.5rem;
    }

    .m1-wo-card {
        border-radius: 10px;
        border: 1px solid #dbe1ea;
        background: #ffffff;
        padding: 0.68rem;
        cursor: grab;
        transition: border-color 0.12s ease, background-color 0.12s ease;
    }

    .m1-wo-card:hover {
        border-color: #cbd5e1;
        background: #f9fafb;
    }

    .m1-wo-card__title {
        font-size: 0.86rem;
        font-weight: 700;
        color: #111827;
        line-height: 1.25;
    }

    .m1-wo-card__meta {
        margin-top: 0.25rem;
        font-size: 0.75rem;
        color: #6b7280;
    }

    .m1-wo-card__footer {
        margin-top: 0.5rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.4rem;
    }

    /* Loading state spinner */
    @keyframes m1-spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }

    [wire\:loading] {
        display: inline-flex !important;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.875rem;
        color: #6b7280;
    }

    [wire\:loading]::before {
        content: '';
        display: inline-block;
        width: 1rem;
        height: 1rem;
        border: 2px solid #dbe1ea;
        border-top-color: var(--tenant-primary);
        border-radius: 999px;
        animation: m1-spin 1s linear infinite;
    }

    .fi-input.wire\:loading,
    .fi-select-input.wire\:loading {
        opacity: 0.6;
    }

    @media (max-width: 1280px) {
        .m1-board {
            grid-template-columns: repeat(3, minmax(250px, 1fr));
        }
    }

    @media (max-width: 768px) {
        .fi-main-ctn {
            padding-inline: 0.8rem;
        }

        .m1-board {
            grid-template-columns: repeat(2, minmax(235px, 1fr));
        }

        .m1-kpi__value {
            font-size: 1.3rem;
        }
    }
</style>
