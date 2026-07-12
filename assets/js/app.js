/* ── Utility ──────────────────────────────────────── */
const $ = id => document.getElementById(id);
const api = async (url, opts = {}) => {
  const res = await fetch(APP_CONFIG.baseUrl + url, {
    headers: { 'Content-Type': 'application/json' },
    ...opts,
  });
  return res.json();
};
const post = (url, body) => api(url, { method: 'POST', body: JSON.stringify(body) });

function toast(msg, type = 'success', icon = '') {
  const c = $('toast-container');
  const el = document.createElement('div');
  el.className = `toast ${type}`;
  el.innerHTML = `<span>${icon || (type === 'success' ? '✅' : type === 'error' ? '❌' : 'ℹ️')}</span><span>${msg}</span>`;
  c.appendChild(el);
  setTimeout(() => el.remove(), 3500);
}

function showModal(html) {
  const c = $('modal-container');
  c.innerHTML = html;
  c.querySelector('.modal-overlay')?.addEventListener('click', e => {
    if (e.target.classList.contains('modal-overlay')) closeModal();
  });
}
function closeModal() { $('modal-container').innerHTML = ''; }

/* ── Confirm / Prompt modals (Promise-based) ──────── */
function _escapeHtml(s) {
  return String(s ?? '').replace(/[&<>"']/g, c =>
    ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c]));
}

function confirmModal(opts = {}) {
  const {
    title = 'ยืนยันการทำรายการ', message = '', icon = '',
    confirmText = 'ยืนยัน', cancelText = 'ยกเลิก', danger = false,
  } = opts;
  return new Promise(resolve => {
    const host = document.createElement('div');
    host.className = 'modal-overlay';
    host.style.zIndex = '300';
    const autoIcon = icon || (danger ? '⚠️' : '❓');
    host.innerHTML = `
      <div class="modal" style="max-width:420px">
        <div class="modal-body" style="text-align:center;padding:26px 24px 20px">
          <div style="width:56px;height:56px;border-radius:50%;margin:0 auto 14px;display:flex;align-items:center;justify-content:center;font-size:26px;background:${danger?'#EF444418':'#7B1F3215'}">${autoIcon}</div>
          <div class="fw-700" style="font-size:16px;margin-bottom:6px">${_escapeHtml(title)}</div>
          <div class="fs-13 text-muted" style="line-height:1.6;white-space:pre-line">${_escapeHtml(message)}</div>
        </div>
        <div class="modal-footer" style="justify-content:center">
          <button class="btn btn-ghost" data-act="cancel">${_escapeHtml(cancelText)}</button>
          <button class="btn ${danger?'btn-danger':'btn-primary'}" data-act="ok">${_escapeHtml(confirmText)}</button>
        </div>
      </div>`;
    const done = (val) => { document.removeEventListener('keydown', onKey); host.remove(); resolve(val); };
    const onKey = (e) => { if (e.key === 'Escape') done(false); if (e.key === 'Enter') done(true); };
    host.addEventListener('click', e => {
      if (e.target === host) return done(false);
      const act = e.target.closest('[data-act]')?.dataset.act;
      if (act === 'ok') done(true);
      if (act === 'cancel') done(false);
    });
    document.addEventListener('keydown', onKey);
    document.body.appendChild(host);
    host.querySelector('[data-act="ok"]').focus();
  });
}

function promptModal(opts = {}) {
  const {
    title = 'กรอกข้อมูล', label = '', message = '', placeholder = '',
    value = '', confirmText = 'ตกลง', cancelText = 'ยกเลิก', type = 'text',
  } = opts;
  return new Promise(resolve => {
    const host = document.createElement('div');
    host.className = 'modal-overlay';
    host.style.zIndex = '300';
    host.innerHTML = `
      <div class="modal" style="max-width:440px">
        <div class="modal-header">${_escapeHtml(title)}<span class="modal-close" data-act="cancel">×</span></div>
        <div class="modal-body">
          ${message ? `<div class="fs-13 text-muted mb-14" style="line-height:1.6">${_escapeHtml(message)}</div>` : ''}
          <div class="form-group">
            ${label ? `<label class="form-label">${_escapeHtml(label)}</label>` : ''}
            <input class="form-control" id="_pm-input" type="${type}" placeholder="${_escapeHtml(placeholder)}" value="${_escapeHtml(value)}">
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-ghost" data-act="cancel">${_escapeHtml(cancelText)}</button>
          <button class="btn btn-primary" data-act="ok">${_escapeHtml(confirmText)}</button>
        </div>
      </div>`;
    const input = host.querySelector('#_pm-input');
    const done = (val) => { document.removeEventListener('keydown', onKey); host.remove(); resolve(val); };
    const submit = () => { const v = input.value.trim(); done(v === '' ? null : v); };
    const onKey = (e) => { if (e.key === 'Escape') done(null); if (e.key === 'Enter') submit(); };
    host.addEventListener('click', e => {
      if (e.target === host) return done(null);
      const act = e.target.closest('[data-act]')?.dataset.act;
      if (act === 'ok') submit();
      if (act === 'cancel') done(null);
    });
    document.addEventListener('keydown', onKey);
    document.body.appendChild(host);
    input.focus(); input.select();
  });
}

function fmt(n) { return '฿' + Number(n).toLocaleString('th-TH', { minimumFractionDigits: 0 }); }
function fmtN(n) { return Number(n).toLocaleString('th-TH'); }
function thaiDate(d) {
  if (!d) return '-';
  const months = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
  const dt = new Date(d);
  return `${dt.getDate()} ${months[dt.getMonth()+1]} ${dt.getFullYear()+543}`;
}
function statusBadge(s) {
  const map = { pending:'badge-pending รออนุมัติ', approved:'badge-approved อนุมัติแล้ว',
                paid:'badge-paid จ่ายแล้ว', rejected:'badge-rejected ปฏิเสธ',
                draft:'badge-draft ร่าง', open:'badge-open เปิด',
                locked:'badge-locked ล็อค' };
  const [cls, label] = (map[s] || 'badge-draft -').split(' ');
  return `<span class="badge ${cls}">${label}</span>`;
}

const can = (...roles) => roles.includes(APP_CONFIG.role);

/* ── Navigation ───────────────────────────────────── */
const NAV = [
  { section: 'หลัก' },
  { id: 'dashboard',   icon: '📊', label: 'ภาพรวม',          roles: ['admin','director','curriculum','teacher','accounting'] },
  { section: 'การจัดการ' },
  { id: 'claims',      icon: '📋', label: 'จัดการใบเบิก',     roles: ['admin','director','curriculum','teacher'] },
  { id: 'rules',       icon: '⚙️', label: 'กำหนดเงื่อนไข',    roles: ['admin','director','curriculum'] },
  { id: 'periods',     icon: '📅', label: 'งวดการเบิก',       roles: ['admin','director','curriculum','accounting'] },
  { id: 'attendance',  icon: '✅', label: 'บันทึกปฏิบัติงาน', roles: ['admin','curriculum','teacher'] },
  { id: 'makeup',      icon: '🔄', label: 'สอนชดเชย/แทน',    roles: ['admin','director','curriculum','teacher'] },
  { section: 'รายงาน' },
  { id: 'reports',     icon: '📈', label: 'รายงานสรุป',       roles: ['admin','director','curriculum','accounting'] },
  { section: 'ระบบ' },
  { id: 'institution', icon: '🏫', label: 'ข้อมูลสถานศึกษา',  roles: ['admin','director'] },
  { id: 'users',       icon: '👥', label: 'จัดการผู้ใช้งาน',   roles: ['admin','director'] },
  { id: 'settings',   icon: '🔧', label: 'การตั้งค่าระบบ',     roles: ['admin'] },
];

let currentPage = '';

function buildNav() {
  const nav = $('nav-menu');
  nav.innerHTML = '';
  NAV.forEach(item => {
    if (item.section) {
      const el = document.createElement('div');
      el.className = 'nav-section'; el.textContent = item.section;
      nav.appendChild(el); return;
    }
    if (!item.roles.includes(APP_CONFIG.role)) return;
    const el = document.createElement('div');
    el.className = 'nav-item'; el.dataset.page = item.id;
    el.innerHTML = `<span class="nav-icon">${item.icon}</span><span class="nav-label">${item.label}</span>`;
    el.addEventListener('click', () => navigate(item.id));
    nav.appendChild(el);
  });
}

const pageTitles = {
  dashboard: 'ภาพรวมระบบ', claims: 'จัดการใบเบิก', rules: 'กำหนดเงื่อนไข',
  periods: 'งวดการเบิก', attendance: 'บันทึกปฏิบัติงาน', makeup: 'สอนชดเชย/แทน',
  reports: 'รายงานสรุป', institution: 'ข้อมูลสถานศึกษา', users: 'จัดการผู้ใช้งาน', settings: 'การตั้งค่าระบบ',
};

function navigate(page) {
  currentPage = page;
  $('page-title').textContent = pageTitles[page] || page;
  document.querySelectorAll('.nav-item').forEach(el => {
    el.classList.toggle('active', el.dataset.page === page);
  });
  const c = $('page-content');
  c.innerHTML = '<div style="text-align:center;padding:60px;color:var(--muted)">⏳ กำลังโหลด...</div>';
  c.style.animation = 'none'; c.offsetHeight; c.style.animation = '';
  pages[page]?.();
}

/* ── Sidebar toggle ───────────────────────────────── */
$('toggle-sidebar').addEventListener('click', () => {
  $('sidebar').classList.toggle('collapsed');
});

/* ── Theme ────────────────────────────────────────── */
function setTheme(t) {
  const app = $('app');
  if (t === 'system') t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  app.dataset.theme = t;
  document.querySelectorAll('.theme-btn').forEach(b => b.classList.toggle('active', b.dataset.theme === t));
  localStorage.setItem('tc_theme', t);
}
document.querySelectorAll('.theme-btn').forEach(b =>
  b.addEventListener('click', () => setTheme(b.dataset.theme))
);
setTheme(localStorage.getItem('tc_theme') || 'light');

/* ── Notifications ────────────────────────────────── */
async function loadNotifs() {
  const r = await api('/api/notifications.php');
  if (!r.success) return;
  $('notif-dot').style.display = r.data.unread > 0 ? 'block' : 'none';
  $('notif-list').innerHTML = r.data.notifications.map(n => `
    <div style="display:flex;gap:11px;padding:11px 16px;border-bottom:1px solid var(--border);cursor:pointer;background:${n.is_read?'transparent':'rgba(123,31,50,.04)'}">
      <div style="width:34px;height:34px;border-radius:9px;background:#7B1F3215;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0">${n.icon}</div>
      <div style="flex:1;min-width:0">
        <div style="font-size:12px;font-weight:${n.is_read?'400':'600'};line-height:1.4">${n.title}</div>
        <div style="font-size:11px;color:var(--muted);margin-top:2px">${thaiDate(n.created_at.split(' ')[0])}</div>
      </div>
      ${!n.is_read ? '<div style="width:7px;height:7px;border-radius:50%;background:#EF4444;margin-top:5px;flex-shrink:0"></div>' : ''}
    </div>
  `).join('') || '<div style="padding:20px;text-align:center;color:var(--muted);font-size:13px">ไม่มีการแจ้งเตือน</div>';
}

$('notif-btn').addEventListener('click', e => {
  e.stopPropagation();
  const dd = $('notif-dropdown');
  dd.style.display = dd.style.display === 'none' ? 'block' : 'none';
  if (dd.style.display === 'block') loadNotifs();
});
$('mark-all-read').addEventListener('click', async () => {
  await post('/api/notifications.php', { action: 'mark_all_read' });
  loadNotifs();
});
document.addEventListener('click', e => {
  if (!$('notif-root').contains(e.target)) $('notif-dropdown').style.display = 'none';
});

/* ══════════════════════════════════════════════════
   PAGES
══════════════════════════════════════════════════ */
const pages = {};

/* ── DASHBOARD ────────────────────────────────────── */
pages.dashboard = async () => {
  const r = await api('/api/dashboard.php');
  if (!r.success) { $('page-content').innerHTML = `<div class="card card-body" style="color:#EF4444">${r.message}</div>`; return; }
  const d = r.data;
  const prevAmt = d.stats.totalAmount * 0.88;

  $('page-content').innerHTML = `
    <div class="stats-grid anim-fadeup">
      <div class="stat-card">
        <div class="stat-label">ยอดเบิกรวมภาคเรียน 💰</div>
        <div class="stat-value text-accent">${fmt(d.stats.totalAmount)}</div>
        <div class="stat-change text-success">▲ ${Math.round((d.stats.totalAmount/Math.max(1,prevAmt)-1)*100)}% จากภาคก่อน</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">ครูที่ดำเนินการ ⏳</div>
        <div class="stat-value">${fmtN(d.stats.teacherCount)} คน</div>
        <div class="stat-change text-warning">${d.stats.docPending} รายการรออนุมัติ</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">งวดที่เสร็จสิ้น ✅</div>
        <div class="stat-value">${d.stats.periodPaid} / ${d.stats.periodTotal}</div>
        <div class="stat-change text-muted">งวด</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">ใบเบิกทั้งหมด 📄</div>
        <div class="stat-value">${fmtN(d.stats.docTotal)} ใบ</div>
        <div class="stat-change text-warning">รออนุมัติ ${d.stats.docPending} ใบ</div>
      </div>
    </div>

    <div class="grid-2 mb-20">
      <div class="card">
        <div class="card-header"><span>ยอดเบิกค่าสอนรายสัปดาห์</span><span class="fs-11 text-muted">ภาคเรียนที่ 1/2568</span></div>
        <div class="card-body"><canvas id="weeklyChart" height="120"></canvas></div>
      </div>
      <div class="card">
        <div class="card-header"><span>สถานะการดำเนินการ</span><span class="fs-11 text-muted">รวมทุกแผนก</span></div>
        <div class="card-body" style="display:flex;align-items:center;gap:20px">
          <canvas id="statusChart" width="140" height="140" style="flex-shrink:0"></canvas>
          <div id="status-legend" style="font-size:12px;display:flex;flex-direction:column;gap:8px"></div>
        </div>
      </div>
    </div>

    <div class="grid-3 mb-20">
      <div class="card" style="display:flex;gap:14px;align-items:center;padding:16px 18px">
        <div style="width:44px;height:44px;border-radius:10px;background:#22C55E15;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0">📚</div>
        <div class="flex-1"><div class="fs-11 text-muted mb-4">สอนชดเชย</div><div style="font-size:22px;font-weight:700">${d.mkSummary.total}</div></div>
        <div class="text-right"><div class="fs-11 text-muted mb-4">รออนุมัติ</div><div style="font-size:16px;font-weight:700;color:#F59E0B">${d.mkSummary.pending}</div></div>
      </div>
      <div class="card" style="display:flex;gap:14px;align-items:center;padding:16px 18px">
        <div style="width:44px;height:44px;border-radius:10px;background:#3B82F615;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0">🔄</div>
        <div class="flex-1"><div class="fs-11 text-muted mb-4">สอนแทน</div><div style="font-size:22px;font-weight:700">${d.sbSummary.total}</div></div>
        <div class="text-right"><div class="fs-11 text-muted mb-4">รออนุมัติ</div><div style="font-size:16px;font-weight:700;color:#F59E0B">${d.sbSummary.pending}</div></div>
      </div>
      <div class="card" style="display:flex;gap:14px;align-items:center;padding:16px 18px">
        <div style="width:44px;height:44px;border-radius:10px;background:#7B1F3215;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0">📅</div>
        <div class="flex-1"><div class="fs-11 text-muted mb-4">งวดถัดไป</div><div style="font-size:14px;font-weight:700">${d.openPeriods[0] ? 'งวดที่ '+d.openPeriods[0].period_num : '-'}</div></div>
        <div class="text-right"><div class="fs-11 text-muted mb-4">วันเปิด</div><div style="font-size:12px;font-weight:600">${d.openPeriods[0] ? thaiDate(d.openPeriods[0].start_date) : '-'}</div></div>
      </div>
    </div>

    <div class="card mb-20">
      <div class="card-header">รายการเบิกล่าสุด <button class="btn btn-outline btn-sm" onclick="navigate('claims')">ดูทั้งหมด →</button></div>
      <div class="tbl-wrap">
        <table>
          <thead><tr>
            <th>ชื่อ-นามสกุล</th><th>แผนก</th><th>สัปดาห์ที่</th>
            <th class="text-center">คาบเกิน</th><th class="text-right">ยอดเบิก</th>
            <th class="text-center">สถานะ</th><th></th>
          </tr></thead>
          <tbody>
            ${d.recentClaims.map(r => `
              <tr>
                <td class="fw-600 text-accent" style="cursor:pointer" onclick="navigate('claims')">${r.full_name}</td>
                <td class="text-muted fs-12">${r.dept_name}</td>
                <td>สป.${r.week_num}</td>
                <td class="text-center">${r.over_periods} คาบ</td>
                <td class="text-right fw-700 text-accent">${fmt(r.amount)}</td>
                <td class="text-center">${statusBadge(r.status)}</td>
                <td class="text-center"><button class="btn btn-ghost btn-sm" onclick="navigate('claims')">ดู</button></td>
              </tr>`).join('')}
          </tbody>
        </table>
      </div>
    </div>
  `;

  // Weekly bar chart
  new Chart($('weeklyChart'), {
    type: 'bar',
    data: {
      labels: d.weeklyChart.labels.map(w => `สป.${w}`),
      datasets: [{ data: d.weeklyChart.amounts, backgroundColor: '#7B1F32cc', borderRadius: 5 }],
    },
    options: { plugins: { legend: { display: false } }, scales: { y: { ticks: { callback: v => fmt(v) } } } },
  });

  // Status donut
  const sc = d.statusChart;
  const labels = ['พิมพ์แล้ว','รออนุมัติ','ยังไม่ดำเนินการ'];
  const vals   = [sc.paid + sc.approved, sc.pending, sc.draft + sc.rejected];
  const colors = ['#22C55E','#F59E0B','#9CA3AF'];
  new Chart($('statusChart'), {
    type: 'doughnut',
    data: { labels, datasets: [{ data: vals, backgroundColor: colors, borderWidth: 2 }] },
    options: { cutout: '65%', plugins: { legend: { display: false } } },
  });
  const total = vals.reduce((a,b)=>a+b,0)||1;
  $('status-legend').innerHTML = labels.map((l,i) => `
    <div class="d-flex align-center gap-8">
      <div style="width:12px;height:12px;border-radius:3px;background:${colors[i]};flex-shrink:0"></div>
      <span>${l}</span>
      <span class="fw-600" style="margin-left:auto">${Math.round(vals[i]/total*100)}%</span>
    </div>`).join('');
};

/* ── CLAIMS ───────────────────────────────────────── */
pages.claims = async () => {
  const [claimsR, teachersR] = await Promise.all([
    api('/api/claims.php'),
    api('/api/teachers.php'),
  ]);
  const claims   = claimsR.data?.claims   || [];
  const teachers = teachersR.data?.teachers || [];
  const depts    = teachersR.data?.departments || [];

  let filterTeacher = '', filterStatus = '', filterDept = '';

  const render = () => {
    let rows = claims;
    if (filterTeacher) rows = rows.filter(r => String(r.teacher_id) === filterTeacher);
    if (filterStatus)  rows = rows.filter(r => r.status === filterStatus);
    if (filterDept)    rows = rows.filter(r => r.dept_name === filterDept);

    $('claims-tbody').innerHTML = rows.map(r => `
      <tr>
        <td class="fw-600">${r.full_name}</td>
        <td class="text-muted fs-12">${r.dept_name}</td>
        <td>สป.${r.week_num}<div class="fs-11 text-muted">${thaiDate(r.week_start_date)}</div></td>
        <td>${r.subject || '-'}<div class="fs-11 text-muted">${r.group_name}</div></td>
        <td class="text-center"><span class="badge badge-draft" style="background:#7B1F3215;color:#7B1F32">${r.level === 'pvch' ? 'ปวช.' : r.level === 'pvs' ? 'ปวส.' : 'ป.ตรี'}</span></td>
        <td class="text-center">${r.total_periods}</td>
        <td class="text-center fw-600 text-accent">${r.over_periods}</td>
        <td class="text-right fw-700 text-accent">${fmt(r.amount)}</td>
        <td class="text-center">${statusBadge(r.status)}</td>
        <td class="text-center">
          <div class="d-flex gap-8 justify-content:center">
            ${can('admin','director') && r.status === 'pending' ? `<button class="btn btn-success btn-sm" onclick="approveClaim(${r.id})">อนุมัติ</button>` : ''}
            ${can('admin','director') && r.status === 'pending' ? `<button class="btn btn-danger btn-sm" onclick="rejectClaim(${r.id})">ปฏิเสธ</button>` : ''}
            ${can('admin','curriculum') ? `<button class="btn btn-ghost btn-sm" onclick="deleteClaim(${r.id})">ลบ</button>` : ''}
          </div>
        </td>
      </tr>`).join('') || `<tr><td colspan="10" style="text-align:center;padding:40px;color:var(--muted)">ไม่พบข้อมูล</td></tr>`;
  };

  $('page-content').innerHTML = `
    <div class="anim-fadeup">
      <div class="tabs mb-18">
        <div class="tab active" id="tab-normal" onclick="switchClaimTab('normal',this)">📅 ตารางสอนปกติ</div>
        <div class="tab" id="tab-block" onclick="switchClaimTab('block',this)">⚡ Block Courses</div>
      </div>
      <div class="d-flex gap-10 mb-18 align-center" style="flex-wrap:wrap">
        <select class="form-control" style="width:200px" onchange="filterTeacher=this.value;render_claims()">
          <option value="">ครูทุกคน</option>
          ${teachers.map(t=>`<option value="${t.id}">${t.full_name}</option>`).join('')}
        </select>
        <select class="form-control" style="width:160px" onchange="filterStatus=this.value;render_claims()">
          <option value="">ทุกสถานะ</option>
          <option value="pending">รออนุมัติ</option>
          <option value="approved">อนุมัติแล้ว</option>
          <option value="paid">จ่ายแล้ว</option>
          <option value="rejected">ปฏิเสธ</option>
        </select>
        <select class="form-control" style="width:180px" onchange="filterDept=this.value;render_claims()">
          <option value="">ทุกแผนก</option>
          ${depts.map(d=>`<option value="${d.name}">${d.name}</option>`).join('')}
        </select>
        <div class="flex-1"></div>
        ${can('admin','curriculum','teacher') ? `<button class="btn btn-primary" onclick="openAddClaim()">+ เพิ่มใบเบิก</button>` : ''}
      </div>
      <div class="card">
        <div class="tbl-wrap">
          <table>
            <thead><tr>
              <th>ชื่อครู</th><th>แผนก</th><th>สัปดาห์</th><th>วิชา/กลุ่ม</th>
              <th class="text-center">ระดับ</th><th class="text-center">คาบรวม</th>
              <th class="text-center">คาบเกิน</th><th class="text-right">ยอดเบิก</th>
              <th class="text-center">สถานะ</th><th></th>
            </tr></thead>
            <tbody id="claims-tbody"></tbody>
          </table>
        </div>
      </div>
    </div>`;

  window.render_claims = render;
  window.filterTeacher = ''; window.filterStatus = ''; window.filterDept = '';
  render();

  window.approveClaim = async (id) => {
    if (!await confirmModal({ title:'อนุมัติใบเบิก', message:'ยืนยันการอนุมัติใบเบิกนี้?', confirmText:'อนุมัติ', icon:'✅' })) return;
    const r = await post('/api/claims.php', { action: 'approve', id });
    toast(r.message, r.success ? 'success' : 'error');
    if (r.success) pages.claims();
  };
  window.rejectClaim = async (id) => {
    const note = await promptModal({ title:'ปฏิเสธใบเบิก', label:'เหตุผลที่ปฏิเสธ', placeholder:'ระบุเหตุผล...', confirmText:'ปฏิเสธ' });
    if (note === null) return;
    const r = await post('/api/claims.php', { action: 'reject', id, note });
    toast(r.message, r.success ? 'success' : 'error');
    if (r.success) pages.claims();
  };
  window.deleteClaim = async (id) => {
    if (!await confirmModal({ title:'ลบใบเบิก', message:'ยืนยันการลบใบเบิกนี้?', confirmText:'ลบ', danger:true })) return;
    const r = await post('/api/claims.php', { action: 'delete', id });
    toast(r.message, r.success ? 'success' : 'error');
    if (r.success) pages.claims();
  };
  window.switchClaimTab = (mode, el) => {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
  };
  window.openAddClaim = () => openClaimModal(teachers);
};

function openClaimModal(teachers) {
  showModal(`
    <div class="modal-overlay">
      <div class="modal" style="max-width:580px">
        <div class="modal-header">เพิ่มใบเบิกค่าตอบแทนการสอน
          <span class="modal-close" onclick="closeModal()">×</span></div>
        <div class="modal-body">
          <div class="grid-2">
            <div class="form-group">
              <label class="form-label">ครูผู้สอน</label>
              <select class="form-control" id="m-teacher">
                ${teachers.map(t=>`<option value="${t.id}">${t.full_name} (${t.dept_short})</option>`).join('')}
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">ระดับชั้น</label>
              <select class="form-control" id="m-level" onchange="calcPreview()">
                <option value="pvch">ปวช.</option>
                <option value="pvs">ปวส.</option>
                <option value="degree">ปริญญาตรี</option>
              </select>
            </div>
          </div>
          <div class="grid-2">
            <div class="form-group">
              <label class="form-label">วิชา</label>
              <input class="form-control" id="m-subject" placeholder="ชื่อวิชา">
            </div>
            <div class="form-group">
              <label class="form-label">กลุ่มเรียน</label>
              <input class="form-control" id="m-group" placeholder="เช่น ชฟ.1/1">
            </div>
          </div>
          <div class="grid-2">
            <div class="form-group">
              <label class="form-label">วันเริ่มต้นสัปดาห์</label>
              <input class="form-control" type="date" id="m-wstart">
            </div>
            <div class="form-group">
              <label class="form-label">วันสิ้นสุดสัปดาห์</label>
              <input class="form-control" type="date" id="m-wend">
            </div>
          </div>
          <div class="grid-2">
            <div class="form-group">
              <label class="form-label">คาบสอนรวม/สัปดาห์</label>
              <input class="form-control" type="number" id="m-periods" value="20" min="1" oninput="calcPreview()">
            </div>
            <div class="form-group">
              <label class="form-label">จำนวนนักเรียน</label>
              <input class="form-control" type="number" id="m-students" value="25" min="1" oninput="calcPreview()">
            </div>
          </div>
          <div id="calc-preview" style="background:#7B1F3208;border:1px solid #7B1F3230;border-radius:9px;padding:14px 16px;margin-bottom:8px">
            <div class="fs-11 text-muted mb-8">ผลการคำนวณ (ประมาณ)</div>
            <div class="d-flex gap-14">
              <div><div class="fs-11 text-muted">คาบเกิน</div><div id="prev-over" class="fw-700 text-accent" style="font-size:18px">-</div></div>
              <div><div class="fs-11 text-muted">อัตรา/คาบ</div><div id="prev-rate" class="fw-700" style="font-size:18px">-</div></div>
              <div><div class="fs-11 text-muted">ยอดเบิก</div><div id="prev-amt" class="fw-700 text-accent" style="font-size:22px">-</div></div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-ghost" onclick="closeModal()">ยกเลิก</button>
          <button class="btn btn-primary" onclick="submitClaim()">💾 บันทึกใบเบิก</button>
        </div>
      </div>
    </div>`);

  // set default dates (current week Mon-Fri)
  const now = new Date();
  const day = now.getDay() || 7;
  const mon = new Date(now); mon.setDate(now.getDate() - day + 1);
  const fri = new Date(mon); fri.setDate(mon.getDate() + 4);
  $('m-wstart').value = mon.toISOString().slice(0,10);
  $('m-wend').value   = fri.toISOString().slice(0,10);

  window.calcPreview = async () => {
    const r = await post('/api/claims.php', {
      action: 'calculate',
      total_periods: +($('m-periods')?.value||0),
      student_count: +($('m-students')?.value||0),
      level: $('m-level')?.value || 'pvch',
    });
    if (r.success) {
      $('prev-over').textContent  = r.data.over_periods + ' คาบ';
      $('prev-rate').textContent  = fmt(r.data.rate);
      $('prev-amt').textContent   = fmt(r.data.amount);
    }
  };
  calcPreview();

  window.submitClaim = async () => {
    const r = await post('/api/claims.php', {
      action:         'create',
      teacher_id:     $('m-teacher').value,
      level:          $('m-level').value,
      subject:        $('m-subject').value,
      group_name:     $('m-group').value,
      week_start_date:$('m-wstart').value,
      week_end_date:  $('m-wend').value,
      total_periods:  +$('m-periods').value,
      student_count:  +$('m-students').value,
    });
    toast(r.message, r.success ? 'success' : 'error');
    if (r.success) { closeModal(); pages.claims(); }
  };
}

/* ── RULES ────────────────────────────────────────── */
pages.rules = async () => {
  const r = await api('/api/rules.php');
  if (!r.success) return;
  const { rules, rates } = r.data;

  $('page-content').innerHTML = `
    <div class="anim-fadeup" style="max-width:860px">
      <!-- Rates -->
      <div class="card mb-18">
        <div class="card-header">💰 อัตราค่าสอนต่อชั่วโมง</div>
        <div class="card-body grid-3">
          ${rates.map((rt,i) => `
            <div style="border:2px solid ${rt.is_enabled?'#7B1F32':'var(--border)'};border-radius:9px;padding:14px 16px">
              <div class="d-flex justify-between align-center mb-14">
                <div><div class="fw-600">${rt.level_name}</div><div class="fs-11 text-muted">${rt.level==='pvch'?'ระดับปวช.':rt.level==='pvs'?'ระดับปวส.':'ระดับอุดมศึกษา'}</div></div>
                <div class="toggle ${rt.is_enabled?'on':'off'}" id="toggle-rate-${i}" onclick="toggleRate(${i})">
                  <div class="toggle-knob"></div>
                </div>
              </div>
              <div class="input-group">
                <span class="input-addon">฿</span>
                <input class="form-control" type="number" id="rate-${i}" value="${rt.rate_per_hour}" ${rt.is_enabled?'':'disabled'}>
                <span class="input-addon">/ ชม.</span>
              </div>
            </div>`).join('')}
        </div>
      </div>

      <!-- Workload -->
      <div class="card mb-18">
        <div class="card-header">📚 ภาระงานสอนและโควตาการเบิก</div>
        <div class="card-body grid-2">
          <div class="form-group">
            <label class="form-label">คาบสอนภาระงานปกติ (คาบ/สัปดาห์)</label>
            <div class="input-group"><input class="form-control" type="number" id="normal-load" value="${rules?.normal_load||18}"><span class="input-addon">คาบ</span></div>
            <div class="form-hint">คาบที่ถือว่าเป็นภาระงานปกติ ไม่นับเป็นค่าตอบแทน</div>
          </div>
          <div class="form-group">
            <label class="form-label">คาบสอนสูงสุดที่เบิกได้ (คาบ/สัปดาห์)</label>
            <div class="input-group"><input class="form-control" type="number" id="max-claim" value="${rules?.max_claimable||10}"><span class="input-addon">คาบ</span></div>
            <div class="form-hint">โควตาสูงสุดต่อสัปดาห์ เกินกว่านี้จะถูกตัดอัตโนมัติ</div>
          </div>
        </div>
      </div>

      <!-- Holiday rule -->
      <div class="card mb-18">
        <div class="card-header">📅 กฎการคำนวณในสัปดาห์ที่มีวันหยุด</div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:8px">
          ${[
            {v:'proportional',t:'คำนวณตามสัดส่วน',d:'ลดจำนวนคาบภาระงานตามจำนวนวันหยุด เช่น หยุด 1 วัน → ภาระงาน = (18×4/5)'},
            {v:'skip',t:'ข้ามสัปดาห์ที่มีวันหยุด',d:'ไม่นับสัปดาห์ที่มีวันหยุดราชการเข้าการคำนวณ'},
            {v:'full',t:'คำนวณเต็มจำนวน',d:'ใช้ภาระงานปกติเต็มจำนวน แม้จะมีวันหยุด'},
          ].map(o => `
            <div onclick="document.getElementById('hr-${o.v}').checked=true" style="display:flex;align-items:flex-start;gap:12px;padding:12px 14px;border-radius:8px;border:2px solid ${(rules?.holiday_rule||'proportional')===o.v?'#7B1F32':'var(--border)'};cursor:pointer;transition:all .2s">
              <input type="radio" name="holiday-rule" id="hr-${o.v}" value="${o.v}" ${(rules?.holiday_rule||'proportional')===o.v?'checked':''} style="margin-top:2px;accent-color:#7B1F32">
              <div><div class="fw-600 fs-13">${o.t}</div><div class="fs-11 text-muted mt-4">${o.d}</div></div>
            </div>`).join('')}
        </div>
      </div>

      <!-- Students -->
      <div class="card mb-18">
        <div class="card-header">👨‍🎓 เงื่อนไขจำนวนนักเรียน</div>
        <div class="card-body grid-2">
          <div class="form-group">
            <label class="form-label">จำนวนนักเรียนขั้นต่ำ (รับค่าตอบแทนเต็มจำนวน)</label>
            <div class="input-group"><input class="form-control" type="number" id="min-students" value="${rules?.min_students||25}"><span class="input-addon">คน</span></div>
          </div>
          <div class="form-group">
            <label class="form-label">อัตราค่าตอบแทนรายหัว (กรณีไม่ถึงเกณฑ์)</label>
            <div class="input-group"><span class="input-addon">฿</span><input class="form-control" type="number" id="per-head" value="${rules?.per_head_rate||20}"><span class="input-addon">/ หัว / ชม.</span></div>
          </div>
        </div>
      </div>

      <!-- Live preview -->
      <div class="card mb-18" style="border:2px solid #7B1F3240">
        <div class="card-header" style="background:#7B1F3208">
          🧮 ตัวอย่างการคำนวณ (Live Preview)
          <span class="badge badge-approved">● Live</span>
        </div>
        <div class="card-body">
          <div class="grid-4 mb-14" style="background:var(--thead);border-radius:9px;padding:14px;border:1px solid var(--border)">
            <div><label class="form-label">คาบสอนจริง/สัปดาห์</label><input class="form-control" type="number" id="sim-periods" value="22" min="1" max="50" oninput="runSim()"></div>
            <div><label class="form-label">ระดับชั้น</label><select class="form-control" id="sim-level" onchange="runSim()"><option value="pvch">ปวช.</option><option value="pvs">ปวส.</option><option value="degree">ป.ตรี</option></select></div>
            <div><label class="form-label">จำนวนนักเรียน</label><input class="form-control" type="number" id="sim-students" value="28" min="1" oninput="runSim()"></div>
            <div><label class="form-label">วันหยุด</label><select class="form-control" id="sim-holiday" onchange="runSim()"><option value="0">ไม่มี</option><option value="1">1 วัน</option><option value="2">2 วัน</option></select></div>
          </div>
          <div id="sim-result" class="grid-4">
            <div style="text-align:center;padding:14px;border:1px solid var(--border);border-radius:9px">
              <div class="fs-11 text-muted mb-4">คาบสอนจริง</div>
              <div id="sr-actual" class="fw-700" style="font-size:20px">-</div>
            </div>
            <div style="text-align:center;padding:14px;border:1px solid var(--border);border-radius:9px">
              <div class="fs-11 text-muted mb-4">ภาระงานปกติ</div>
              <div id="sr-normal" class="fw-700" style="font-size:20px">-</div>
            </div>
            <div style="text-align:center;padding:14px;border:1px solid var(--border);border-radius:9px">
              <div class="fs-11 text-muted mb-4">คาบเกิน</div>
              <div id="sr-over" class="fw-700 text-accent" style="font-size:20px">-</div>
            </div>
            <div style="text-align:center;padding:14px;border:2px solid #7B1F3240;border-radius:9px;background:#7B1F3208">
              <div class="fs-11 text-muted mb-4">ยอดเบิก</div>
              <div id="sr-amt" class="fw-700 text-accent" style="font-size:22px">-</div>
            </div>
          </div>
        </div>
      </div>

      <div class="d-flex justify-between">
        <button class="btn btn-ghost">ยกเลิก</button>
        <button class="btn btn-primary" onclick="saveRules()">💾 บันทึกการตั้งค่า</button>
      </div>
    </div>`;

  // store rates data for toggle
  window._ratesData = rates;
  window.toggleRate = (i) => {
    const t = document.getElementById(`toggle-rate-${i}`);
    const inp = document.getElementById(`rate-${i}`);
    const on = t.classList.contains('off');
    t.className = `toggle ${on?'on':'off'}`;
    inp.disabled = !on;
  };

  window.runSim = async () => {
    const periods  = +($('sim-periods')?.value||0);
    const level    = $('sim-level')?.value||'pvch';
    const students = +($('sim-students')?.value||0);
    const holiday  = +($('sim-holiday')?.value||0);
    const nl  = +($('normal-load')?.value||18);
    const mcl = +($('max-claim')?.value||10);
    const ms  = +($('min-students')?.value||25);
    const phr = +($('per-head')?.value||20);
    const rateEl = rates.find(r=>r.level===level);
    const rate   = rateEl?.rate_per_hour || 60;
    const adjNormal = holiday > 0 ? Math.round(nl*(5-holiday)/5) : nl;
    const over = Math.min(Math.max(0,periods-adjNormal),mcl);
    const amt  = over>0 ? (students>=ms ? over*rate : over*students*phr) : 0;
    if($('sr-actual'))  $('sr-actual').textContent  = periods+' คาบ';
    if($('sr-normal'))  $('sr-normal').textContent  = adjNormal+' คาบ';
    if($('sr-over'))    $('sr-over').textContent    = over+' คาบ';
    if($('sr-amt'))     $('sr-amt').textContent     = fmt(amt);
  };
  window.runSim();

  window.saveRules = async () => {
    const ratesPayload = (window._ratesData||[]).map((rt,i) => ({
      level: rt.level, level_name: rt.level_name,
      rate_per_hour: +($(`rate-${i}`)?.value||rt.rate_per_hour),
      is_enabled: $(`toggle-rate-${i}`)?.classList.contains('on') ? 1 : 0,
    }));
    const res = await post('/api/rules.php', {
      normal_load:  +($('normal-load')?.value||18),
      max_claimable:+($('max-claim')?.value||10),
      min_students: +($('min-students')?.value||25),
      per_head_rate:+($('per-head')?.value||20),
      holiday_rule: document.querySelector('input[name="holiday-rule"]:checked')?.value||'proportional',
      rates: ratesPayload,
    });
    toast(res.message, res.success ? 'success' : 'error');
  };
};

/* ── ATTENDANCE ───────────────────────────────────── */
pages.attendance = async () => {
  let week = 1;
  const attStatus = { present:'present', leave_personal:'leave_personal', leave_sick:'leave_sick', holiday:'holiday' };
  const statusCycle = ['present','leave_personal','leave_sick','holiday'];
  const statusLabel = { present:'ปกติ', leave_personal:'ลากิจ', leave_sick:'ลาป่วย', holiday:'หยุด' };
  const statusBg    = { present:'#22C55E22', leave_personal:'#F59E0B22', leave_sick:'#EF444422', holiday:'#3B82F622' };
  const statusIcon  = { present:'✅', leave_personal:'📋', leave_sick:'🏥', holiday:'🎌' };
  const statusColor = { present:'#22C55E', leave_personal:'#F59E0B', leave_sick:'#EF4444', holiday:'#3B82F6' };

  let currentData = null;
  let localUpdates = {}; // teacher_id -> date -> status

  const load = async (w) => {
    const r = await api(`/api/attendance.php?week=${w}`);
    if (!r.success) return;
    currentData = r.data;
    week = w;
    renderGrid();
  };

  const renderGrid = () => {
    const d = currentData;
    const dayNames = ['จ','อ','พ','พฤ','ศ'];
    $('page-content').innerHTML = `
      <div class="anim-fadeup">
        <div class="d-flex gap-10 mb-18 align-center" style="flex-wrap:wrap">
          <select class="form-control" style="width:180px" id="att-week-sel" onchange="attWeekChange(this.value)">
            ${Array.from({length:18},(_,i)=>`<option value="${i+1}" ${i+1===week?'selected':''}>สัปดาห์ที่ ${i+1}</option>`).join('')}
          </select>
          <div class="d-flex gap-10">
            ${Object.keys(statusLabel).map(s=>`
              <div class="d-flex align-center gap-8">
                <div style="width:12px;height:12px;border-radius:3px;background:${statusColor[s]}30;border:1px solid ${statusColor[s]};flex-shrink:0"></div>
                <span class="fs-11 text-muted">${statusLabel[s]}</span>
              </div>`).join('')}
          </div>
          <div class="flex-1"></div>
          <button class="btn btn-primary" onclick="saveAtt()">💾 บันทึก</button>
        </div>
        <div class="card">
          <div class="card-header">ตารางการลงชื่อปฏิบัติงาน — สัปดาห์ที่ ${week}</div>
          <div class="tbl-wrap">
            <table style="min-width:700px">
              <thead><tr>
                <th style="min-width:200px">ชื่อ-นามสกุล</th>
                ${d.dates.map((dt,i)=>`
                  <th style="text-align:center;min-width:80px;background:${d.holidays.includes(dt)?'#3B82F610':''}">
                    <div style="color:${d.holidays.includes(dt)?'#3B82F6':'var(--muted)'}">${dayNames[i]}</div>
                    <div style="font-size:10px;font-weight:400">${new Date(dt).getDate()}/${new Date(dt).getMonth()+1}</div>
                  </th>`).join('')}
                <th style="text-align:center">วันทำงาน</th>
                <th style="text-align:center">ผล</th>
              </tr></thead>
              <tbody>
                ${d.rows.map(row => {
                  let workDays = 0;
                  const dayCells = row.days.map(day => {
                    const key = `${row.teacher_id}_${day.date}`;
                    const st  = localUpdates[key] || day.status;
                    if (st === 'present') workDays++;
                    return `<td style="text-align:center;padding:6px 8px">
                      <div onclick="cycleAtt('${row.teacher_id}','${day.date}',this)"
                           data-status="${st}" data-tid="${row.teacher_id}" data-date="${day.date}"
                           style="width:44px;height:36px;border-radius:7px;background:${statusBg[st]||statusBg.present};display:flex;flex-direction:column;align-items:center;justify-content:center;cursor:pointer;margin:0 auto;border:1px solid ${statusColor[st]||statusColor.present}40;user-select:none;transition:all .15s">
                        <span style="font-size:14px">${statusIcon[st]||statusIcon.present}</span>
                        <span style="font-size:9px;color:${statusColor[st]||statusColor.present};font-weight:600">${statusLabel[st]||statusLabel.present}</span>
                      </div>
                    </td>`;
                  }).join('');
                  const eff = workDays >= 5 ? 'เต็ม' : workDays >= 3 ? 'บางส่วน' : 'ขาด';
                  const effColor = workDays >= 5 ? '#22C55E' : workDays >= 3 ? '#F59E0B' : '#EF4444';
                  return `<tr>
                    <td><div class="fw-600 fs-13">${row.full_name}</div><div class="fs-11 text-muted">${row.dept_name}</div></td>
                    ${dayCells}
                    <td style="text-align:center;font-size:13px;font-weight:700;color:${workDays<5?'#F59E0B':'var(--text)'}">${workDays}/5</td>
                    <td style="text-align:center"><span class="badge" style="background:${effColor}20;color:${effColor}">${eff}</span></td>
                  </tr>`;
                }).join('')}
              </tbody>
            </table>
          </div>
          <div style="padding:12px 18px;border-top:1px solid var(--border);font-size:12px;color:var(--muted)">
            💡 คลิกที่ช่องเพื่อเปลี่ยนสถานะ: <strong>ปกติ → ลากิจ → ลาป่วย → วันหยุดราชการ</strong>
          </div>
        </div>
      </div>`;

    window.cycleAtt = (tid, date, el) => {
      const key = `${tid}_${date}`;
      const cur = el.dataset.status;
      const idx = statusCycle.indexOf(cur);
      const nxt = statusCycle[(idx+1)%statusCycle.length];
      localUpdates[key] = nxt;
      el.dataset.status = nxt;
      el.style.background = statusBg[nxt];
      el.style.borderColor = statusColor[nxt]+'40';
      el.children[0].textContent = statusIcon[nxt];
      el.children[1].style.color = statusColor[nxt];
      el.children[1].textContent = statusLabel[nxt];
    };

    window.saveAtt = async () => {
      const updates = Object.entries(localUpdates).map(([k,status])=>{
        const [tid,date] = k.split('_');
        return { teacher_id: +tid, date, status };
      });
      const r = await post('/api/attendance.php', { action:'save', week, updates });
      toast(r.message, r.success?'success':'error');
      if (r.success) { localUpdates = {}; }
    };

    window.attWeekChange = (w) => load(+w);
  };

  $('page-content').innerHTML = '<div style="text-align:center;padding:40px;color:var(--muted)">⏳ กำลังโหลด...</div>';
  await load(1);
};

/* ── PERIODS ──────────────────────────────────────── */
pages.periods = async () => {
  const r = await api('/api/periods.php');
  if (!r.success) return;
  const { periods } = r.data;

  $('page-content').innerHTML = `
    <div class="anim-fadeup">
      <div class="d-flex justify-between mb-18">
        <div class="text-muted fs-13">กำหนดงวดการเบิกค่าตอบแทนประจำภาคเรียน</div>
        <div class="d-flex gap-8">
          ${can('admin','curriculum') ? `<button class="btn btn-primary" onclick="openAddPeriod()">+ สร้างงวดใหม่</button>` : ''}
        </div>
      </div>
      <div class="grid-3">
        ${periods.map(p => {
          const prog = p.doc_count > 0 ? Math.round(p.submitted_count/p.doc_count*100) : 0;
          const hdrBg = p.status === 'paid' ? '#22C55E' : p.status === 'locked' ? '#F59E0B' : '#7B1F32';
          return `
          <div class="card" style="border:2px solid ${p.status==='paid'?'#22C55E30':p.status==='locked'?'#F59E0B30':'var(--border)'}">
            <div style="padding:13px 16px;background:${hdrBg};display:flex;justify-content:space-between;align-items:center">
              <div>
                <div style="font-weight:700;font-size:14px;color:#fff">งวดที่ ${p.period_num}</div>
                <div style="font-size:11px;color:rgba(255,255,255,.7)">สัปดาห์ที่ ${p.week_start}–${p.week_end}</div>
              </div>
              ${statusBadge(p.status)}
            </div>
            <div style="padding:14px 16px">
              <div class="grid-2 mb-14">
                <div style="background:var(--thead);border-radius:7px;padding:9px 12px;border:1px solid var(--border)">
                  <div class="fs-11 text-muted mb-4">วันเริ่มต้น</div>
                  <div class="fw-600 fs-12">${thaiDate(p.start_date)}</div>
                </div>
                <div style="background:var(--thead);border-radius:7px;padding:9px 12px;border:1px solid var(--border)">
                  <div class="fs-11 text-muted mb-4">วันสิ้นสุด</div>
                  <div class="fw-600 fs-12">${thaiDate(p.end_date)}</div>
                </div>
              </div>
              <div class="d-flex justify-between mb-4 fs-12"><span class="text-muted">ใบเบิกที่สร้าง</span><span class="fw-600">${p.doc_count} ใบ</span></div>
              <div class="d-flex justify-between mb-14 fs-12"><span class="text-muted">ยอดเบิกรวม</span><span class="fw-700 text-accent">${fmt(p.total_amount)}</span></div>
              <div class="mb-14">
                <div class="d-flex justify-between mb-4 fs-11 text-muted"><span>ความคืบหน้า</span><span>${prog}%</span></div>
                <div class="progress-track"><div class="progress-bar" style="width:${prog}%;background:${p.status==='paid'?'#22C55E':'#7B1F32'}"></div></div>
              </div>
              <div class="d-flex gap-8">
                <button class="btn btn-outline btn-sm flex-1" onclick="navigate('claims')">ดูรายการ</button>
                ${can('admin','curriculum','director') ? `<button class="btn btn-ghost btn-sm flex-1" onclick="togglePeriodLock(${p.id},'${p.status}')">${p.status==='open'?'🔒 ล็อค':'🔓 เปิด'}</button>` : ''}
                ${can('admin','accounting') && p.status==='locked' ? `<button class="btn btn-success btn-sm" onclick="markPaid(${p.id})">💰</button>` : ''}
              </div>
            </div>
          </div>`;
        }).join('')}
      </div>
    </div>`;

  window.togglePeriodLock = async (id, status) => {
    const r = await post('/api/periods.php', { action:'toggle_lock', id });
    toast(r.message, r.success?'success':'error');
    if (r.success) pages.periods();
  };
  window.markPaid = async (id) => {
    if (!await confirmModal({ title:'บันทึกการจ่ายเงิน', message:'ยืนยันการบันทึกการจ่ายเงินงวดนี้?', confirmText:'บันทึกการจ่าย', icon:'💰' })) return;
    const r = await post('/api/periods.php', { action:'mark_paid', id });
    toast(r.message, r.success?'success':'error');
    if (r.success) pages.periods();
  };
  window.openAddPeriod = () => {
    showModal(`
      <div class="modal-overlay"><div class="modal">
        <div class="modal-header">สร้างงวดใหม่<span class="modal-close" onclick="closeModal()">×</span></div>
        <div class="modal-body">
          <div class="grid-2">
            <div class="form-group"><label class="form-label">สัปดาห์เริ่ม</label><input class="form-control" type="number" id="pw-start" value="1" min="1" max="18"></div>
            <div class="form-group"><label class="form-label">สัปดาห์สิ้นสุด</label><input class="form-control" type="number" id="pw-end" value="4" min="1" max="18"></div>
          </div>
          <div class="grid-2">
            <div class="form-group"><label class="form-label">วันที่เริ่ม</label><input class="form-control" type="date" id="pd-start"></div>
            <div class="form-group"><label class="form-label">วันที่สิ้นสุด</label><input class="form-control" type="date" id="pd-end"></div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-ghost" onclick="closeModal()">ยกเลิก</button>
          <button class="btn btn-primary" onclick="submitPeriod()">สร้างงวด</button>
        </div>
      </div></div>`);
    window.submitPeriod = async () => {
      const r = await post('/api/periods.php',{
        action:'create', week_start:+$('pw-start').value,
        week_end:+$('pw-end').value, start_date:$('pd-start').value, end_date:$('pd-end').value
      });
      toast(r.message, r.success?'success':'error');
      if (r.success) { closeModal(); pages.periods(); }
    };
  };
};

/* ── MAKEUP ───────────────────────────────────────── */
pages.makeup = async () => {
  const [mkR, teachersR, reasonsR] = await Promise.all([
    api('/api/makeup.php?type=makeup'),
    api('/api/teachers.php'),
    api('/api/settings.php?section=makeup_reasons'),
  ]);
  const makeup   = mkR.data?.records    || [];
  const teachers = teachersR.data?.teachers || [];
  const allReasons = reasonsR.data?.reasons || [
    { code:'holiday',  label:'วันหยุดราชการ',            icon:'🎌', color:'#3B82F6', bg_color:'#3B82F620', is_active:1 },
    { code:'personal', label:'ลากิจ',                    icon:'📋', color:'#F59E0B', bg_color:'#F59E0B20', is_active:1 },
    { code:'official', label:'ปฏิบัติราชการนอกสถานที่', icon:'✈️', color:'#8B5CF6', bg_color:'#8B5CF620', is_active:1 },
    { code:'sick',     label:'ลาป่วย',                   icon:'🏥', color:'#EF4444', bg_color:'#EF444420', is_active:1 },
  ];
  let activeTab  = 'makeup';
  let subRecords = [];

  const loadSub = async () => {
    const r = await api('/api/makeup.php?type=substitute');
    subRecords = r.data?.records || [];
  };
  await loadSub();

  const reasonLabel = {};
  const reasonBg    = {};
  const reasonColor = {};
  allReasons.forEach(r => {
    reasonLabel[r.code] = r.icon + ' ' + r.label;
    reasonBg[r.code]    = r.bg_color;
    reasonColor[r.code] = r.color;
  });

  const render = () => {
    const isMakeup = activeTab === 'makeup';
    $('page-content').innerHTML = `
      <div class="anim-fadeup">
        <div class="tabs">${['makeup','substitute'].map(t=>`
          <div class="tab ${activeTab===t?'active':''}" onclick="mkTab('${t}')">${t==='makeup'?'📚 การสอนชดเชย':'🔄 การแต่งตั้งสอนแทน'}</div>`).join('')}
        </div>
        ${isMakeup ? `
          <div class="d-flex justify-between mb-14 align-center" style="flex-wrap:wrap;gap:10px">
            <div class="d-flex gap-8" style="flex-wrap:wrap">
              ${allReasons.filter(r=>r.is_active).map(r=>`<span class="badge" style="background:${r.bg_color};color:${r.color}">${r.icon} ${r.label}</span>`).join('')}
            </div>
            ${can('admin','curriculum','teacher')?`<button class="btn btn-primary" onclick="openAddMakeup()">+ เพิ่มรายการสอนชดเชย</button>`:''}
          </div>
          <div class="card"><div class="tbl-wrap"><table>
            <thead><tr>
              <th>ครูผู้สอน</th><th>วิชา/กลุ่ม</th><th class="text-center">เหตุผล</th>
              <th class="text-center">วันที่ขาด → สอนชดเชย</th><th class="text-center">เวลา/คาบ</th>
              <th class="text-center">สถานะ</th><th></th>
            </tr></thead>
            <tbody>
              ${makeup.map(m=>`<tr>
                <td><div class="fw-600">${m.full_name}</div><div class="fs-11 text-muted">${m.dept_name}</div></td>
                <td><div class="fs-12">${m.subject}</div><div class="fs-11 text-muted">${m.group_name}</div></td>
                <td class="text-center"><span class="badge" style="background:${reasonBg[m.reason]};color:${reasonColor[m.reason]}">${reasonLabel[m.reason]||m.reason}</span></td>
                <td class="text-center">
                  <div class="fs-12 text-muted">${thaiDate(m.missed_date)}</div>
                  <div class="fs-12 fw-600" style="color:#22C55E">→ ${thaiDate(m.makeup_date)}</div>
                </td>
                <td class="text-center"><div class="fs-12">${m.start_time?.slice(0,5)||''} – ${m.end_time?.slice(0,5)||''}</div><div class="fw-700 text-accent">${m.periods} คาบ</div></td>
                <td class="text-center">${statusBadge(m.status)}</td>
                <td class="text-center"><div class="d-flex gap-8">
                  ${can('admin','director','curriculum') && m.status==='pending' ? `<button class="btn btn-success btn-sm" onclick="approveMk(${m.id})">อนุมัติ</button>` : ''}
                  <button class="btn btn-ghost btn-sm" onclick="deleteMk(${m.id})">ลบ</button>
                </div></td>
              </tr>`).join('')||`<tr><td colspan="7" style="text-align:center;padding:40px;color:var(--muted)">ไม่มีข้อมูล</td></tr>`}
            </tbody>
          </table></div></div>
        ` : `
          <div class="d-flex justify-between mb-14 align-center">
            <div class="fs-13 text-muted">บันทึกการแต่งตั้งครูสอนแทน <span class="badge badge-rejected">🏥 กรณีครูลาป่วยเท่านั้น</span></div>
            ${can('admin','curriculum','teacher')?`<button class="btn btn-primary" onclick="openAddSub()">+ เพิ่มรายการสอนแทน</button>`:''}
          </div>
          <div class="card"><div class="tbl-wrap"><table>
            <thead><tr>
              <th>ครูที่ลาป่วย</th><th>วิชา/กลุ่ม</th><th class="text-center">วันที่ขาด</th>
              <th>ครูสอนแทน</th><th class="text-center">คาบ</th>
              <th class="text-center">ผลต่อค่าตอบแทน</th><th class="text-center">สถานะ</th><th></th>
            </tr></thead>
            <tbody>
              ${subRecords.map(s=>`<tr>
                <td><div class="fw-600">${s.absent_name}</div><div class="fs-11" style="color:#EF4444">🏥 ลาป่วย · ${s.absent_dept}</div></td>
                <td><div class="fs-12">${s.subject}</div><div class="fs-11 text-muted">${s.group_name}</div></td>
                <td class="text-center fs-12 text-muted">${thaiDate(s.absent_date)}</td>
                <td><div class="fw-600">${s.sub_name}</div><div class="fs-11 text-muted">${s.sub_dept}</div></td>
                <td class="text-center fw-700 text-accent">${s.periods} คาบ</td>
                <td class="text-center"><span class="badge badge-approved">ครูสอนแทนได้รับ</span></td>
                <td class="text-center">${statusBadge(s.status)}</td>
                <td class="text-center"><div class="d-flex gap-8">
                  ${can('admin','director','curriculum') && s.status==='pending' ? `<button class="btn btn-success btn-sm" onclick="approveSb(${s.id})">อนุมัติ</button>` : ''}
                  <button class="btn btn-ghost btn-sm" onclick="deleteSb(${s.id})">ลบ</button>
                </div></td>
              </tr>`).join('')||`<tr><td colspan="8" style="text-align:center;padding:40px;color:var(--muted)">ไม่มีข้อมูล</td></tr>`}
            </tbody>
          </table></div></div>
        `}
      </div>`;
  };

  window.mkTab = async (t) => { activeTab = t; render(); };
  render();

  window.approveMk = async (id) => {
    const r = await post('/api/makeup.php', { action:'approve_makeup', id });
    toast(r.message, r.success?'success':'error');
    if (r.success) pages.makeup();
  };
  window.deleteMk = async (id) => {
    if (!await confirmModal({ title:'ลบรายการสอนชดเชย', message:'ยืนยันการลบรายการนี้?', confirmText:'ลบ', danger:true })) return;
    const r = await post('/api/makeup.php', { action:'delete_makeup', id });
    toast(r.message, r.success?'success':'error');
    if (r.success) pages.makeup();
  };
  window.approveSb = async (id) => {
    const r = await post('/api/makeup.php', { action:'approve_substitute', id });
    toast(r.message, r.success?'success':'error');
    if (r.success) pages.makeup();
  };
  window.deleteSb = async (id) => {
    if (!await confirmModal({ title:'ลบรายการสอนแทน', message:'ยืนยันการลบรายการนี้?', confirmText:'ลบ', danger:true })) return;
    const r = await post('/api/makeup.php', { action:'delete_substitute', id });
    toast(r.message, r.success?'success':'error');
    if (r.success) pages.makeup();
  };

  window.openAddMakeup = () => {
    showModal(`<div class="modal-overlay"><div class="modal">
      <div class="modal-header">เพิ่มรายการสอนชดเชย<span class="modal-close" onclick="closeModal()">×</span></div>
      <div class="modal-body">
        <div class="form-group"><label class="form-label">ครูผู้สอน</label>
          <select class="form-control" id="mk-teacher">${teachers.map(t=>`<option value="${t.id}">${t.full_name}</option>`).join('')}</select></div>
        <div class="grid-2">
          <div class="form-group"><label class="form-label">วิชา</label><input class="form-control" id="mk-subject" placeholder="ชื่อวิชา"></div>
          <div class="form-group"><label class="form-label">กลุ่มเรียน</label><input class="form-control" id="mk-group" placeholder="เช่น ชฟ.1/1"></div>
        </div>
        <div class="form-group"><label class="form-label">เหตุผล</label>
          <select class="form-control" id="mk-reason">
            ${allReasons.filter(r=>r.is_active).map(r=>`<option value="${r.code}">${r.icon} ${r.label}</option>`).join('')}
          </select></div>
        <div class="grid-2">
          <div class="form-group"><label class="form-label">วันที่ขาด</label><input class="form-control" type="date" id="mk-missed"></div>
          <div class="form-group"><label class="form-label">วันที่สอนชดเชย</label><input class="form-control" type="date" id="mk-makeup"></div>
        </div>
        <div class="grid-3">
          <div class="form-group"><label class="form-label">เวลาเริ่ม</label><input class="form-control" type="time" id="mk-start" value="08:00"></div>
          <div class="form-group"><label class="form-label">เวลาสิ้นสุด</label><input class="form-control" type="time" id="mk-end" value="11:00"></div>
          <div class="form-group"><label class="form-label">จำนวนคาบ</label><input class="form-control" type="number" id="mk-periods" value="3" min="1"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-ghost" onclick="closeModal()">ยกเลิก</button>
        <button class="btn btn-primary" onclick="submitMk()">💾 บันทึก</button>
      </div>
    </div></div>`);
    window.submitMk = async () => {
      const r = await post('/api/makeup.php',{
        action:'add_makeup', teacher_id:$('mk-teacher').value,
        subject:$('mk-subject').value, group_name:$('mk-group').value,
        reason:$('mk-reason').value, missed_date:$('mk-missed').value,
        makeup_date:$('mk-makeup').value, start_time:$('mk-start').value,
        end_time:$('mk-end').value, periods:+$('mk-periods').value,
      });
      toast(r.message, r.success?'success':'error');
      if (r.success) { closeModal(); pages.makeup(); }
    };
  };

  window.openAddSub = () => {
    showModal(`<div class="modal-overlay"><div class="modal">
      <div class="modal-header">เพิ่มรายการสอนแทน<span class="modal-close" onclick="closeModal()">×</span></div>
      <div class="modal-body">
        <div class="form-group"><label class="form-label">ครูที่ลาป่วย</label>
          <select class="form-control" id="sb-absent">${teachers.map(t=>`<option value="${t.id}">${t.full_name}</option>`).join('')}</select></div>
        <div class="form-group"><label class="form-label">ครูสอนแทน</label>
          <select class="form-control" id="sb-sub">${teachers.map(t=>`<option value="${t.id}">${t.full_name}</option>`).join('')}</select></div>
        <div class="grid-2">
          <div class="form-group"><label class="form-label">วิชา</label><input class="form-control" id="sb-subject" placeholder="ชื่อวิชา"></div>
          <div class="form-group"><label class="form-label">กลุ่มเรียน</label><input class="form-control" id="sb-group"></div>
        </div>
        <div class="grid-2">
          <div class="form-group"><label class="form-label">วันที่ขาด</label><input class="form-control" type="date" id="sb-date"></div>
          <div class="form-group"><label class="form-label">จำนวนคาบ</label><input class="form-control" type="number" id="sb-periods" value="2" min="1"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-ghost" onclick="closeModal()">ยกเลิก</button>
        <button class="btn btn-primary" onclick="submitSb()">💾 บันทึก</button>
      </div>
    </div></div>`);
    window.submitSb = async () => {
      const r = await post('/api/makeup.php',{
        action:'add_substitute', absent_teacher_id:$('sb-absent').value,
        sub_teacher_id:$('sb-sub').value, subject:$('sb-subject').value,
        group_name:$('sb-group').value, absent_date:$('sb-date').value,
        periods:+$('sb-periods').value,
      });
      toast(r.message, r.success?'success':'error');
      if (r.success) { closeModal(); pages.makeup(); }
    };
  };
};

/* ── REPORTS ──────────────────────────────────────── */
pages.reports = async () => {
  const r = await api('/api/reports.php');
  if (!r.success) return;
  const { kpi, by_dept, by_period, by_teacher, semesters } = r.data;

  $('page-content').innerHTML = `
    <div class="anim-fadeup">
      <div class="d-flex gap-10 mb-18 align-center" style="flex-wrap:wrap">
        <span class="fw-600 text-muted fs-13">กรองข้อมูล:</span>
        <select class="form-control" style="width:220px" onchange="loadReport(this.value)">
          ${semesters.map(s=>`<option value="${s.id}" ${s.is_current?'selected':''}>${s.name}</option>`).join('')}
        </select>
        <div class="flex-1"></div>
        <button class="btn btn-primary" onclick="toast('อยู่ระหว่างการพัฒนา Export Excel','info')">📊 Export Excel</button>
      </div>

      <div class="grid-4 mb-20">
        ${[
          {l:'ยอดเบิกรวม',v:fmt(kpi.total_amount),s:`ครู ${kpi.teacher_count} คน`,accent:'#7B1F32'},
          {l:'คาบเกินภาระงาน',v:fmtN(kpi.total_over)+' คาบ',s:`เฉลี่ย ${kpi.teacher_count>0?Math.round(kpi.total_over/kpi.teacher_count):0} คาบ/ครู`,accent:'#F59E0B'},
          {l:'ใบเบิกทั้งหมด',v:fmtN(kpi.doc_count)+' ใบ',s:`จ่ายแล้ว ${kpi.paid_count} ใบ`,accent:'#22C55E'},
          {l:'รออนุมัติ',v:fmtN(kpi.pending_count)+' ใบ',s:'',accent:'#EF4444'},
        ].map(c=>`
          <div class="card" style="border-top:3px solid ${c.accent}">
            <div class="card-body">
              <div class="fs-11 text-muted mb-8">${c.l}</div>
              <div style="font-size:22px;font-weight:700;color:${c.accent}">${c.v}</div>
              <div class="fs-11 text-muted mt-4">${c.s}</div>
            </div>
          </div>`).join('')}
      </div>

      <div class="grid-2 mb-18">
        <div class="card">
          <div class="card-header">ยอดเบิกแยกตามแผนก</div>
          <div class="card-body">
            ${by_dept.map(d=>`
              <div class="mb-14">
                <div class="d-flex justify-between mb-4">
                  <span class="fw-600 fs-13">${d.dept}</span>
                  <span class="fw-700 text-accent fs-13">${fmt(d.total_amount)}</span>
                </div>
                <div class="progress-track"><div class="progress-bar" style="width:${d.pct}%;background:#7B1F32"></div></div>
                <div class="d-flex justify-between mt-4">
                  <span class="fs-11 text-muted">${d.teacher_count} ครู · ${d.total_periods} คาบ</span>
                  <span class="fs-11 text-muted">${d.pct}%</span>
                </div>
              </div>`).join('')}
          </div>
        </div>
        <div class="card">
          <div class="card-header">ยอดเบิกรายงวด</div>
          <div class="card-body">
            ${by_period.map(p=>`
              <div class="d-flex align-center gap-12" style="padding:10px 0;border-bottom:1px solid var(--border)">
                <div style="width:34px;height:34px;border-radius:8px;background:#7B1F3215;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#7B1F32;flex-shrink:0">ง${p.period_num}</div>
                <div class="flex-1"><div class="fw-600 fs-12">${thaiDate(p.start_date)} – ${thaiDate(p.end_date)}</div>
                  <div class="fs-11 text-muted">${p.teacher_count} ครู · ${p.doc_count} ใบ</div></div>
                <div class="text-right">
                  <div class="fw-700 text-accent fs-13">${fmt(p.total_amount)}</div>
                  ${statusBadge(p.status)}
                </div>
              </div>`).join('')}
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">สรุปรายครู<span class="fs-11 text-muted">เรียงตามยอดเบิกมาก→น้อย</span></div>
        <div class="tbl-wrap">
          <table>
            <thead><tr>
              <th>#</th><th>ครูผู้สอน</th><th>แผนก</th>
              <th class="text-center">คาบเกินสะสม</th>
              <th class="text-center">สอนชดเชย</th><th class="text-center">สอนแทน</th>
              <th class="text-right">ยอดเบิกสะสม</th><th class="text-center">แนวโน้ม</th>
            </tr></thead>
            <tbody>
              ${by_teacher.map((t,i)=>`<tr>
                <td class="fw-700" style="color:${i===0?'#FFD700':i===1?'#C0C0C0':i===2?'#CD7F32':'var(--muted)'}">${i+1}</td>
                <td><div class="d-flex align-center gap-10">
                  <div style="width:30px;height:30px;border-radius:50%;background:#7B1F3222;color:#7B1F32;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700">${t.full_name.slice(0,1)}</div>
                  <span class="fw-600 fs-13">${t.full_name}</span>
                </div></td>
                <td class="text-muted fs-12">${t.dept_name}</td>
                <td class="text-center fw-600">${t.total_over} คาบ</td>
                <td class="text-center text-muted fs-12">${t.makeup_count} ครั้ง</td>
                <td class="text-center text-muted fs-12">${t.sub_count} ครั้ง</td>
                <td class="text-right fw-700 text-accent" style="font-size:14px">${fmt(t.total_amount)}</td>
                <td class="text-center" style="font-size:18px">${t.total_amount>50000?'📈':t.total_amount>20000?'➡️':'📉'}</td>
              </tr>`).join('')}
            </tbody>
          </table>
        </div>
      </div>
    </div>`;

  window.loadReport = (semId) => {
    api(`/api/reports.php?semester_id=${semId}`).then(r => {
      if (r.success) pages.reports();
    });
  };
};

/* ── USERS ────────────────────────────────────────── */
pages.users = async () => {
  const r = await api('/api/users.php');
  if (!r.success) return;
  const { users, departments } = r.data;
  const roleLabels = { admin:'ผู้ดูแลระบบ', director:'ผู้อำนวยการ', curriculum:'งานหลักสูตร', teacher:'ครูผู้สอน', accounting:'งานบัญชี' };
  const roleColors = { admin:'#EF4444', director:'#7B1F32', curriculum:'#3B82F6', teacher:'#22C55E', accounting:'#F59E0B' };

  let uSearch = '', uRole = '', uStatus = '', uPage = 1;
  const perPage = 10;

  $('page-content').innerHTML = `
    <div class="anim-fadeup">
      <div class="d-flex justify-between mb-18 align-center" style="flex-wrap:wrap;gap:10px">
        <div class="text-muted fs-13">จัดการบัญชีผู้ใช้งานทุก Role ในระบบ</div>
        <div class="d-flex gap-8" style="flex-wrap:wrap">
          ${can('admin') ? `<button class="btn btn-outline" onclick="openRmsSync()">🔌 โอนข้อมูลจาก RMS</button>` : ''}
          <button class="btn btn-primary" onclick="openAddUser()">+ เพิ่มผู้ใช้</button>
        </div>
      </div>

      <div class="d-flex gap-10 mb-14 align-center" style="flex-wrap:wrap">
        <input class="form-control" id="u-search" placeholder="🔍 ค้นหา ชื่อ / ชื่อผู้ใช้ / อีเมล" style="width:280px" oninput="usersOnSearch(this.value)">
        <select class="form-control" style="width:170px" onchange="usersOnRole(this.value)">
          <option value="">ทุกบทบาท</option>
          ${Object.entries(roleLabels).map(([v,l])=>`<option value="${v}">${l}</option>`).join('')}
        </select>
        <select class="form-control" style="width:150px" onchange="usersOnStatus(this.value)">
          <option value="">ทุกสถานะ</option>
          <option value="1">ใช้งาน</option>
          <option value="0">ไม่ใช้งาน</option>
        </select>
        <div class="flex-1"></div>
        <div class="fs-12 text-muted" id="u-count"></div>
      </div>

      <div class="card">
        <div class="tbl-wrap">
          <table>
            <thead><tr>
              <th>ผู้ใช้งาน</th><th>อีเมล</th><th class="text-center">บทบาท</th>
              <th>แผนก</th><th class="text-center">ล็อกอินล่าสุด</th>
              <th class="text-center">สถานะ</th><th class="text-center">จัดการ</th>
            </tr></thead>
            <tbody id="users-tbody"></tbody>
          </table>
        </div>
        <div id="u-pager" class="d-flex align-center justify-between" style="padding:12px 16px;border-top:1px solid var(--border);flex-wrap:wrap;gap:10px"></div>
      </div>
    </div>`;

  const renderUsers = () => {
    const q = uSearch.trim().toLowerCase();
    let list = users.filter(u => {
      if (uRole && u.role !== uRole) return false;
      if (uStatus !== '' && String(u.is_active) !== uStatus) return false;
      if (q) {
        const hay = `${u.full_name} ${u.username||''} ${u.email||''}`.toLowerCase();
        if (!hay.includes(q)) return false;
      }
      return true;
    });

    const totalPages = Math.max(1, Math.ceil(list.length / perPage));
    if (uPage > totalPages) uPage = totalPages;
    const start = (uPage - 1) * perPage;
    const pageRows = list.slice(start, start + perPage);

    $('u-count').textContent = `พบ ${fmtN(list.length)} รายชื่อ`;
    $('users-tbody').innerHTML = pageRows.map(u=>`<tr>
        <td><div class="d-flex align-center gap-10">
          <div style="width:32px;height:32px;border-radius:50%;background:${roleColors[u.role]}22;color:${roleColors[u.role]};display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700">${u.full_name.slice(0,1)}</div>
          <div><div class="fw-600 fs-13">${u.full_name}</div><div class="fs-11 text-muted">${u.username||''}</div></div>
        </div></td>
        <td class="text-muted fs-12">${u.email||'-'}</td>
        <td class="text-center"><span class="badge" style="background:${roleColors[u.role]}20;color:${roleColors[u.role]}">${roleLabels[u.role]||u.role}</span></td>
        <td class="fs-12">${u.dept_name||'-'}</td>
        <td class="text-center text-muted fs-12">${u.last_login?thaiDate(u.last_login.split(' ')[0]):'-'}</td>
        <td class="text-center">
          <div class="toggle ${u.is_active?'on':'off'}" onclick="toggleUser(${u.id},${u.is_active})"><div class="toggle-knob"></div></div>
        </td>
        <td class="text-center"><div class="d-flex gap-8" style="justify-content:center">
          <button class="btn btn-ghost btn-sm" onclick="openEditUser(${u.id})">แก้ไข</button>
          <button class="btn btn-ghost btn-sm" onclick="resetPwd(${u.id})">รีเซ็ต</button>
          ${can('admin') ? `<button class="btn btn-danger btn-sm" onclick="deleteUser(${u.id})">ลบ</button>` : ''}
        </div></td>
      </tr>`).join('') || `<tr><td colspan="7" style="text-align:center;padding:40px;color:var(--muted)">ไม่พบผู้ใช้ที่ตรงกับเงื่อนไข</td></tr>`;

    const from = list.length ? start + 1 : 0;
    const to   = Math.min(start + perPage, list.length);
    $('u-pager').innerHTML = `
      <div class="fs-12 text-muted">แสดง ${from}–${to} จาก ${fmtN(list.length)} · หน้า ${uPage}/${totalPages}</div>
      <div class="d-flex gap-6">
        <button class="btn btn-ghost btn-sm" ${uPage<=1?'disabled':''} onclick="usersGoPage(${uPage-1})">← ก่อนหน้า</button>
        <button class="btn btn-ghost btn-sm" ${uPage>=totalPages?'disabled':''} onclick="usersGoPage(${uPage+1})">ถัดไป →</button>
      </div>`;
  };

  window.usersOnSearch = (v) => { uSearch = v; uPage = 1; renderUsers(); };
  window.usersOnRole   = (v) => { uRole = v; uPage = 1; renderUsers(); };
  window.usersOnStatus = (v) => { uStatus = v; uPage = 1; renderUsers(); };
  window.usersGoPage   = (p) => { uPage = p; renderUsers(); };
  renderUsers();

  window._usersData = users;
  window._deptsData = departments;

  window.openRmsSync = async () => {
    const cfg = await api('/api/settings.php?section=integration');
    const url = cfg.data?.rms_base_url || '';
    showModal(`<div class="modal-overlay"><div class="modal" style="max-width:480px">
      <div class="modal-header">โอนข้อมูลบุคลากรจาก RMS<span class="modal-close" onclick="closeModal()">×</span></div>
      <div class="modal-body">
        <div class="fs-13 text-muted mb-14" style="line-height:1.7">
          ดึงข้อมูลจาก <code>${url || '(ยังไม่ได้ตั้งค่า URL)'}</code>
          <ul style="margin:8px 0 0 18px;padding:0">
            <li>โอนเฉพาะบุคลากรที่ยังไม่พ้นสภาพ (<code>people_exit = 0</code>)</li>
            <li><code>people_id</code> เป็นชื่อผู้ใช้ · รหัสผ่านจาก <code>ath_pass</code></li>
            <li>บุคลากรที่พ้นสภาพ/ไม่พบในต้นทางจะถูกตั้งเป็น "ไม่ใช้งาน"</li>
          </ul>
          <div class="mt-8">แก้ไข URL ต้นทางได้ที่เมนู <b>การตั้งค่าระบบ → การเชื่อมต่อ RMS</b></div>
        </div>
        <div id="rms-modal-result"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-ghost" onclick="closeModal()">ปิด</button>
        <button class="btn btn-primary" id="rms-modal-btn" onclick="runRmsSync()">⬇️ เริ่มโอนข้อมูล</button>
      </div>
    </div></div>`);

    window.runRmsSync = async () => {
      const btn = $('rms-modal-btn');
      btn.disabled = true;
      btn.innerHTML = `<span class="spinner" style="width:15px;height:15px;border-width:2px;border-top-color:#fff;border-color:rgba(255,255,255,.4);border-top-color:#fff;display:inline-block;vertical-align:middle;margin-right:6px"></span>กำลังโอน...`;
      $('rms-modal-result').innerHTML = rmsSyncLoadingHtml();
      const res = await post('/api/users.php', { action: 'sync_rms' });
      btn.disabled = false; btn.textContent = '⬇️ เริ่มโอนข้อมูล';
      toast(res.message, res.success ? 'success' : 'error');
      if (res.success) {
        const d = res.data;
        $('rms-modal-result').innerHTML = `
          <div class="anim-fadein d-flex gap-8 mb-8" style="flex-wrap:wrap">
            <span class="badge badge-approved">เพิ่มใหม่ ${d.created}</span>
            <span class="badge" style="background:#3B82F620;color:#3B82F6">อัปเดต ${d.updated}</span>
            <span class="badge badge-rejected">ปิดใช้งาน ${d.deactivated}</span>
          </div>`;
        setTimeout(() => { closeModal(); pages.users(); }, 1800);
      } else {
        $('rms-modal-result').innerHTML = `<div class="fs-13" style="color:#EF4444">${res.message}</div>`;
      }
    };
  };

  window.toggleUser = async (id, cur) => {
    const r = await post('/api/users.php', { action:'toggle_active', id });
    if (r.success) pages.users();
  };
  window.resetPwd = async (id) => {
    const pwd = await promptModal({ title:'รีเซ็ตรหัสผ่าน', label:'รหัสผ่านใหม่', placeholder:'กรอกรหัสผ่านใหม่', type:'password', confirmText:'รีเซ็ต' });
    if (!pwd) return;
    const r = await post('/api/users.php', { action:'reset_password', id, new_password:pwd });
    toast(r.message, r.success?'success':'error');
  };
  window.deleteUser = async (id) => {
    const u = (window._usersData||[]).find(x=>x.id===id);
    const name = u ? u.full_name : '';
    if (!await confirmModal({ title:'ลบผู้ใช้งาน', message:`ลบผู้ใช้ "${name}" ออกจากระบบถาวร?\nการดำเนินการนี้ไม่สามารถย้อนกลับได้`, confirmText:'ลบถาวร', danger:true })) return;
    const r = await post('/api/users.php', { action:'delete', id });
    toast(r.message, r.success?'success':'error');
    if (r.success) pages.users();
  };
  window.openAddUser = () => {
    showModal(`<div class="modal-overlay"><div class="modal">
      <div class="modal-header">เพิ่มผู้ใช้งาน<span class="modal-close" onclick="closeModal()">×</span></div>
      <div class="modal-body">
        <div class="grid-2">
          <div class="form-group"><label class="form-label">ชื่อ-นามสกุล</label><input class="form-control" id="nu-name"></div>
          <div class="form-group"><label class="form-label">ชื่อผู้ใช้</label><input class="form-control" id="nu-username"></div>
        </div>
        <div class="grid-2">
          <div class="form-group"><label class="form-label">รหัสผ่าน</label><input class="form-control" type="password" id="nu-pwd"></div>
          <div class="form-group"><label class="form-label">อีเมล</label><input class="form-control" type="email" id="nu-email"></div>
        </div>
        <div class="grid-2">
          <div class="form-group"><label class="form-label">บทบาท</label>
            <select class="form-control" id="nu-role">
              ${Object.entries(roleLabels).map(([v,l])=>`<option value="${v}">${l}</option>`).join('')}
            </select></div>
          <div class="form-group"><label class="form-label">แผนก</label>
            <select class="form-control" id="nu-dept">
              <option value="">ไม่ระบุ</option>
              ${departments.map(d=>`<option value="${d.id}">${d.name}</option>`).join('')}
            </select></div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-ghost" onclick="closeModal()">ยกเลิก</button>
        <button class="btn btn-primary" onclick="submitUser()">เพิ่มผู้ใช้</button>
      </div>
    </div></div>`);
    window.submitUser = async () => {
      const r = await post('/api/users.php',{
        action:'create', full_name:$('nu-name').value, username:$('nu-username').value,
        password:$('nu-pwd').value, email:$('nu-email').value,
        role:$('nu-role').value, department_id:$('nu-dept').value||null,
      });
      toast(r.message, r.success?'success':'error');
      if (r.success) { closeModal(); pages.users(); }
    };
  };
  window.openEditUser = (id) => {
    const u = (window._usersData||[]).find(x=>x.id===id);
    if (!u) return;
    showModal(`<div class="modal-overlay"><div class="modal">
      <div class="modal-header">แก้ไขผู้ใช้งาน<span class="modal-close" onclick="closeModal()">×</span></div>
      <div class="modal-body">
        <div class="form-group"><label class="form-label">ชื่อ-นามสกุล</label><input class="form-control" id="eu-name" value="${u.full_name}"></div>
        <div class="form-group"><label class="form-label">อีเมล</label><input class="form-control" type="email" id="eu-email" value="${u.email||''}"></div>
        <div class="grid-2">
          <div class="form-group"><label class="form-label">บทบาท</label>
            <select class="form-control" id="eu-role">
              ${Object.entries(roleLabels).map(([v,l])=>`<option value="${v}" ${u.role===v?'selected':''}>${l}</option>`).join('')}
            </select></div>
          <div class="form-group"><label class="form-label">แผนก</label>
            <select class="form-control" id="eu-dept">
              <option value="">ไม่ระบุ</option>
              ${(window._deptsData||[]).map(d=>`<option value="${d.id}" ${d.id==u.department_id?'selected':''}>${d.name}</option>`).join('')}
            </select></div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-ghost" onclick="closeModal()">ยกเลิก</button>
        <button class="btn btn-primary" onclick="submitEditUser(${u.id})">บันทึก</button>
      </div>
    </div></div>`);
    window.submitEditUser = async (id) => {
      const r = await post('/api/users.php',{
        action:'update', id, full_name:$('eu-name').value,
        email:$('eu-email').value, role:$('eu-role').value,
        department_id:$('eu-dept').value||null,
      });
      toast(r.message, r.success?'success':'error');
      if (r.success) { closeModal(); pages.users(); }
    };
  };
};

/* ── INSTITUTION ──────────────────────────────────── */
pages.institution = async () => {
  const r = await api('/api/institution.php');
  if (!r.success) return;
  const { institution: inst, holidays, current_semester: sem, semesters = [] } = r.data;

  $('page-content').innerHTML = `
    <div class="anim-fadeup grid-2" style="align-items:flex-start;max-width:920px">
      <div>
        <div class="card mb-18">
          <div class="card-header">🏫 ข้อมูลสถานศึกษา</div>
          <div class="card-body" style="display:flex;flex-direction:column;gap:12px">
            <div class="form-group"><label class="form-label">ชื่อสถานศึกษา</label><input class="form-control" id="inst-name" value="${inst?.school_name||''}"></div>
            <div class="form-group"><label class="form-label">ที่อยู่</label><input class="form-control" id="inst-addr" value="${inst?.address||''}"></div>
            <div class="form-group"><label class="form-label">โทรศัพท์</label><input class="form-control" id="inst-phone" value="${inst?.phone||''}"></div>
            <div class="form-group"><label class="form-label">ชื่อผู้อำนวยการ</label><input class="form-control" id="inst-dir" value="${inst?.director_name||''}"></div>
            <button class="btn btn-primary" onclick="saveInst()">💾 บันทึกข้อมูล</button>
          </div>
        </div>
        <div class="card">
          <div class="card-header">📆 ปฏิทินการศึกษา ${sem?.name||''}</div>
          <div class="card-body grid-2">
            <div class="form-group"><label class="form-label">วันเปิดภาคเรียน</label><input class="form-control" type="date" id="sem-start" value="${sem?.start_date||''}"></div>
            <div class="form-group"><label class="form-label">วันปิดภาคเรียน</label><input class="form-control" type="date" id="sem-end" value="${sem?.end_date||''}"></div>
          </div>
          <div style="padding:0 18px 18px"><button class="btn btn-primary" onclick="saveSem()">💾 บันทึกปฏิทิน</button></div>
        </div>
      </div>
      <div>
        <div class="card">
          <div class="card-header">🎌 วันหยุดสถานศึกษา
            <div class="d-flex gap-6">
              ${can('admin','director') ? `<button class="btn btn-outline btn-sm" onclick="syncHolidays()">🔌 โหลดจาก RMS</button>` : ''}
              <button class="btn btn-outline btn-sm" onclick="openAddHoliday()">+ เพิ่ม</button>
            </div>
          </div>
          <div class="card-body">
            <div id="holiday-list">
              ${holidays.map(h=>`
                <div class="d-flex align-center justify-between" style="padding:9px 0;border-bottom:1px solid var(--border)">
                  <div class="d-flex align-center gap-10">
                    <span style="font-size:16px">🎌</span>
                    <div><div class="fw-600 fs-13">${h.name}</div><div class="fs-11 text-muted">${thaiDate(h.holiday_date)}</div></div>
                  </div>
                  <button class="btn btn-ghost btn-sm" onclick="deleteHoliday(${h.id})">×</button>
                </div>`).join('')||'<div class="text-muted text-center" style="padding:20px">ไม่มีข้อมูลวันหยุด</div>'}
            </div>
          </div>
        </div>

        <div class="card mt-18">
          <div class="card-header">📚 ภาคเรียนทั้งหมด <span class="fs-11 text-muted">${semesters.length} ภาคเรียน</span></div>
          <div class="card-body" style="padding:6px 0">
            ${semesters.map(s=>`
              <div class="d-flex align-center justify-between" style="padding:10px 18px;border-bottom:1px solid var(--border)">
                <div>
                  <div class="fw-600 fs-13">${s.name} ${s.is_current==1?'<span class="badge badge-approved" style="margin-left:4px">ปัจจุบัน</span>':''}</div>
                  <div class="fs-11 text-muted">${thaiDate(s.start_date)} – ${thaiDate(s.end_date)}</div>
                </div>
                ${can('admin','director') && s.is_current!=1 ? `<button class="btn btn-outline btn-sm" onclick="setCurrentSem(${s.id})">ตั้งเป็นปัจจุบัน</button>` : ''}
              </div>`).join('')||'<div class="text-muted text-center" style="padding:20px">ไม่มีข้อมูลภาคเรียน</div>'}
          </div>
        </div>
      </div>
    </div>`;

  window.saveInst = async () => {
    const r = await post('/api/institution.php',{
      action:'save_institution', school_name:$('inst-name').value,
      address:$('inst-addr').value, phone:$('inst-phone').value, director_name:$('inst-dir').value,
    });
    toast(r.message, r.success?'success':'error');
  };
  window.saveSem = async () => {
    const r = await post('/api/institution.php',{
      action:'save_semester', id:sem?.id, name:sem?.name,
      year:sem?.year, semester:sem?.semester,
      start_date:$('sem-start').value, end_date:$('sem-end').value,
    });
    toast(r.message, r.success?'success':'error');
  };
  window.setCurrentSem = async (id) => {
    const s = semesters.find(x => x.id == id);
    if (!await confirmModal({ title:'ตั้งภาคเรียนปัจจุบัน', message:`ตั้ง "${s?.name||''}" เป็นภาคเรียนปัจจุบัน?`, confirmText:'ตั้งเป็นปัจจุบัน', icon:'📆' })) return;
    const r = await post('/api/institution.php', { action:'set_current_semester', id });
    toast(r.message, r.success?'success':'error');
    if (r.success) pages.institution();
  };
  window.deleteHoliday = async (id) => {
    if (!await confirmModal({ title:'ลบวันหยุด', message:'ยืนยันการลบวันหยุดนี้?', confirmText:'ลบ', danger:true })) return;
    const r = await post('/api/institution.php', { action:'delete_holiday', id });
    toast(r.message, r.success?'success':'error');
    if (r.success) pages.institution();
  };
  window.syncHolidays = async () => {
    if (!await confirmModal({
      title:'โหลดวันหยุดจาก RMS',
      message:'ดึงข้อมูลวันหยุดจากระบบ RMS?\nจะเพิ่มเฉพาะวันหยุดที่ตรงกับภาคเรียนในระบบ และข้ามรายการที่มีอยู่แล้ว',
      confirmText:'โหลดข้อมูล', icon:'🔌',
    })) return;
    const list = $('holiday-list');
    if (list) list.innerHTML = rmsSyncLoadingHtml();
    const r = await post('/api/institution.php', { action:'sync_holidays' });
    toast(r.message, r.success?'success':'error');
    if (r.success) pages.institution();
    else if (list) list.innerHTML = `<div class="fs-13" style="color:#EF4444;padding:12px">${r.message}</div>`;
  };
  window.openAddHoliday = () => {
    showModal(`<div class="modal-overlay"><div class="modal" style="max-width:400px">
      <div class="modal-header">เพิ่มวันหยุด<span class="modal-close" onclick="closeModal()">×</span></div>
      <div class="modal-body">
        <div class="form-group"><label class="form-label">ชื่อวันหยุด</label><input class="form-control" id="hd-name" placeholder="เช่น วันมหิดล"></div>
        <div class="form-group"><label class="form-label">วันที่</label><input class="form-control" type="date" id="hd-date"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-ghost" onclick="closeModal()">ยกเลิก</button>
        <button class="btn btn-primary" onclick="submitHoliday()">เพิ่มวันหยุด</button>
      </div>
    </div></div>`);
    window.submitHoliday = async () => {
      const r = await post('/api/institution.php',{action:'add_holiday',name:$('hd-name').value,holiday_date:$('hd-date').value});
      toast(r.message, r.success?'success':'error');
      if (r.success) { closeModal(); pages.institution(); }
    };
  };
};

/* ── RMS sync loading animation (shared) ──────────── */
function rmsSyncLoadingHtml() {
  return `
    <div class="anim-fadein" style="display:flex;align-items:center;gap:14px;padding:14px 16px;border:1px solid var(--border);border-radius:10px;background:rgba(123,31,50,.04)">
      <div class="spinner spinner-lg"></div>
      <div style="flex:1;min-width:0">
        <div class="fw-600 fs-13" style="color:#7B1F32">กำลังโอนข้อมูลบุคลากรจาก RMS<span class="sync-dots"><span>.</span><span>.</span><span>.</span></span></div>
        <div class="fs-11 text-muted" style="margin:6px 0 8px">กำลังเชื่อมต่อและประมวลผลข้อมูล — อาจใช้เวลาสักครู่ อย่าปิดหน้าต่างนี้</div>
        <div class="progress-indet"></div>
      </div>
    </div>`;
}

/* ── SETTINGS ─────────────────────────────────────── */
pages.settings = async () => {
  let activeSection = 'makeup_reasons';

  const sections = [
    { id: 'makeup_reasons', icon: '🔖', label: 'เหตุผลการสอนชดเชย' },
    { id: 'integration',    icon: '🔌', label: 'การเชื่อมต่อ RMS' },
  ];

  const renderShell = () => {
    $('page-content').innerHTML = `
      <div class="anim-fadeup">
        <div class="d-flex" style="align-items:flex-start;gap:28px">
          <div class="card" style="width:220px;flex-shrink:0;padding:8px 0">
            ${sections.map(s => `
              <div onclick="switchSettingSection('${s.id}')"
                   style="display:flex;align-items:center;gap:10px;padding:10px 16px;border-radius:8px;cursor:pointer;transition:background .15s;${activeSection===s.id?'background:#7B1F3215;color:#7B1F32;font-weight:600':'color:var(--text)'}">
                <span style="font-size:16px">${s.icon}</span>
                <span class="fs-13">${s.label}</span>
              </div>`).join('')}
          </div>
          <div class="flex-1" id="settings-content">
            <div style="text-align:center;padding:60px;color:var(--muted)">⏳ กำลังโหลด...</div>
          </div>
        </div>
      </div>`;
    loadSection(activeSection);
  };

  const loadSection = async (section) => {
    if (section === 'makeup_reasons') await loadReasonsSection();
    if (section === 'integration')    await loadIntegrationSection();
  };

  const loadIntegrationSection = async () => {
    const r   = await api('/api/settings.php?section=integration');
    const url = r.data?.rms_base_url || '';
    const sc  = document.getElementById('settings-content');
    if (!sc) return;

    const badge = (label, val, cls, style='') =>
      `<span class="badge ${cls}" ${style?`style="${style}"`:''}>${label} ${val}</span>`;

    // รายการโอนข้อมูลจาก RMS — เพิ่มรายการใหม่ในอนาคตได้ที่ array นี้
    const transfers = [
      {
        key:'people', icon:'👥', title:'โอนข้อมูลบุคลากร', data:'people', btn:'เริ่มโอนข้อมูล',
        api:'/api/users.php', body:{ action:'sync_rms' },
        confirm:{ title:'โอนข้อมูลบุคลากรจาก RMS', message:'เริ่มโอนข้อมูลบุคลากรจาก RMS?\nข้อมูลผู้ใช้ที่มี people_id เดิมจะถูกอัปเดต', confirmText:'เริ่มโอนข้อมูล', icon:'👥' },
        details:`<ul style="margin:0 0 0 18px;padding:0">
          <li>โอนเฉพาะบุคลากรที่ยังไม่พ้นสภาพ (<code>people_exit = 0</code>)</li>
          <li>ใช้ <code>people_id</code> เป็นชื่อผู้ใช้ · ชื่อ-สกุลจาก <code>people_name + people_surname</code></li>
          <li>รหัสผ่านจาก <code>ath_pass</code> (เข้ารหัสก่อนจัดเก็บ)</li>
          <li>บุคลากรที่พ้นสภาพหรือไม่พบในต้นทางจะถูกตั้งเป็น "ไม่ใช้งาน"</li>
          <li>ผู้ใช้เดิมที่โอนซ้ำจะไม่อัปเดตวันที่สร้างบัญชี</li></ul>`,
        badges:d => badge('เพิ่มใหม่',d.created,'badge-approved') + badge('อัปเดต',d.updated,'','background:#3B82F620;color:#3B82F6')
                  + badge('ปิดใช้งาน',d.deactivated,'badge-rejected') + badge('บุคลากรในต้นทาง',d.active_source,'badge-draft'),
      },
      {
        key:'dateedu', icon:'📆', title:'โหลดข้อมูลภาคเรียน', data:'dateedu', btn:'โหลดภาคเรียน',
        api:'/api/institution.php', body:{ action:'sync_semesters' },
        confirm:{ title:'โหลดภาคเรียนจาก RMS', message:'ดึงข้อมูลภาคเรียนจากระบบ RMS?\nจะสร้าง/อัปเดตภาคเรียนตามข้อมูลต้นทาง โดยไม่เปลี่ยนภาคเรียนปัจจุบัน', confirmText:'โหลดข้อมูล', icon:'📆' },
        details:`<ul style="margin:0 0 0 18px;padding:0">
          <li>สร้าง/อัปเดตภาคเรียนตามปีการศึกษา (<code>dateedu_eduyear</code>) พร้อมวันเปิด-ปิดภาคเรียน</li>
          <li>ไม่เปลี่ยนแปลงภาคเรียนปัจจุบันที่กำหนดไว้ (โหลดซ้ำได้)</li></ul>`,
        badges:d => badge('เพิ่มใหม่',d.added,'badge-approved') + badge('อัปเดต',d.updated,'','background:#3B82F620;color:#3B82F6') + badge('ข้าม',d.skipped,'badge-draft'),
      },
      {
        key:'stopday', icon:'🎌', title:'โหลดวันหยุด', data:'stopday', btn:'โหลดวันหยุด',
        api:'/api/institution.php', body:{ action:'sync_holidays' },
        confirm:{ title:'โหลดวันหยุดจาก RMS', message:'ดึงข้อมูลวันหยุดจากระบบ RMS?\nจะเพิ่มเฉพาะวันหยุดที่ตรงกับภาคเรียนในระบบ และข้ามรายการที่มีอยู่แล้ว', confirmText:'โหลดข้อมูล', icon:'🎌' },
        details:`<ul style="margin:0 0 0 18px;padding:0">
          <li>เพิ่มเฉพาะวันหยุดที่ปีการศึกษา/ภาคเรียน (<code>stopday_eduyear</code>) ตรงกับภาคเรียนในระบบ</li>
          <li>ข้ามรายการที่มีวันหยุดวันเดียวกันอยู่แล้ว (โหลดซ้ำได้)</li></ul>`,
        badges:d => badge('เพิ่มใหม่',d.added,'badge-approved') + badge('ซ้ำ',d.duplicated,'','background:#3B82F620;color:#3B82F6') + badge('ข้าม',d.skipped,'badge-draft'),
      },
      {
        key:'studentgroup', icon:'👨‍🎓', title:'โหลดกลุ่มผู้เรียน', data:'std2018_studentgroup', btn:'โหลดกลุ่มเรียน',
        api:'/api/institution.php', body:{ action:'sync_student_groups' },
        confirm:{ title:'โหลดกลุ่มผู้เรียนจาก RMS', message:'ดึงข้อมูลกลุ่มผู้เรียนจากระบบ RMS?\nจะสร้าง/อัปเดตกลุ่มเรียนตามข้อมูลต้นทาง (โหลดซ้ำได้)', confirmText:'โหลดข้อมูล', icon:'👨‍🎓' },
        details:`<ul style="margin:0 0 0 18px;padding:0">
          <li>สร้าง/อัปเดตกลุ่มเรียนตามปีการศึกษา/ภาคเรียน + รหัสกลุ่ม (<code>groupCode</code>)</li>
          <li>เก็บระดับชั้น ชื่อกลุ่ม ชื่อย่อ และครูที่ปรึกษา</li></ul>`,
        badges:d => badge('เพิ่มใหม่',d.added,'badge-approved') + badge('อัปเดต',d.updated,'','background:#3B82F620;color:#3B82F6') + badge('ข้าม',d.skipped,'badge-draft'),
      },
    ];

    sc.innerHTML = `
      <div class="card mb-18">
        <div class="card-header"><span>🔌 การเชื่อมต่อระบบ RMS</span></div>
        <div class="card-body">
          <label class="form-label">URL ฐานของระบบ RMS (host)</label>
          <div class="d-flex gap-8 align-center" style="flex-wrap:wrap">
            <input class="form-control flex-1" id="rms-url" value="${url}" placeholder="http://rms.rvc.ac.th" style="min-width:220px">
            <button class="btn btn-outline btn-sm" onclick="saveRmsUrl()">💾 บันทึก</button>
          </div>
          <div class="fs-11 text-muted mt-4">ระบบจะต่อ path <code>/api_connection.php?app_name=nutty&amp;data=…</code> เข้าไปอัตโนมัติ</div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><span>⬇️ โอนข้อมูลจาก RMS</span></div>
        <div class="card-body" style="padding:4px 0">
          ${transfers.map((t,i) => `
            <div style="padding:14px 18px;${i<transfers.length-1?'border-bottom:1px solid var(--border)':''}">
              <div class="d-flex align-center justify-between gap-10" style="flex-wrap:wrap">
                <div class="d-flex align-center gap-10">
                  <div style="width:36px;height:36px;border-radius:9px;background:#7B1F3215;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0">${t.icon}</div>
                  <div>
                    <div class="fw-600 fs-13">${t.title}</div>
                    <div class="fs-11 text-muted"><code>data=${t.data}</code></div>
                  </div>
                </div>
                <div class="d-flex align-center gap-10" style="flex-wrap:wrap">
                  <div id="rt-res-${t.key}"></div>
                  <button class="btn btn-primary btn-sm" id="rt-btn-${t.key}" onclick="runRmsTransfer('${t.key}')">⬇️ ${t.btn}</button>
                </div>
              </div>
              <details style="margin-top:8px">
                <summary style="cursor:pointer;font-size:12px;color:#7B1F32;user-select:none">ดูรายละเอียด</summary>
                <div class="fs-12 text-muted" style="line-height:1.7;margin-top:6px">${t.details}</div>
              </details>
            </div>`).join('')}
        </div>
      </div>`;

    window.saveRmsUrl = async () => {
      const res = await post('/api/settings.php', { action: 'save_rms_url', rms_base_url: $('rms-url').value });
      toast(res.message, res.success ? 'success' : 'error');
    };

    window.runRmsTransfer = async (key) => {
      const cfg = transfers.find(t => t.key === key);
      if (!cfg) return;
      if (!await confirmModal(cfg.confirm)) return;
      const btn = $('rt-btn-' + key);
      const res = $('rt-res-' + key);
      btn.disabled = true;
      btn.innerHTML = `<span class="spinner" style="width:14px;height:14px;border-width:2px;border-color:rgba(255,255,255,.4);border-top-color:#fff;display:inline-block;vertical-align:middle;margin-right:6px"></span>กำลังทำงาน...`;
      res.innerHTML = `<span class="fs-11 text-muted">กำลังเชื่อมต่อ RMS…</span>`;
      const r = await post(cfg.api, cfg.body);
      btn.disabled = false;
      btn.textContent = '⬇️ ' + cfg.btn;
      toast(r.message, r.success ? 'success' : 'error');
      res.innerHTML = r.success
        ? `<div class="anim-fadein d-flex gap-6" style="flex-wrap:wrap;justify-content:flex-end">${cfg.badges(r.data)}</div>`
        : `<div class="fs-12" style="color:#EF4444">${r.message}</div>`;
    };
  };

  const loadReasonsSection = async () => {
    const r   = await api('/api/settings.php?section=makeup_reasons');
    const rss = r.data?.reasons || [];
    const sc  = document.getElementById('settings-content');
    if (!sc) return;

    sc.innerHTML = `
      <div class="card">
        <div class="card-header">
          <span>🔖 เหตุผลการสอนชดเชย</span>
          <button class="btn btn-primary btn-sm" onclick="openAddReason()">+ เพิ่มเหตุผล</button>
        </div>
        <div class="card-body" style="padding-bottom:4px">
          <div class="fs-12 text-muted">เหตุผลที่ปิดการใช้งานจะไม่ปรากฏในฟอร์มบันทึก แต่ยังแสดงในรายการเดิม รหัส (code) ไม่สามารถเปลี่ยนได้หลังสร้าง</div>
        </div>
        <div class="tbl-wrap">
          <table>
            <thead><tr>
              <th style="width:56px;text-align:center">ไอคอน</th>
              <th>ชื่อเหตุผล</th>
              <th>รหัส</th>
              <th class="text-center">สถานะ</th>
              <th class="text-center">จัดการ</th>
            </tr></thead>
            <tbody>
              ${rss.map(rs => `
                <tr style="${!rs.is_active ? 'opacity:.45' : ''}">
                  <td style="font-size:22px;text-align:center">${rs.icon}</td>
                  <td>
                    <span class="badge" style="background:${rs.bg_color};color:${rs.color}">${rs.label}</span>
                    ${!rs.is_deletable ? '<span class="fs-11 text-muted" style="margin-left:6px">(ค่าเริ่มต้น)</span>' : ''}
                  </td>
                  <td><code style="font-size:11px;background:var(--bg);padding:2px 7px;border-radius:4px;border:1px solid var(--border)">${rs.code}</code></td>
                  <td class="text-center">
                    <button class="btn btn-sm ${rs.is_active ? 'btn-success' : 'btn-ghost'}" onclick="toggleReason(${rs.id})">
                      ${rs.is_active ? '✅ เปิดใช้งาน' : '⛔ ปิดใช้งาน'}
                    </button>
                  </td>
                  <td class="text-center">
                    <div class="d-flex gap-8" style="justify-content:center">
                      <button class="btn btn-outline btn-sm" onclick='openEditReason(${JSON.stringify(rs)})'>✏️ แก้ไข</button>
                      ${rs.is_deletable ? `<button class="btn btn-danger btn-sm" onclick="deleteReason(${rs.id})">ลบ</button>` : ''}
                    </div>
                  </td>
                </tr>`).join('') || `<tr><td colspan="5" style="text-align:center;padding:40px;color:var(--muted)">ไม่มีข้อมูล</td></tr>`}
            </tbody>
          </table>
        </div>
      </div>`;

    window.toggleReason = async (id) => {
      const res = await post('/api/settings.php', { action: 'toggle_reason', id });
      toast(res.message, res.success ? 'success' : 'error');
      if (res.success) loadReasonsSection();
    };

    window.deleteReason = async (id) => {
      if (!await confirmModal({ title:'ลบเหตุผล', message:'ยืนยันการลบเหตุผลนี้?', confirmText:'ลบ', danger:true })) return;
      const res = await post('/api/settings.php', { action: 'delete_reason', id });
      toast(res.message, res.success ? 'success' : 'error');
      if (res.success) loadReasonsSection();
    };

    window.openAddReason = () => {
      showModal(`<div class="modal-overlay"><div class="modal" style="max-width:440px">
        <div class="modal-header">เพิ่มเหตุผลใหม่<span class="modal-close" onclick="closeModal()">×</span></div>
        <div class="modal-body">
          <div class="form-group">
            <label class="form-label">ชื่อเหตุผล <span style="color:#EF4444">*</span></label>
            <input class="form-control" id="rs-label" placeholder="เช่น ลาคลอด">
          </div>
          <div class="grid-2">
            <div class="form-group">
              <label class="form-label">รหัส (a-z, _) <span style="color:#EF4444">*</span></label>
              <input class="form-control" id="rs-code" placeholder="เช่น maternity">
            </div>
            <div class="form-group">
              <label class="form-label">ไอคอน</label>
              <input class="form-control" id="rs-icon" value="📋" maxlength="4">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">สีป้ายเหตุผล</label>
            <div class="d-flex align-center gap-10">
              <input type="color" id="rs-color" value="#6B7280" style="width:48px;height:36px;border-radius:6px;border:1px solid var(--border);padding:2px;cursor:pointer">
              <span class="fs-12 text-muted">สีที่เลือกจะใช้เป็นสีตัวอักษร พื้นหลังจะสว่างขึ้นอัตโนมัติ</span>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">ลำดับการแสดง</label>
            <input class="form-control" type="number" id="rs-sort" value="99" min="0" style="width:120px">
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-ghost" onclick="closeModal()">ยกเลิก</button>
          <button class="btn btn-primary" onclick="submitAddReason()">💾 เพิ่มเหตุผล</button>
        </div>
      </div></div>`);

      window.submitAddReason = async () => {
        const label = $('rs-label').value.trim();
        const code  = $('rs-code').value.trim().replace(/\s+/g, '_').toLowerCase();
        if (!label || !code) { toast('กรุณากรอกชื่อและรหัส', 'error'); return; }
        const color = $('rs-color').value;
        const res   = await post('/api/settings.php', {
          action: 'add_reason', label, code, icon: $('rs-icon').value,
          color, bg_color: color + '20', sort_order: +$('rs-sort').value,
        });
        toast(res.message, res.success ? 'success' : 'error');
        if (res.success) { closeModal(); loadReasonsSection(); }
      };
    };

    window.openEditReason = (rs) => {
      showModal(`<div class="modal-overlay"><div class="modal" style="max-width:440px">
        <div class="modal-header">แก้ไขเหตุผล<span class="modal-close" onclick="closeModal()">×</span></div>
        <div class="modal-body">
          <div class="form-group">
            <label class="form-label">ชื่อเหตุผล</label>
            <input class="form-control" id="re-label" value="${rs.label}">
          </div>
          <div class="grid-2">
            <div class="form-group">
              <label class="form-label">รหัส</label>
              <input class="form-control" value="${rs.code}" disabled style="opacity:.6;cursor:not-allowed">
            </div>
            <div class="form-group">
              <label class="form-label">ไอคอน</label>
              <input class="form-control" id="re-icon" value="${rs.icon}" maxlength="4">
            </div>
          </div>
          <div class="grid-2">
            <div class="form-group">
              <label class="form-label">สีป้าย</label>
              <input type="color" id="re-color" value="${rs.color}" style="width:48px;height:36px;border-radius:6px;border:1px solid var(--border);padding:2px;cursor:pointer">
            </div>
            <div class="form-group">
              <label class="form-label">ลำดับการแสดง</label>
              <input class="form-control" type="number" id="re-sort" value="${rs.sort_order}" min="0">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-ghost" onclick="closeModal()">ยกเลิก</button>
          <button class="btn btn-primary" onclick="submitEditReason(${rs.id})">💾 บันทึก</button>
        </div>
      </div></div>`);

      window.submitEditReason = async (id) => {
        const color = $('re-color').value;
        const res   = await post('/api/settings.php', {
          action: 'update_reason', id,
          label: $('re-label').value, icon: $('re-icon').value,
          color, bg_color: color + '20', sort_order: +$('re-sort').value,
        });
        toast(res.message, res.success ? 'success' : 'error');
        if (res.success) { closeModal(); loadReasonsSection(); }
      };
    };
  };

  window.switchSettingSection = (s) => { activeSection = s; renderShell(); };
  renderShell();
};

/* ── INIT ─────────────────────────────────────────── */
buildNav();
loadNotifs();
setInterval(loadNotifs, 60000);

// Default page
const defaultPage = can('admin','director','curriculum','accounting') ? 'dashboard' : 'claims';
navigate(defaultPage);
