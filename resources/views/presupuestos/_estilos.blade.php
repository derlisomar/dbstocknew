{{-- Reusa los estilos propios de la Parte 5 (.p5-*) y agrega los de las pestañas. --}}
@include('compras._estilos')
<style>
.p5-tabs{display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));margin-bottom:16px}
.p5-tab{display:block;text-decoration:none;color:var(--p5-tx);background:var(--p5-bg);border:1px solid var(--p5-bd);border-radius:10px;padding:10px 12px;border-top:4px solid var(--p5-bd)}
.p5-tab:hover{background:var(--p5-soft)}
.p5-tab b{display:block;font-size:20px;font-weight:800;margin:2px 0}
.p5-tab.on{outline:2px solid #3b82f6;outline-offset:-2px;background:var(--p5-soft)}
.p5-tab.t-ok{border-top-color:#10b981}.p5-tab.t-info{border-top-color:#3b82f6}.p5-tab.t-bad{border-top-color:#ef4444}.p5-tab.t-warn{border-top-color:#f59e0b}
.p5-row-venc td{background:rgba(239,68,68,.05)}
.p5-seg{display:inline-flex;border:1px solid var(--p5-bd);border-radius:8px;overflow:hidden}
.p5-seg button{padding:7px 12px;font-size:13px;font-weight:700;border:0;background:transparent;color:var(--p5-tx);cursor:pointer}
.p5-seg button.on{background:#2563eb;color:#fff}
</style>
