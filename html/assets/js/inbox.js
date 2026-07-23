/* =====================================================================
   CF7 AI Inbox — AI Inbox page script (list + submission detail + AI
   failure detail — these three views share one page/URL, switched via
   JS, since opening a message is a drill-down of the list rather than a
   separate top-level destination).
   Requires common.js to be loaded first (messages data + shared helpers).
   ===================================================================== */

const INBOX_PAGE_SIZE = 5;

const state = {
  inboxFilters:{search:'', status:'all', priority:'all', category:'all', form:'all'},
  inboxPage:1,
  currentDetailId:null
};

/* ================= IN-PAGE SCREEN SWITCH (list / detail / failure) ============= */
function showInboxScreen(key){
  document.querySelectorAll('.cf7-ai-inbox-screen').forEach(s=>s.classList.remove('cf7-ai-inbox-is-active'));
  const el = document.getElementById('screen-'+key);
  if(el) el.classList.add('cf7-ai-inbox-is-active');
  document.getElementById('main').scrollTo({top:0, behavior:'instant'});
  window.scrollTo({top:0, behavior:'instant'});
}

/* Clicking the "AI Inbox" top-nav item while already on this page should
   just return to the list instead of doing a full page reload. Each of the
   three sub-views (list/detail/failure) carries its own copy of the nav
   bar, so this binds to all of them via a shared class rather than an id. */
document.querySelectorAll('.js-nav-inbox').forEach(function(navInboxLink){
  navInboxLink.addEventListener('click', function(e){
    e.preventDefault();
    showInboxScreen('inbox');
    renderInboxTable();
  });
});

/* ================= FILTERS ================= */
function filteredMessages(){
  const f = state.inboxFilters;
  return messages.filter(m=>{
    if(f.status && f.status!=='all' && m.status!==f.status) return false;
    if(f.priority && f.priority!=='all' && m.priority!==f.priority) return false;
    if(f.category && f.category!=='all' && m.category!==f.category) return false;
    if(f.form && f.form!=='all' && m.form!==f.form) return false;
    if(f.confidence==='low' && !(m.confidence!==null && m.confidence<70)) return false;
    if(f.search){
      const s = f.search.toLowerCase();
      const hay = (m.name+' '+m.email+' '+m.preview+' '+m.form).toLowerCase();
      if(hay.indexOf(s)===-1) return false;
    }
    return true;
  });
}

function clearInboxFilters(rerender){
  state.inboxFilters = {search:'', status:'all', priority:'all', category:'all', form:'all'};
  state.inboxPage = 1;
  syncFilterUI();
  if(rerender!==false) renderInboxTable();
}

function syncFilterUI(){
  const f = state.inboxFilters;
  const map = {form:'filter-form', status:'filter-status', priority:'filter-priority', category:'filter-category'};
  Object.keys(map).forEach(k=>{
    const elx = document.getElementById(map[k]);
    if(elx) elx.value = f[k] || 'all';
  });
  const search = document.getElementById('inbox-search');
  if(search) search.value = f.search || '';
}

/* ================= EXPORT ================= */
function exportInboxCsv(){
  const list = filteredMessages();
  if(list.length===0){ showToast('No messages to export'); return; }
  const headers = ['Customer','Email','Message','Form','Priority','Category','AI Confidence','Status','Received'];
  const statusLabels = {new:'New', review:'Needs Review', reviewed:'Reviewed', drafted:'Drafted', replied:'Replied', failed:'Failed', archived:'Archived'};
  const rows = list.map(m => [
    m.name, m.email, m.full, m.form, m.priority, m.category,
    m.confidence===null || m.confidence===undefined ? '' : m.confidence+'%',
    statusLabels[m.status] || m.status, m.received
  ]);
  downloadCsv('ai-inbox-export.csv', headers, rows);
  showToast('Exported '+list.length+' message'+(list.length===1?'':'s')+' to CSV','success');
}

/* ================= RENDER LIST ================= */
function renderInboxTable(){
  const tbody = document.getElementById('inbox-table-body');
  const fullList = filteredMessages();
  const totalPages = Math.max(1, Math.ceil(fullList.length / INBOX_PAGE_SIZE));
  if(state.inboxPage > totalPages) state.inboxPage = totalPages;
  if(state.inboxPage < 1) state.inboxPage = 1;

  const start = (state.inboxPage - 1) * INBOX_PAGE_SIZE;
  const pageList = fullList.slice(start, start + INBOX_PAGE_SIZE);

  if(fullList.length===0){
    tbody.innerHTML = '<div class="cf7-ai-inbox-grid-table__cell cf7-ai-inbox-grid-table__cell--empty">No messages match your filters.<button class="cf7-ai-inbox-btn--secondary cf7-ai-inbox-btn--clear" id="clear-filters-btn">Clear filters</button></div>';
  } else {
    tbody.innerHTML = pageList.map(rowHtml).join('');
  }

  const rangeStart = fullList.length===0 ? 0 : start+1;
  const rangeEnd = Math.min(start+INBOX_PAGE_SIZE, fullList.length);
  document.getElementById('inbox-count-label').textContent =
    fullList.length===0 ? 'Showing 0 of '+messages.length+' messages' : 'Showing '+rangeStart+' to '+rangeEnd+' of '+fullList.length+' messages';
  document.getElementById('inbox-pager').innerHTML = fullList.length>INBOX_PAGE_SIZE ? paginationHtml('inbox', fullList.length, state.inboxPage, INBOX_PAGE_SIZE) : '';
}

function checkEmptyState(){
  const formsEnabled = true; /* no cross-page settings sync yet — see General Settings on the Settings page */
  document.getElementById('inbox-populated').style.display = formsEnabled ? '' : 'none';
  document.getElementById('inbox-empty').style.display = formsEnabled ? 'none' : '';
}

/* ================= DETAIL SCREEN ================= */
function openDetail(id){
  const m = messages.find(x=>x.id===id);
  if(!m) return;
  if(m.status==='failed'){ openFailure(id); return; }

  document.getElementById('detail-submission-id').textContent = '#'+(4800+m.id);
  document.getElementById('detail-title').textContent = m.subject;
  document.getElementById('detail-meta').textContent = 'From '+m.name+' · '+m.email+' · '+m.form;
  setPriorityBadge('detail-priority-badge', m.priority);
  setStatusBadge('detail-status-badge', m.status);
  document.getElementById('detail-datetime').textContent = m.submittedAt || m.received;

  document.getElementById('detail-avatar').style.background = m.color;
  document.getElementById('detail-avatar').textContent = m.initials;
  document.getElementById('detail-name').textContent = m.name;
  document.getElementById('detail-email').textContent = m.email;
  document.getElementById('detail-phone').textContent = m.phone || '—';
  document.getElementById('detail-location').textContent = m.location || '—';

  document.getElementById('detail-form').textContent = m.form;
  document.getElementById('detail-source').textContent = m.sourcePage || '—';
  document.getElementById('detail-ip').textContent = m.ip || '—';
  document.getElementById('detail-submitted').textContent = m.submittedAt || m.received;

  document.getElementById('detail-fields-subject').textContent = m.subject;
  document.getElementById('detail-message').textContent = m.full;
  const companyRow = document.getElementById('detail-company-row');
  if(m.company){ companyRow.style.display=''; document.getElementById('detail-company').textContent = m.company; }
  else { companyRow.style.display='none'; }
  const attachRow = document.getElementById('detail-attachment-row');
  if(m.attachment){ attachRow.style.display=''; document.getElementById('detail-attachment-name').textContent = m.attachment.name+' ('+m.attachment.size+')'; }
  else { attachRow.style.display='none'; }

  if(m.summary){
    document.getElementById('detail-summary').textContent = m.summary;
    document.getElementById('detail-category-badge').textContent = m.category;
    setPriorityBadge('detail-priority-badge-2', m.priority);
    const color = m.confidence>=70 ? 'var(--conf-good)' : m.confidence>=40 ? 'var(--conf-mid)' : 'var(--conf-low)';
    document.getElementById('detail-confidence-top').textContent = m.confidence+'% confident';
    document.getElementById('detail-confidence-top').style.color = color;
    document.getElementById('detail-confidence-fill').style.width = m.confidence+'%';
    document.getElementById('detail-confidence-fill').style.background = color;
    document.getElementById('detail-reasoning').textContent = m.reasoning;
  } else {
    document.getElementById('detail-summary').textContent = 'No AI analysis available for this submission. Retry the analysis or fill in details manually.';
    document.getElementById('detail-category-badge').textContent = m.category;
    setPriorityBadge('detail-priority-badge-2', m.priority);
    document.getElementById('detail-confidence-top').textContent = '—';
    document.getElementById('detail-confidence-top').style.color = 'var(--text-tertiary)';
    document.getElementById('detail-confidence-fill').style.width = '0%';
    document.getElementById('detail-reasoning').textContent = 'Not available.';
  }

  document.getElementById('detail-recipient').value = m.email;
  document.getElementById('detail-subject').value = 'Re: '+m.subject;
  document.getElementById('detail-reply-body').innerText = m.draft || '';
  document.getElementById('detail-reply-body').setAttribute('contenteditable','true');
  document.getElementById('detail-reply-body').style.background = '';
  document.getElementById('detail-preview').textContent = 'Preview';
  document.getElementById('detail-template-select').value = '';
  document.getElementById('detail-draft-status').textContent = 'Draft auto-saved just now';

  document.getElementById('detail-timeline').innerHTML =
    '<div class="cf7-ai-inbox-timeline__item"><div class="cf7-ai-inbox-timeline__dot cf7-ai-inbox-timeline__dot--ok"><svg width="7" height="7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="color:var(--conf-good);"><path d="M20 6L9 17l-5-5"/></svg></div><div class="cf7-ai-inbox-timeline__text">AI analysis completed — '+(m.confidence||0)+'% confidence</div><div class="cf7-ai-inbox-timeline__meta">System · '+m.received+'</div></div>'
    +'<div class="cf7-ai-inbox-timeline__item"><div class="cf7-ai-inbox-timeline__dot cf7-ai-inbox-timeline__dot--neutral"><svg width="7" height="7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="color:var(--low);"><path d="M4 4h16v14H8l-4 4V4z"/></svg></div><div class="cf7-ai-inbox-timeline__text">Submission received via '+m.form+'</div><div class="cf7-ai-inbox-timeline__meta">System · '+m.received+'</div></div>';

  state.currentDetailId = id;
  showInboxScreen('detail');
}

function openFailure(id){
  const m = messages.find(x=>x.id===id);
  if(!m) return;
  document.getElementById('failure-crumb').textContent = 'Submission #'+(4800+m.id);
  document.getElementById('failure-title').textContent = m.subject;
  document.getElementById('failure-meta').textContent = 'From '+m.name+' · '+m.email+' · '+m.form;
  document.getElementById('failure-received').textContent = m.received;
  document.getElementById('failure-message').textContent = m.full;
  state.currentDetailId = id;
  showInboxScreen('ai-failure');
}

/* ================= EVENT DELEGATION ================= */
document.addEventListener('click', function(e){
  const menuItemEl = e.target.closest('.cf7-ai-inbox-row-menu__item[data-menu-action]');
  if(menuItemEl){
    const action = menuItemEl.dataset.menuAction;
    const id = parseInt(menuItemEl.dataset.key, 10);
    closeRowMenu();
    const m = messages.find(x=>x.id===id);
    if(!m) return;
    if(action==='reviewed'){ m.status='reviewed'; renderInboxTable(); showToast('Marked as reviewed','success'); }
    else if(action==='archive'){ m.status='archived'; renderInboxTable(); showToast('Message archived'); }
    else if(action==='retry'){
      showToast('Retrying analysis…');
      setTimeout(()=>{
        m.status='new'; m.confidence=87; m.summary='Manually re-analyzed after a provider timeout. Please confirm category and priority.';
        m.reasoning='Retried successfully on the second attempt; category and priority carried over from the original request context.';
        renderInboxTable();
        showToast('Analysis completed successfully','success');
      }, 900);
    }
    return;
  }

  const actionEl = e.target.closest('[data-action]');
  if(actionEl){
    const action = actionEl.dataset.action;
    if(action==='more'){
      e.stopPropagation();
      openRowMenu(actionEl, 'message', parseInt(actionEl.dataset.id, 10));
      return;
    }
    const id = parseInt(actionEl.dataset.id, 10);
    const m = messages.find(x=>x.id===id);
    if(!m) return;
    if(action==='view' || action==='reply'){ closeRowMenu(); openDetail(id); }
    return;
  }
});

document.addEventListener('input', function(e){
  if(e.target.id==='inbox-search'){ state.inboxFilters.search = e.target.value; state.inboxPage = 1; renderInboxTable(); }
});
document.addEventListener('change', function(e){
  if(e.target.classList.contains('cf7-ai-inbox-filter-select')){
    state.inboxFilters[e.target.dataset.filter] = e.target.value;
    state.inboxPage = 1;
    renderInboxTable();
  }
});
document.addEventListener('click', function(e){
  const pagerBtn = e.target.closest('.cf7-ai-inbox-pager__btn');
  if(pagerBtn && !pagerBtn.disabled){
    state.inboxPage = parseInt(pagerBtn.dataset.page, 10);
    renderInboxTable();
    document.getElementById('main').scrollTo({top:0, behavior:'smooth'});
    return;
  }
  if(e.target.id==='clear-filters-btn'){ clearInboxFilters(); }
});

document.getElementById('inbox-refresh-btn').addEventListener('click', function(){
  this.classList.add('cf7-ai-inbox-is-spinning');
  setTimeout(()=>{ this.classList.remove('cf7-ai-inbox-is-spinning'); showToast('Inbox refreshed','success'); }, 700);
});
document.getElementById('inbox-export-btn').addEventListener('click', exportInboxCsv);

/* ================= DETAIL: REPLY COMPOSER ================= */
document.getElementById('detail-regenerate-analysis').addEventListener('click', function(){ showToast('Re-running AI analysis…'); setTimeout(()=>showToast('Analysis regenerated','success'), 800); });

document.getElementById('detail-save-draft').addEventListener('click', function(){
  const m = messages.find(x=>x.id===state.currentDetailId);
  if(!m) return;
  m.draft = document.getElementById('detail-reply-body').innerText;
  document.getElementById('detail-draft-status').textContent = 'Draft saved just now';
  showToast('Draft saved','success');
});

document.getElementById('detail-preview').addEventListener('click', function(){
  const body = document.getElementById('detail-reply-body');
  const previewing = body.getAttribute('contenteditable')==='false';
  if(previewing){
    body.setAttribute('contenteditable','true');
    body.style.background = '';
    this.textContent = 'Preview';
  } else {
    body.setAttribute('contenteditable','false');
    body.style.background = 'var(--surface-2)';
    this.textContent = 'Edit';
    showToast('Previewing reply as the customer will see it');
  }
});

document.getElementById('detail-regenerate-reply').addEventListener('click', function(){
  const m = messages.find(x=>x.id===state.currentDetailId);
  if(!m) return;
  showToast('Regenerating reply draft…');
  setTimeout(()=>{
    const base = (m.draft || '').split('\n\n')[0] || ('Hi '+m.name.split(' ')[0]+',');
    const regenerated = base+'\n\nThanks again for reaching out — following up with a bit more detail based on what you shared. Let me know if this covers what you need, or if you\'d like me to go deeper on anything.\n\nBest,\nAminul';
    document.getElementById('detail-reply-body').innerText = regenerated;
    document.getElementById('detail-draft-status').textContent = 'Draft regenerated just now';
    showToast('Reply draft regenerated','success');
  }, 800);
});

/* Templates */
const REPLY_TEMPLATES = {
  ack: (m)=> 'Hi '+m.name.split(' ')[0]+',\n\nThanks for reaching out. We\'ve received your message and someone from our team will follow up shortly.\n\nBest,\nAminul',
  refund: (m)=> 'Hi '+m.name.split(' ')[0]+',\n\nThanks for letting us know. We\'re reviewing your request and will follow up with next steps within 1-2 business days.\n\nBest,\nAminul',
  blank: ()=> ''
};
document.getElementById('detail-template-select').addEventListener('change', function(){
  const m = messages.find(x=>x.id===state.currentDetailId);
  if(!m) return;
  const val = this.value;
  const body = document.getElementById('detail-reply-body');
  if(val==='ai'){ body.innerText = m.draft || ''; showToast('Loaded suggested AI reply'); }
  else if(val && REPLY_TEMPLATES[val]){ body.innerText = REPLY_TEMPLATES[val](m); showToast('Template applied'); }
});

/* Rich text formatting */
function applyFormat(cmd, value){
  document.getElementById('detail-reply-body').focus();
  document.execCommand(cmd, false, value || null);
  syncToolbarState();
}
[['fmt-bold','bold'], ['fmt-italic','italic'], ['fmt-underline','underline'], ['fmt-list','insertUnorderedList']].forEach(([id, cmd])=>{
  const el = document.getElementById(id);
  el.addEventListener('mousedown', e=> e.preventDefault());
  el.addEventListener('click', ()=> applyFormat(cmd));
});
document.getElementById('fmt-link').addEventListener('mousedown', e=> e.preventDefault());
document.getElementById('fmt-link').addEventListener('click', function(){
  const url = prompt('Link URL:', 'https://');
  if(url) applyFormat('createLink', url);
});
document.getElementById('fmt-block').addEventListener('change', function(){
  applyFormat('formatBlock', this.value);
});
function syncToolbarState(){
  try{
    document.getElementById('fmt-bold').classList.toggle('cf7-ai-inbox-is-active', document.queryCommandState('bold'));
    document.getElementById('fmt-italic').classList.toggle('cf7-ai-inbox-is-active', document.queryCommandState('italic'));
    document.getElementById('fmt-underline').classList.toggle('cf7-ai-inbox-is-active', document.queryCommandState('underline'));
    document.getElementById('fmt-list').classList.toggle('cf7-ai-inbox-is-active', document.queryCommandState('insertUnorderedList'));
  } catch(e){}
}
document.getElementById('detail-reply-body').addEventListener('keyup', syncToolbarState);
document.getElementById('detail-reply-body').addEventListener('mouseup', syncToolbarState);
document.getElementById('detail-reply-body').addEventListener('input', function(){
  document.getElementById('detail-draft-status').textContent = 'Editing…';
});

/* ================= FAILURE SCREEN ================= */
document.getElementById('failure-retry-btn').addEventListener('click', function(){
  const btn = this;
  btn.disabled = true; btn.textContent = 'Retrying…';
  showToast('Retrying analysis…');
  setTimeout(()=>{
    const id = state.currentDetailId;
    const m = messages.find(x=>x.id===id);
    if(m){
      m.status='new'; m.confidence=87;
      m.summary='Manually re-analyzed after a provider timeout. Please confirm category and priority.';
      m.reasoning='Retried successfully on the second attempt; category and priority carried over from the original request context.';
      renderInboxTable();
      showToast('Analysis completed successfully','success');
      openDetail(id);
    }
    btn.disabled = false; btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:13px;height:13px;"><path d="M23 4v6h-6M1 20v-6h6"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>Retry';
  }, 900);
});
document.getElementById('failure-manual-btn').addEventListener('click', function(){
  const id = state.currentDetailId;
  const m = messages.find(x=>x.id===id);
  if(m){ m.status='review'; renderInboxTable(); showToast('Marked for manual review'); openDetail(id); }
});

/* ================= SEND-REPLY MODAL ================= */
document.getElementById('open-reply-modal').addEventListener('click', function(){
  const m = messages.find(x=>x.id===state.currentDetailId);
  if(!m) return;
  document.getElementById('modal-body-text').innerHTML = 'This reply will be emailed to <b style="color:var(--text-primary);">'+m.email+'</b> and the message status will change to <b style="color:var(--text-primary);">Replied</b>. This action cannot be undone.<div class="cf7-ai-inbox-modal__preview" id="modal-preview-text"><b>Subject:</b> Re: '+m.subject+'<br><br>'+(document.getElementById('detail-reply-body').innerText.slice(0,140))+'…</div>';
  document.getElementById('reply-modal-overlay').style.display = 'flex';
});
document.getElementById('modal-confirm-send').addEventListener('click', function(){
  const m = messages.find(x=>x.id===state.currentDetailId);
  document.getElementById('reply-modal-overlay').style.display = 'none';
  if(m){
    m.status = 'replied';
    renderInboxTable();
    showToast('Reply sent to '+m.email,'success');
    openDetail(m.id);
  }
});

/* ================= INIT ================= */
(function init(){
  const status = getQueryParam('status');
  const priority = getQueryParam('priority');
  const category = getQueryParam('category');
  const confidence = getQueryParam('confidence');
  const search = getQueryParam('search');
  const view = getQueryParam('view');

  if(status) state.inboxFilters.status = status;
  if(priority) state.inboxFilters.priority = priority;
  if(category) state.inboxFilters.category = category;
  if(confidence) state.inboxFilters.confidence = confidence;
  if(search) state.inboxFilters.search = search;

  syncFilterUI();
  checkEmptyState();
  renderInboxTable();

  if(view){
    const id = parseInt(view, 10);
    if(messages.some(m=>m.id===id)) openDetail(id);
  }
})();
