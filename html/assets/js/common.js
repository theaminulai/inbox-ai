/* =====================================================================
   CF7 AI Inbox — shared mock data + helpers used across multiple pages.
   Load this file BEFORE any page-specific script (dashboard.js, inbox.js,
   contacts.js, analytics.js, settings.js).

   This is still a static, client-side mockup — the same role the original
   single-file docs/cf7-ai-inbox-connected.html played. Data does not
   persist or sync across pages (each page is a real, separate document
   now); that will come once this is wired to the real WordPress backend.
   ===================================================================== */

/* ================= MOCK DATA ================= */
const messages = [
  {id:1, name:'Rashida Karim', initials:'RK', color:'#3A5CF6', email:'rashida.karim@nexbuild.co', form:'Sales Contact',
   subject:'Bulk enterprise pricing request',
   preview:"Interested in a bulk quote for the enterprise plan — 40 seats, need pricing by Friday…",
   full:"Interested in a bulk quote for the enterprise plan — 40 seats, need pricing by Friday if possible. Also want to know if there's a discount for annual billing and whether SSO is included at this tier.",
   priority:'urgent', category:'Sales', confidence:94, status:'new', received:'2h ago',
   summary:"A qualified enterprise lead requesting a 40-seat bulk quote with a Friday deadline. Asking specifically about annual-billing discounts and SSO availability — both are buying-decision signals worth a fast, detailed response.",
   reasoning:'Flagged urgent due to an explicit deadline ("by Friday") combined with a 40-seat volume signal. Category set to Sales based on pricing and plan-tier language. High confidence driven by clear, unambiguous intent language.',
   draft:"Hi Rashida,\n\nThanks for reaching out about the enterprise plan for NexBuild's team of 40. Happy to put a formal quote together for you before Friday.\n\nTo confirm: our annual-billing plans include a 15% discount versus monthly, and SSO (SAML/OIDC) is included at the Enterprise tier by default.\n\nI'll send over a detailed quote shortly — let me know if you'd like a short call to walk through it.\n\nBest,\nAminul",
   phone:'+1 (415) 555-0142', location:'San Francisco, USA', company:'NexBuild Technologies',
   sourcePage:'https://nexbuild.co/pricing/enterprise', ip:'104.28.14.62', submittedAt:'Jul 20, 2026 at 2:14 PM',
   attachment:{name:'requirements.pdf', size:'245 KB'}},
  {id:2, name:'Jahid Ansari', initials:'JA', color:'#D93B3B', email:'jahid.a@corpmail.com', form:'Support Form',
   subject:'URGENT: checkout returning 500 errors',
   preview:'URGENT: our production site is down, checkout page returns 500…',
   full:'URGENT: our production site is down, checkout page returns 500 errors for all customers since about 20 minutes ago. Please escalate immediately.',
   priority:'urgent', category:'Support', confidence:98, status:'new', received:'18m ago',
   summary:'A live production outage affecting checkout for all customers. Needs an immediate response from the support team.',
   reasoning:'Flagged urgent due to explicit "URGENT" language and description of a live, customer-facing outage affecting all users.',
   draft:"Hi Jahid,\n\nThanks for flagging this immediately — I've escalated the checkout 500 errors to our engineering team as a top priority.\n\nI'll follow up as soon as we have a fix or a status update, and will keep you posted every 30 minutes until resolved.\n\nBest,\nAminul",
   phone:'+1 (212) 555-0187', location:'New York, USA', company:'CorpMail Inc.',
   sourcePage:'https://corpmail.com/support', ip:'203.0.113.45', submittedAt:'Jul 20, 2026 at 6:42 PM',
   attachment:null},
  {id:3, name:'Tanvir Ahmed', initials:'TA', color:'#1F9254', email:'t.ahmed@studiomail.com', form:'Support Form',
   subject:'Export fails at step 3 (E-204)',
   preview:'My export keeps failing on step 3 with error code E-204…',
   full:'My export keeps failing on step 3 with error code E-204, tried three times with different browsers.',
   priority:'high', category:'Support', confidence:91, status:'review', received:'4h ago',
   summary:'A recurring export failure with a specific error code, already retried multiple times by the customer.',
   reasoning:'High priority due to a blocked workflow; confidence reduced slightly pending confirmation of what error E-204 refers to.',
   draft:"Hi Tanvir,\n\nSorry for the trouble — error E-204 usually points to a timeout on larger exports. Could you let me know roughly how many records you're exporting?\n\nIn the meantime, try splitting the export into two smaller batches, which usually works around this.\n\nBest,\nAminul",
   phone:'+880 1711-234567', location:'Dhaka, Bangladesh', company:'Studio Mail',
   sourcePage:'https://studiomail.com/help', ip:'103.87.65.12', submittedAt:'Jul 20, 2026 at 12:30 PM',
   attachment:{name:'error-log.txt', size:'12 KB'}},
  {id:4, name:'Sultana Haque', initials:'SH', color:'#DA8A2E', email:'sultana.h@gmail.com', form:'Billing Form',
   subject:'Refund request — order #88213',
   preview:'I want a refund for my last order, item arrived damaged…',
   full:'I want a refund for my last order, item arrived damaged, order #88213.',
   priority:'high', category:'Billing', confidence:52, status:'review', received:'6h ago',
   summary:'A refund request for a damaged item tied to a specific order number.',
   reasoning:'Lower confidence: the message could indicate either a refund or a replacement request, and order details need verification.',
   draft:"Hi Sultana,\n\nI'm sorry to hear order #88213 arrived damaged. I can process either a full refund or a replacement — whichever you'd prefer.\n\nCould you reply with a photo of the damage so I can also flag this with our fulfillment team?\n\nBest,\nAminul",
   phone:'+1 (312) 555-0199', location:'Chicago, USA', company:'',
   sourcePage:'https://example.com/billing', ip:'198.51.100.23', submittedAt:'Jul 20, 2026 at 10:15 AM',
   attachment:{name:'damaged-item.jpg', size:'1.1 MB'}},
  {id:5, name:'Mahfuz Hossain', initials:'MH', color:'#6B4CE6', email:'mahfuz@partnerworks.io', form:'General Contact',
   subject:"Reseller partnership — South Asia",
   preview:"We'd like to explore a reseller partnership for the South Asia region…",
   full:"We'd like to explore a reseller partnership for the South Asia region, can we set up a call next week?",
   priority:'normal', category:'Partnership', confidence:88, status:'reviewed', received:'1d ago',
   summary:'A partnership inquiry proposing a reseller relationship in South Asia.',
   reasoning:'Normal priority — no urgency signaled, straightforward partnership inquiry.',
   draft:"Hi Mahfuz,\n\nThanks for reaching out — a reseller partnership for South Asia sounds interesting. I'd be happy to set up a call next week.\n\nDoes Tuesday or Wednesday afternoon work on your end?\n\nBest,\nAminul",
   phone:'+880 1911-556677', location:'Dhaka, Bangladesh', company:'PartnerWorks',
   sourcePage:'https://partnerworks.io/contact', ip:'103.21.244.9', submittedAt:'Jul 19, 2026 at 5:00 PM',
   attachment:null},
  {id:6, name:'Nusrat Islam', initials:'NI', color:'#1F9254', email:'nusrat.islam@outlook.com', form:'Support Form',
   subject:'Re: invoice confirmation',
   preview:'Thank you for the quick turnaround, just confirming the invoice was received…',
   full:'Thank you for the quick turnaround, just confirming the invoice was received on your end.',
   priority:'low', category:'Support', confidence:97, status:'replied', received:'1d ago',
   summary:'A short confirmation message, no action needed beyond acknowledgement.',
   reasoning:'Low priority — informational message with no open request.',
   draft:"Hi Nusrat,\n\nConfirmed — we received the invoice, thanks for the quick payment!\n\nBest,\nAminul",
   phone:'+880 1611-998877', location:'Chattogram, Bangladesh', company:'',
   sourcePage:'https://outlook-relay.com/support', ip:'103.94.12.5', submittedAt:'Jul 19, 2026 at 4:20 PM',
   attachment:null},
  {id:7, name:'Farhan Rahman', initials:'FR', color:'#9AA1AC', email:'farhan.r@buildmail.net', form:'General Contact',
   subject:'Question about ToS section 4.2',
   preview:'General question about your terms of service, section 4.2 regarding data…',
   full:'General question about your terms of service, section 4.2 regarding data retention after account closure. Could someone clarify how long data is kept?',
   priority:'normal', category:'Other', confidence:null, status:'failed', received:'2d ago', summary:null, reasoning:null, draft:'',
   phone:'+1 (503) 555-0164', location:'Portland, USA', company:'',
   sourcePage:'https://example.com/contact', ip:'192.0.2.88', submittedAt:'Jul 18, 2026 at 10:05 AM',
   attachment:null},
  {id:8, name:'unknown-sender-221', initials:'SP', color:'#9AA1AC', email:'promo@bulkmailer.ru', form:'General Contact',
   subject:'Exclusive offer inside!!!',
   preview:"Congratulations! You've been selected for our exclusive offer…",
   full:"Congratulations! You've been selected for our exclusive offer, click here to claim your prize.",
   priority:'low', category:'Spam', confidence:99, status:'archived', received:'3d ago',
   summary:'Automated promotional spam, no genuine inquiry.', reasoning:'Very high confidence spam classification based on promotional language patterns.', draft:'',
   phone:'', location:'Unknown', company:'',
   sourcePage:'https://example.com/contact', ip:'45.155.204.11', submittedAt:'Jul 19, 2026 at 8:47 AM',
   attachment:null},
  {id:9, name:'Priya Chowdhury', initials:'PC', color:'#3A5CF6', email:'priya.c@retailgroup.com', form:'Sales Contact',
   subject:"Following up on last week's demo",
   preview:'Following up on the demo we had last week, ready to move forward…',
   full:"Following up on the demo we had last week, ready to move forward with the standard plan for 12 seats.",
   priority:'high', category:'Sales', confidence:90, status:'drafted', received:'5h ago',
   summary:'A warm sales lead ready to proceed, reply drafted and awaiting approval.',
   reasoning:'High priority given clear buying intent; a draft reply has already been generated and is waiting for manual approval.',
   draft:"Hi Priya,\n\nGreat to hear you're ready to move forward! I'll get the standard plan set up for 12 seats and send over the paperwork today.\n\nBest,\nAminul",
   phone:'+91 98765 43210', location:'Mumbai, India', company:'Retail Group Pvt Ltd',
   sourcePage:'https://retailgroup.com/demo', ip:'157.32.4.201', submittedAt:'Jul 20, 2026 at 9:05 AM',
   attachment:{name:'seat-count.xlsx', size:'38 KB'}}
];

/* ================= QUERY STRING HELPER ================= */
function getQueryParam(name){
  return new URLSearchParams(window.location.search).get(name);
}

/* ================= CSV EXPORT ================= */
function csvEscape(val){
  const s = String(val===null || val===undefined ? '' : val);
  return /[",\n]/.test(s) ? '"'+s.replace(/"/g,'""')+'"' : s;
}
function downloadCsv(filename, headers, rows){
  const lines = [headers.map(csvEscape).join(',')].concat(
    rows.map(r => r.map(csvEscape).join(','))
  );
  const blob = new Blob([lines.join('\r\n')], {type:'text/csv;charset=utf-8;'});
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
}

/* ================= TOAST ================= */
function showToast(msg, type){
  const c = document.getElementById('toast-container');
  if(!c) return;
  const el = document.createElement('div');
  el.className = 'cf7-ai-inbox-toast' + (type ? ' cf7-ai-inbox-toast--'+type : '');
  const icon = type==='success' ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M20 6L9 17l-5-5"/></svg>'
             : type==='error' ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg>'
             : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>';
  el.innerHTML = icon + '<span>' + msg + '</span>';
  c.appendChild(el);
  requestAnimationFrame(()=> el.classList.add('cf7-ai-inbox-is-visible'));
  setTimeout(()=>{ el.classList.remove('cf7-ai-inbox-is-visible'); setTimeout(()=> el.remove(), 250); }, 3000);
}

/* ================= "TEST CONNECTION" BUTTON (dashboard + settings) ============= */
function testConnection(lastCheckedId, btn){
  const originalText = btn.textContent;
  btn.disabled = true; btn.textContent = 'Testing…';
  setTimeout(()=>{
    btn.disabled = false; btn.textContent = originalText;
    if(lastCheckedId) document.getElementById(lastCheckedId).textContent = 'just now';
    showToast('Connection successful','success');
  }, 800);
}

/* ================= BADGES / CELLS ================= */
function priorityBadgeHtml(p){
  const map = {urgent:['Urgent','var(--urgent)','cf7-ai-inbox-badge--urgent'], high:['High','var(--high)','cf7-ai-inbox-badge--high'], normal:['Normal','var(--normal)','cf7-ai-inbox-badge--normal'], low:['Low','var(--low)','cf7-ai-inbox-badge--low']};
  const m = map[p] || map.normal;
  return '<span class="cf7-ai-inbox-badge '+m[2]+'"><span class="cf7-ai-inbox-badge__dot" style="background:'+m[1]+';"></span>'+m[0]+'</span>';
}
function statusBadgeHtml(s){
  const map = {new:['New','cf7-ai-inbox-status--new'], review:['Needs Review','cf7-ai-inbox-status--review'], reviewed:['Reviewed','cf7-ai-inbox-status--reviewed'], drafted:['Drafted','cf7-ai-inbox-status--drafted'], replied:['Replied','cf7-ai-inbox-status--replied'], failed:['Failed','cf7-ai-inbox-status--failed'], archived:['Archived','cf7-ai-inbox-status--archived']};
  const m = map[s] || map.new;
  return '<span class="cf7-ai-inbox-status '+m[1]+'">'+m[0]+'</span>';
}
function setPriorityBadge(elId, p){
  const map = {urgent:['Urgent','var(--urgent)','cf7-ai-inbox-badge--urgent'], high:['High','var(--high)','cf7-ai-inbox-badge--high'], normal:['Normal','var(--normal)','cf7-ai-inbox-badge--normal'], low:['Low','var(--low)','cf7-ai-inbox-badge--low']};
  const m = map[p] || map.normal;
  const el = document.getElementById(elId);
  if(!el) return;
  el.className = 'cf7-ai-inbox-badge '+m[2];
  el.innerHTML = '<span class="cf7-ai-inbox-badge__dot" style="background:'+m[1]+';"></span>'+m[0];
}
function setStatusBadge(elId, s){
  const map = {new:['New','cf7-ai-inbox-status--new'], review:['Needs Review','cf7-ai-inbox-status--review'], reviewed:['Reviewed','cf7-ai-inbox-status--reviewed'], drafted:['Drafted','cf7-ai-inbox-status--drafted'], replied:['Replied','cf7-ai-inbox-status--replied'], failed:['Failed','cf7-ai-inbox-status--failed'], archived:['Archived','cf7-ai-inbox-status--archived']};
  const m = map[s] || map.new;
  const el = document.getElementById(elId);
  if(!el) return;
  el.className = 'cf7-ai-inbox-status '+m[1];
  el.textContent = m[0];
}
function confidenceCellHtml(c){
  if(c===null || c===undefined) return '<div class="cf7-ai-inbox-confidence"><div class="cf7-ai-inbox-confidence__value" style="color:var(--text-tertiary);">—</div><div class="cf7-ai-inbox-confidence__track"><div class="cf7-ai-inbox-confidence__fill" style="width:0%;"></div></div></div>';
  const color = c>=70 ? 'var(--conf-good)' : c>=40 ? 'var(--conf-mid)' : 'var(--conf-low)';
  const warn = c<70 ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z"/></svg>' : '';
  return '<div class="cf7-ai-inbox-confidence"><div class="cf7-ai-inbox-confidence__value" style="color:'+color+';">'+warn+c+'%</div><div class="cf7-ai-inbox-confidence__track"><div class="cf7-ai-inbox-confidence__fill" style="width:'+c+'%;background:'+color+';"></div></div></div>';
}

/* ================= PAGINATION ================= */
function paginationHtml(pagerId, totalItems, currentPage, pageSize){
  const totalPages = Math.max(1, Math.ceil(totalItems / pageSize));
  if(currentPage > totalPages) currentPage = totalPages;
  const prevIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg>';
  const nextIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 18l6-6-6-6"/></svg>';
  const btn = (label, page, disabled, active) =>
    '<button class="cf7-ai-inbox-pager__btn'+(active ? ' cf7-ai-inbox-is-active' : '')+'"'+(disabled ? ' disabled' : '')+' data-pager="'+pagerId+'" data-page="'+page+'">'+label+'</button>';

  let pages = [];
  if(totalPages <= 7){
    for(let i=1;i<=totalPages;i++) pages.push(i);
  } else {
    pages.push(1);
    if(currentPage > 3) pages.push('…');
    for(let i=Math.max(2,currentPage-1); i<=Math.min(totalPages-1,currentPage+1); i++) pages.push(i);
    if(currentPage < totalPages-2) pages.push('…');
    pages.push(totalPages);
  }

  let html = '<div class="cf7-ai-inbox-pager">';
  html += btn(prevIcon, currentPage-1, currentPage<=1, false);
  pages.forEach(p=>{
    html += p==='…' ? '<span class="cf7-ai-inbox-pager__ellipsis">…</span>' : btn(String(p), p, false, p===currentPage);
  });
  html += btn(nextIcon, currentPage+1, currentPage>=totalPages, false);
  html += '</div>';
  return html;
}

/* ================= ROW ACTIONS / ROW MENUS (messages + contacts) ============= */
function rowActionsHtml(m){
  const icons =
      '<div class="cf7-ai-inbox-btn--icon" data-action="view" data-id="'+m.id+'" title="View"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg></div>'
    + '<div class="cf7-ai-inbox-btn--icon" data-action="reply" data-id="'+m.id+'" title="Reply"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 5h16v14H4z"/><path d="M4 6l8 7 8-7"/></svg></div>'
    + '<div class="cf7-ai-inbox-btn--icon" data-action="more" data-kind="message" data-id="'+m.id+'" title="More actions"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/></svg></div>';
  return '<div class="cf7-ai-inbox-row-actions">'+icons+'</div>';
}

/* Extra actions live inside the "more" dropdown, contextual to status */
function messageRowMenuItems(m){
  const items = [];
  if(m.status==='failed'){
    items.push({action:'retry', label:'Retry analysis', icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 4v6h-6M1 20v-6h6"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>'});
  } else {
    if(m.status==='new' || m.status==='review'){
      items.push({action:'reviewed', label:'Mark reviewed', icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>'});
    }
  }
  if(m.status!=='archived'){
    items.push({action:'archive', label:'Archive', icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 8v13H3V8M1 3h22v5H1z"/><path d="M10 12h4"/></svg>', danger:true});
  }
  return items;
}

function contactRowMenuItems(){
  return [
    {action:'view', label:'View messages', icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>'},
    {action:'delete', label:'Delete contact', icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2m3 0-1 14a2 2 0 01-2 2H7a2 2 0 01-2-2L4 6"/><path d="M10 11v6M14 11v6"/></svg>', danger:true}
  ];
}

function openRowMenu(anchorEl, kind, key){
  const existing = document.getElementById('row-menu');
  if(!existing) return;
  const openKey = kind + ':' + key;
  if(existing.dataset.openFor === openKey && existing.style.display==='block'){
    closeRowMenu();
    return;
  }
  let items;
  if(kind==='contact'){
    items = contactRowMenuItems();
  } else if(kind==='campaign'){
    // campaignRowMenuItems()/campaigns are defined locally in campaign.js,
    // only loaded on campaign.html — guarded so other pages don't error.
    items = (typeof campaignRowMenuItems === 'function') ? campaignRowMenuItems() : [];
  } else {
    const m = messages.find(x=>x.id===key);
    items = m ? messageRowMenuItems(m) : [];
  }
  existing.innerHTML = items.length
    ? items.map(it=>'<div class="cf7-ai-inbox-row-menu__item'+(it.danger ? ' cf7-ai-inbox-row-menu__item--danger' : '')+'" data-menu-action="'+it.action+'" data-kind="'+kind+'" data-key="'+key+'">'+it.icon+'<span>'+it.label+'</span></div>').join('')
    : '<div class="cf7-ai-inbox-row-menu__item" style="color:var(--text-tertiary);cursor:default;">No further actions</div>';
  const rect = anchorEl.getBoundingClientRect();
  existing.style.display = 'block';
  existing.dataset.openFor = openKey;
  let left = rect.right - existing.offsetWidth;
  if(left < 8) left = 8;
  existing.style.top = (rect.bottom + 6) + 'px';
  existing.style.left = left + 'px';
}
function closeRowMenu(){
  const menu = document.getElementById('row-menu');
  if(!menu) return;
  menu.style.display = 'none';
  menu.dataset.openFor = '';
}
document.addEventListener('click', function(e){
  if(!e.target.closest('[data-action="more"]') && !e.target.closest('.cf7-ai-inbox-row-menu__item')){
    closeRowMenu();
  }
});

function rowHtml(m){
  const cell = (content, extra) => '<div class="cf7-ai-inbox-grid-table__cell'+(extra?' '+extra:'')+'" role="cell">'+content+'</div>';
  return '<div class="cf7-ai-inbox-grid-table__row'+(m.status==='archived' ? ' cf7-ai-inbox-is-archived' : '')+'" role="row">'
    + cell('<div class="cf7-ai-inbox-avatar" style="background:'+m.color+';">'+m.initials+'</div><div><div class="cf7-ai-inbox-customer__name cf7-ai-inbox-customer__link" data-action="view" data-id="'+m.id+'">'+m.name+'</div><div class="cf7-ai-inbox-customer__email cf7-ai-inbox-customer__link" data-action="view" data-id="'+m.id+'">'+m.email+'</div></div>', 'cf7-ai-inbox-customer__cell')
    + cell('<span class="cf7-ai-inbox-message-preview">'+m.preview+'</span>')
    + cell(m.form)
    + cell(priorityBadgeHtml(m.priority))
    + cell(m.category)
    + cell(confidenceCellHtml(m.confidence))
    + cell(statusBadgeHtml(m.status))
    + cell('<span style="font-family:var(--mono);color:var(--text-secondary);">'+m.received+'</span>')
    + cell(rowActionsHtml(m))
    + '</div>';
}

/* ================= GENERIC MODAL CLOSE WIRING ================= */
document.querySelectorAll('[data-close-modal]').forEach(el=>{
  el.addEventListener('click', ()=>{
    const overlayId = el.dataset.closeModal || 'reply-modal-overlay';
    const overlay = document.getElementById(overlayId);
    if(overlay) overlay.style.display = 'none';
  });
});
