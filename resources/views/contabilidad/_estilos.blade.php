<style>
.ct{--ct-bg:#fff;--ct-bd:#e5e7eb;--ct-tx:#111827;--ct-mu:#6b7280;--ct-soft:#f9fafb}
html.dark .ct{--ct-bg:#1c2434;--ct-bd:#2e3a47;--ct-tx:#f3f4f6;--ct-mu:#9ca3af;--ct-soft:rgba(255,255,255,.04)}
.ct{color:var(--ct-tx)}
.ct *{box-sizing:border-box}
.ct-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-bottom:16px}
.ct-title{font-size:22px;font-weight:800;margin:0}
.ct-sub{font-size:13px;color:var(--ct-mu);margin:4px 0 0}
.ct-card{background:var(--ct-bg);border:1px solid var(--ct-bd);border-radius:12px;padding:16px;margin-bottom:16px}
.ct-card h3{font-size:14px;font-weight:700;margin:0 0 12px}
.ct-grid{display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(190px,1fr))}
.ct-label{display:block;font-size:10px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--ct-mu);margin-bottom:4px}
.ct-in{width:100%;padding:8px 10px;border-radius:8px;border:1px solid var(--ct-bd);background:var(--ct-bg);color:var(--ct-tx);font-size:14px}
.ct-in:focus{outline:2px solid #3b82f6;outline-offset:-1px}
.ct-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:8px;border:1px solid transparent;font-size:13px;font-weight:700;cursor:pointer;text-decoration:none;background:#2563eb;color:#fff}
.ct-btn:hover{background:#1d4ed8}
.ct-btn.ghost{background:transparent;color:var(--ct-tx);border-color:var(--ct-bd)}
.ct-btn.ghost:hover{background:var(--ct-soft)}
.ct-btn.danger{background:#dc2626}.ct-btn.danger:hover{background:#b91c1c}
.ct-btn.ok{background:#059669}.ct-btn.ok:hover{background:#047857}
.ct-btn.sm{padding:4px 10px;font-size:12px}
.ct-tw{overflow-x:auto}
.ct-table{width:100%;border-collapse:collapse;font-size:13px}
.ct-table th{text-align:left;font-size:10px;letter-spacing:.06em;text-transform:uppercase;color:var(--ct-mu);padding:8px 10px;border-bottom:1px solid var(--ct-bd);white-space:nowrap}
.ct-table td{padding:9px 10px;border-bottom:1px solid var(--ct-bd);vertical-align:middle}
.ct-table tr:last-child td{border-bottom:0}
.ct-r{text-align:right !important}.ct-c{text-align:center !important}
.ct-badge{display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700}
.ct-badge.ok{background:rgba(16,185,129,.15);color:#059669}
.ct-badge.warn{background:rgba(245,158,11,.18);color:#b45309}
.ct-badge.bad{background:rgba(239,68,68,.15);color:#dc2626}
.ct-badge.info{background:rgba(59,130,246,.15);color:#2563eb}
.ct-badge.mute{background:var(--ct-soft);color:var(--ct-mu)}
html.dark .ct-badge.warn{color:#fbbf24}html.dark .ct-badge.ok{color:#34d399}html.dark .ct-badge.bad{color:#f87171}html.dark .ct-badge.info{color:#93c5fd}
.ct-alert{padding:10px 14px;border-radius:10px;font-size:13px;font-weight:600;margin-bottom:14px}
.ct-alert.ok{background:rgba(16,185,129,.12);color:#047857;border:1px solid rgba(16,185,129,.35)}
.ct-alert.bad{background:rgba(239,68,68,.1);color:#b91c1c;border:1px solid rgba(239,68,68,.35)}
html.dark .ct-alert.ok{color:#6ee7b7}html.dark .ct-alert.bad{color:#fca5a5}
.ct-stats{display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));margin-bottom:16px}
.ct-stat{background:var(--ct-bg);border:1px solid var(--ct-bd);border-radius:10px;padding:10px 12px}
.ct-stat b{display:block;font-size:17px;font-weight:800;margin-top:2px}
.ct-stat.warn{border-left:4px solid #f59e0b}.ct-stat.bad{border-left:4px solid #ef4444}.ct-stat.ok{border-left:4px solid #10b981}.ct-stat.info{border-left:4px solid #3b82f6}
.ct-mu{color:var(--ct-mu);font-size:12px}
.ct-total{font-size:22px;font-weight:800}
.ct details{margin-top:6px}.ct details summary{cursor:pointer;font-size:12px;font-weight:700;color:#2563eb;list-style:none}
.ct details[open] summary{margin-bottom:8px}
.ct-pop{border:1px solid var(--ct-bd);border-radius:10px;padding:10px;background:var(--ct-soft);min-width:240px}
.ct-res{position:absolute;z-index:20;left:0;right:0;background:var(--ct-bg);border:1px solid var(--ct-bd);border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.18);max-height:280px;overflow:auto}
.ct-res button{display:block;width:100%;text-align:left;padding:8px 10px;border:0;background:transparent;color:var(--ct-tx);font-size:13px;cursor:pointer}
.ct-res button:hover{background:var(--ct-soft)}

.ct-tabs{display:flex;gap:6px;flex-wrap:wrap;margin:0 0 16px;padding-bottom:10px;border-bottom:1px solid var(--ct-bd)}
.ct-tabs a{padding:6px 12px;border-radius:999px;font-size:12px;font-weight:700;text-decoration:none;color:var(--ct-mu);border:1px solid var(--ct-bd)}
.ct-tabs a:hover{background:var(--ct-soft);color:var(--ct-tx)}
.ct-tabs a.on{background:#2563eb;border-color:#2563eb;color:#fff}
.ct-num{font-variant-numeric:tabular-nums;text-align:right;white-space:nowrap}
.ct-asi{border:1px solid var(--ct-bd);border-radius:12px;margin-bottom:12px;overflow:hidden;background:var(--ct-bg)}
.ct-asi-h{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center;padding:10px 14px;background:var(--ct-soft);border-bottom:1px solid var(--ct-bd)}
.ct-asi-h b{font-size:13px}
.ct-haber{padding-left:28px !important}
.ct-sub td{font-weight:800;background:var(--ct-soft)}
.ct-rowx{display:grid;gap:8px;grid-template-columns:minmax(220px,3fr) 1fr 1fr minmax(120px,2fr) 36px;align-items:center;margin-bottom:8px}
@media(max-width:760px){.ct-rowx{grid-template-columns:1fr 1fr}.ct-rowx>:first-child,.ct-rowx>:nth-child(4){grid-column:1/-1}}
.ct-quad{display:grid;gap:14px;grid-template-columns:repeat(auto-fit,minmax(300px,1fr))}
.ct-hint{font-size:12px;color:var(--ct-mu);margin:6px 0 0}
</style>
