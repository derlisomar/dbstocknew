<style>
.p5{--p5-bg:#fff;--p5-bd:#e5e7eb;--p5-tx:#111827;--p5-mu:#6b7280;--p5-soft:#f9fafb}
html.dark .p5{--p5-bg:#1c2434;--p5-bd:#2e3a47;--p5-tx:#f3f4f6;--p5-mu:#9ca3af;--p5-soft:rgba(255,255,255,.04)}
.p5{color:var(--p5-tx)}
.p5 *{box-sizing:border-box}
.p5-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-bottom:16px}
.p5-title{font-size:22px;font-weight:800;margin:0}
.p5-sub{font-size:13px;color:var(--p5-mu);margin:4px 0 0}
.p5-card{background:var(--p5-bg);border:1px solid var(--p5-bd);border-radius:12px;padding:16px;margin-bottom:16px}
.p5-card h3{font-size:14px;font-weight:700;margin:0 0 12px}
.p5-grid{display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(190px,1fr))}
.p5-label{display:block;font-size:10px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--p5-mu);margin-bottom:4px}
.p5-in{width:100%;padding:8px 10px;border-radius:8px;border:1px solid var(--p5-bd);background:var(--p5-bg);color:var(--p5-tx);font-size:14px}
.p5-in:focus{outline:2px solid #3b82f6;outline-offset:-1px}
.p5-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:8px;border:1px solid transparent;font-size:13px;font-weight:700;cursor:pointer;text-decoration:none;background:#2563eb;color:#fff}
.p5-btn:hover{background:#1d4ed8}
.p5-btn.ghost{background:transparent;color:var(--p5-tx);border-color:var(--p5-bd)}
.p5-btn.ghost:hover{background:var(--p5-soft)}
.p5-btn.danger{background:#dc2626}.p5-btn.danger:hover{background:#b91c1c}
.p5-btn.ok{background:#059669}.p5-btn.ok:hover{background:#047857}
.p5-btn.sm{padding:4px 10px;font-size:12px}
.p5-tw{overflow-x:auto}
.p5-table{width:100%;border-collapse:collapse;font-size:13px}
.p5-table th{text-align:left;font-size:10px;letter-spacing:.06em;text-transform:uppercase;color:var(--p5-mu);padding:8px 10px;border-bottom:1px solid var(--p5-bd);white-space:nowrap}
.p5-table td{padding:9px 10px;border-bottom:1px solid var(--p5-bd);vertical-align:middle}
.p5-table tr:last-child td{border-bottom:0}
.p5-r{text-align:right !important}.p5-c{text-align:center !important}
.p5-badge{display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700}
.p5-badge.ok{background:rgba(16,185,129,.15);color:#059669}
.p5-badge.warn{background:rgba(245,158,11,.18);color:#b45309}
.p5-badge.bad{background:rgba(239,68,68,.15);color:#dc2626}
.p5-badge.info{background:rgba(59,130,246,.15);color:#2563eb}
.p5-badge.mute{background:var(--p5-soft);color:var(--p5-mu)}
html.dark .p5-badge.warn{color:#fbbf24}html.dark .p5-badge.ok{color:#34d399}html.dark .p5-badge.bad{color:#f87171}html.dark .p5-badge.info{color:#93c5fd}
.p5-alert{padding:10px 14px;border-radius:10px;font-size:13px;font-weight:600;margin-bottom:14px}
.p5-alert.ok{background:rgba(16,185,129,.12);color:#047857;border:1px solid rgba(16,185,129,.35)}
.p5-alert.bad{background:rgba(239,68,68,.1);color:#b91c1c;border:1px solid rgba(239,68,68,.35)}
html.dark .p5-alert.ok{color:#6ee7b7}html.dark .p5-alert.bad{color:#fca5a5}
.p5-stats{display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));margin-bottom:16px}
.p5-stat{background:var(--p5-bg);border:1px solid var(--p5-bd);border-radius:10px;padding:10px 12px}
.p5-stat b{display:block;font-size:17px;font-weight:800;margin-top:2px}
.p5-stat.warn{border-left:4px solid #f59e0b}.p5-stat.bad{border-left:4px solid #ef4444}.p5-stat.ok{border-left:4px solid #10b981}.p5-stat.info{border-left:4px solid #3b82f6}
.p5-mu{color:var(--p5-mu);font-size:12px}
.p5-total{font-size:22px;font-weight:800}
.p5 details{margin-top:6px}.p5 details summary{cursor:pointer;font-size:12px;font-weight:700;color:#2563eb;list-style:none}
.p5 details[open] summary{margin-bottom:8px}
.p5-pop{border:1px solid var(--p5-bd);border-radius:10px;padding:10px;background:var(--p5-soft);min-width:240px}
.p5-res{position:absolute;z-index:20;left:0;right:0;background:var(--p5-bg);border:1px solid var(--p5-bd);border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.18);max-height:280px;overflow:auto}
.p5-res button{display:block;width:100%;text-align:left;padding:8px 10px;border:0;background:transparent;color:var(--p5-tx);font-size:13px;cursor:pointer}
.p5-res button:hover{background:var(--p5-soft)}
</style>
