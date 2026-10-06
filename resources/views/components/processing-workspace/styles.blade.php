<style>
.process-workspace-grid { display:grid; grid-template-columns:minmax(0, 2.6fr) minmax(280px, 1fr); gap:1.25rem; align-items:start; }
.process-workspace-main, .process-workspace-sidebar { min-width:0; }
.process-workspace-sidebar { display:grid; gap:1rem; position:sticky; top:1rem; }
.process-workspace-header { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; padding:1.2rem 1.5rem; margin-bottom:1.25rem; color:#fff; background:#312e81; border-radius:14px; }
.process-workspace-header h1 { color:#fff; font-size:1.35rem; font-weight:800; margin:0; }
.process-workspace-header p { color:#e0e7ff; margin:.3rem 0 0; font-size:.82rem; }
.process-workspace-status { border:1px solid rgba(255,255,255,.3); border-radius:999px; padding:.4rem .8rem; font-size:.8rem; font-weight:700; }
.process-draft-toolbar { display:flex; align-items:center; justify-content:space-between; gap:.75rem; padding:.8rem 1rem; margin-bottom:1rem; background:#fff; border:1px solid #e2e8f0; border-radius:12px; }
.process-autosave-status { display:inline-flex; align-items:center; gap:.45rem; color:#166534; font-size:.8rem; font-weight:750; }
.process-autosave-status.saving { color:#1d4ed8; }
.process-autosave-status.unsaved { color:#b45309; }
.process-side-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; }
.process-side-toggle { display:flex; align-items:center; justify-content:space-between; width:100%; border:0; background:#f8fafc; color:#0f172a; padding:.85rem 1rem; font-weight:800; font-size:.86rem; text-align:left; }
.process-side-toggle .mdi-chevron-down { transition:transform .2s ease; }
.process-side-toggle[aria-expanded="true"] .mdi-chevron-down { transform:rotate(180deg); }
.process-side-body { padding:1rem; }
.process-side-count { color:#64748b; font-size:.74rem; }
.process-comment-list { display:grid; gap:.75rem; max-height:420px; overflow:auto; }
.process-comment { border:1px solid #e2e8f0; border-radius:10px; padding:.8rem; }
.process-comment-head { display:flex; gap:.65rem; align-items:start; }
.process-comment-avatar { display:inline-flex; align-items:center; justify-content:center; width:34px; height:34px; flex:0 0 34px; border-radius:50%; background:#eef2ff; color:#4338ca; font-size:.72rem; font-weight:800; }
.process-comment-avatar img { width:100%; height:100%; border-radius:inherit; object-fit:cover; }
.process-comment-name { font-weight:750; color:#0f172a; font-size:.82rem; }
.process-comment-role { display:inline-block; background:#f1f5f9; color:#475569; border-radius:999px; padding:.1rem .4rem; font-size:.68rem; font-weight:700; }
.process-comment-time, .process-history-meta { color:#64748b; font-size:.72rem; }
.process-comment-text { margin:.65rem 0 0; white-space:pre-wrap; overflow-wrap:anywhere; color:#334155; font-size:.82rem; }
.process-comment-form { border-top:1px solid #e2e8f0; padding-top:.9rem; margin-top:.9rem; }
.process-comment-form textarea { min-height:85px; resize:vertical; }
.process-history-list { list-style:none; margin:0; padding:0 0 0:.6rem; border-left:1px solid #cbd5e1; }
.process-history-item { position:relative; margin:0 0 1rem; padding-left:1rem; }
.process-history-item:last-child { margin-bottom:0; }
.process-history-item::before { content:""; position:absolute; left:-.93rem; top:.3rem; width:.55rem; height:.55rem; border-radius:50%; background:#4338ca; }
.process-history-title { color:#0f172a; font-weight:750; font-size:.82rem; }
.process-history-context { color:#475569; font-size:.72rem; }
@media(max-width:991px) { .process-workspace-grid { grid-template-columns:minmax(0, 1fr); } .process-workspace-sidebar { position:static; } }
@media(max-width:575px) { .process-draft-toolbar { align-items:stretch; flex-direction:column; } }
</style>
