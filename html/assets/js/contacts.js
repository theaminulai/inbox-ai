/* =====================================================================
   Inbox AI — Contacts page script.
   Requires common.js to be loaded first (messages data + shared helpers).
   ===================================================================== */

const CONTACTS_PAGE_SIZE = 5;

const state = {
  contactsPage:1,
  contactsFilters:{search:'', category:'all', priority:'all'},
  deletedContacts:new Set()
};

function contactsFromMessages(){
  const byEmail = {};
  messages.forEach(m=>{
    if(!byEmail[m.email]) byEmail[m.email] = {name:m.name, initials:m.initials, color:m.color, email:m.email, category:m.category, priority:m.priority, count:0, replied:0, received:m.received};
    byEmail[m.email].count++;
    if(m.status==='replied') byEmail[m.email].replied++;
  });
  return Object.values(byEmail).filter(c=> !state.deletedContacts.has(c.email));
}
function filteredContacts(){
  const f = state.contactsFilters;
  return contactsFromMessages().filter(c=>{
    if(f.category && f.category!=='all' && c.category!==f.category) return false;
    if(f.priority && f.priority!=='all' && c.priority!==f.priority) return false;
    if(f.search){
      const s = f.search.toLowerCase();
      if((c.name+' '+c.email).toLowerCase().indexOf(s)===-1) return false;
    }
    return true;
  });
}

function renderContacts(){
  const tbody = document.getElementById('contacts-table-body');
  const fullList = filteredContacts();

  const totalPages = Math.max(1, Math.ceil(fullList.length / CONTACTS_PAGE_SIZE));
  if(state.contactsPage > totalPages) state.contactsPage = totalPages;
  if(state.contactsPage < 1) state.contactsPage = 1;
  const start = (state.contactsPage - 1) * CONTACTS_PAGE_SIZE;
  const list = fullList.slice(start, start + CONTACTS_PAGE_SIZE);

  tbody.innerHTML = list.length ? list.map(c=>
    '<div class="inboxai-grid-table__row" role="row">'
    +'<div class="inboxai-grid-table__cell inboxai-customer__cell" role="cell"><div class="inboxai-avatar" style="background:'+c.color+';">'+c.initials+'</div><a class="inboxai-customer__name inboxai-customer__link" href="inbox.html?search='+encodeURIComponent(c.email)+'">'+c.name+'</a></div>'
    +'<a class="inboxai-grid-table__cell inboxai-customer__link" href="inbox.html?search='+encodeURIComponent(c.email)+'" role="cell" style="color:var(--text-secondary);">'+c.email+'</a>'
    +'<div class="inboxai-grid-table__cell" role="cell">'+c.category+'</div>'
    +'<div class="inboxai-grid-table__cell" role="cell">'+priorityBadgeHtml(c.priority)+'</div>'
    +'<div class="inboxai-grid-table__cell" role="cell"><span style="font-family:var(--mono);">'+c.count+'</span></div>'
    +'<div class="inboxai-grid-table__cell" role="cell"><span style="font-family:var(--mono);">'+c.replied+'</span></div>'
    +'<div class="inboxai-grid-table__cell" role="cell"><span style="font-family:var(--mono);color:var(--text-secondary);">'+c.received+'</span></div>'
    +'<div class="inboxai-grid-table__cell" role="cell"><div class="inboxai-row-actions"><div class="inboxai-btn--icon" data-action="more" data-key="'+c.email+'" title="More actions"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/></svg></div></div></div>'
    +'</div>'
  ).join('') : '<div class="inboxai-grid-table__cell inboxai-grid-table__cell--empty">No contacts match your filters.<button class="inboxai-btn--secondary inboxai-btn--clear" id="clear-contacts-filters-btn">Clear filters</button></div>';

  const rangeStart = fullList.length===0 ? 0 : start+1;
  const rangeEnd = Math.min(start+CONTACTS_PAGE_SIZE, fullList.length);
  document.getElementById('contacts-count-label').textContent =
    fullList.length===0 ? 'Showing 0 contacts' : 'Showing '+rangeStart+' to '+rangeEnd+' of '+fullList.length+' contacts';
  document.getElementById('contacts-pager').innerHTML = fullList.length>CONTACTS_PAGE_SIZE ? paginationHtml('contacts', fullList.length, state.contactsPage, CONTACTS_PAGE_SIZE) : '';
}

function clearContactsFilters(){
  state.contactsFilters = {search:'', category:'all', priority:'all'};
  state.contactsPage = 1;
  const searchEl = document.getElementById('contacts-search');
  if(searchEl) searchEl.value = '';
  const catEl = document.getElementById('contacts-filter-category');
  if(catEl) catEl.value = 'all';
  const prEl = document.getElementById('contacts-filter-priority');
  if(prEl) prEl.value = 'all';
  renderContacts();
}

function exportContactsCsv(){
  const list = filteredContacts();
  if(list.length===0){ showToast('No contacts to export'); return; }
  const headers = ['Name','Email','Category','Priority','Messages','Replied','Last Contact'];
  const rows = list.map(c => [c.name, c.email, c.category, c.priority, c.count, c.replied, c.received]);
  downloadCsv('contacts-export.csv', headers, rows);
  showToast('Exported '+list.length+' contact'+(list.length===1?'':'s')+' to CSV','success');
}

/* ================= EVENT DELEGATION ================= */
document.addEventListener('click', function(e){
  const menuItemEl = e.target.closest('.inboxai-row-menu__item[data-menu-action]');
  if(menuItemEl){
    const action = menuItemEl.dataset.menuAction;
    const key = menuItemEl.dataset.key;
    closeRowMenu();
    if(action==='view'){
      window.location.href = 'inbox.html?search='+encodeURIComponent(key);
    } else if(action==='delete'){
      state.deletedContacts.add(key);
      renderContacts();
      showToast('Contact deleted');
    }
    return;
  }

  const actionEl = e.target.closest('[data-action="more"]');
  if(actionEl){
    e.stopPropagation();
    openRowMenu(actionEl, 'contact', actionEl.dataset.key);
    return;
  }

  const pagerBtn = e.target.closest('.inboxai-pager__btn');
  if(pagerBtn && !pagerBtn.disabled){
    state.contactsPage = parseInt(pagerBtn.dataset.page, 10);
    renderContacts();
    document.getElementById('main').scrollTo({top:0, behavior:'smooth'});
    return;
  }

  if(e.target.id==='clear-contacts-filters-btn'){ clearContactsFilters(); }
});

document.addEventListener('input', function(e){
  if(e.target.id==='contacts-search'){ state.contactsFilters.search = e.target.value; state.contactsPage = 1; renderContacts(); }
});
document.addEventListener('change', function(e){
  if(e.target.classList.contains('inboxai-filter-select') && e.target.id.indexOf('contacts-filter-')===0){
    state.contactsFilters[e.target.dataset.filter] = e.target.value;
    state.contactsPage = 1;
    renderContacts();
  }
});

document.getElementById('contacts-export-btn').addEventListener('click', exportContactsCsv);

/* ================= INIT ================= */
renderContacts();
