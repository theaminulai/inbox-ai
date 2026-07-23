/* =====================================================================
   CF7 AI Inbox — Dashboard page script.
   Requires common.js to be loaded first (messages data + shared helpers).
   ===================================================================== */

const state = {
  formsEnabled:true /* no cross-page settings sync yet — see General Settings on the Settings page */
};

const chartDatasets = {
  daily:  {axis:['Jul 14','Jul 16','Jul 17','Jul 19','Jul 20'], nw:'0,150 90,140 180,160 270,110 360,125 450,90 540,105 630,60 720,80', rv:'0,180 90,172 180,182 270,158 360,165 450,140 540,150 630,118 720,128', rp:'0,198 90,193 180,200 270,182 360,187 450,168 540,175 630,148 720,155'},
  weekly: {axis:['May 26','Jun 9','Jun 23','Jul 7','Jul 20'], nw:'0,140 60,120 120,150 180,90 240,110 300,70 360,95 420,60 480,80 540,45 600,65 660,30 720,50', rv:'0,175 60,165 120,178 180,150 240,160 300,130 360,148 420,120 480,135 540,105 600,120 660,95 720,108', rp:'0,195 60,190 120,198 180,180 240,185 300,168 360,178 420,158 480,168 540,145 600,155 660,132 720,140'},
  monthly:{axis:['Feb','Mar','Apr','May','Jun','Jul'], nw:'0,170 140,150 280,160 420,100 560,80 720,40', rv:'0,190 140,178 280,185 420,150 560,135 720,100', rp:'0,205 140,198 280,202 420,178 560,168 720,150'}
};

/* ================= RENDER ================= */
function renderDashboardTable(){
  const tbody = document.getElementById('dashboard-table-body');
  if(!tbody) return;
  const list = messages.slice(0,6);
  tbody.innerHTML = list.map(rowHtml).join('');
  document.getElementById('dashboard-count-label').textContent = 'Showing '+list.length+' of '+messages.length+' messages';
}

function checkEmptyState(){
  document.getElementById('dashboard-populated').style.display = state.formsEnabled ? '' : 'none';
  document.getElementById('dashboard-empty').style.display = state.formsEnabled ? 'none' : '';
}

/* ================= ROW ACTIONS (view/reply -> Inbox page; more -> local menu) ============= */
document.addEventListener('click', function(e){
  const menuItemEl = e.target.closest('.cf7-ai-inbox-row-menu__item[data-menu-action]');
  if(menuItemEl){
    const action = menuItemEl.dataset.menuAction;
    const id = parseInt(menuItemEl.dataset.key, 10);
    closeRowMenu();
    const m = messages.find(x=>x.id===id);
    if(!m) return;
    if(action==='reviewed'){ m.status='reviewed'; renderDashboardTable(); showToast('Marked as reviewed','success'); }
    else if(action==='archive'){ m.status='archived'; renderDashboardTable(); showToast('Message archived'); }
    else if(action==='retry'){
      showToast('Retrying analysis…');
      setTimeout(()=>{
        m.status='new'; m.confidence=87; m.summary='Manually re-analyzed after a provider timeout. Please confirm category and priority.';
        m.reasoning='Retried successfully on the second attempt; category and priority carried over from the original request context.';
        renderDashboardTable();
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
    if(action==='view' || action==='reply'){
      window.location.href = 'inbox.html?view='+actionEl.dataset.id;
    }
  }
});

/* ================= FILTER SHORTCUTS ================= */
/* Summary cards, Attention Required, Priority Distribution, and Categories
   all link straight to inbox.html?<key>=<value> now (see dashboard.html) —
   no click-handling needed here, they're real links. */

/* ================= CHART TOGGLE ================= */
document.getElementById('chart-toggle').addEventListener('click', function(e){
  const btn = e.target.closest('button');
  if(!btn) return;
  this.querySelectorAll('button').forEach(b=>b.classList.remove('cf7-ai-inbox-is-active'));
  btn.classList.add('cf7-ai-inbox-is-active');
  const d = chartDatasets[btn.dataset.range];
  document.getElementById('chart-line-new').setAttribute('points', d.nw);
  document.getElementById('chart-line-reviewed').setAttribute('points', d.rv);
  document.getElementById('chart-line-replied').setAttribute('points', d.rp);
  document.getElementById('chart-area').setAttribute('points', d.nw+' 720,205 0,205');
  document.getElementById('chart-axis').innerHTML = d.axis.map(a=>'<span>'+a+'</span>').join('');
});

/* ================= REFRESH ================= */
function doRefresh(){
  document.getElementById('dash-skeleton').style.display = '';
  document.getElementById('dashboard-populated').style.display = 'none';
  document.getElementById('dashboard-empty').style.display = 'none';
  setTimeout(()=>{
    document.getElementById('dash-skeleton').style.display = 'none';
    checkEmptyState();
    showToast('Dashboard refreshed','success');
  }, 900);
}
document.getElementById('dash-refresh-btn').addEventListener('click', doRefresh);

/* ================= RETRY QUEUE ================= */
document.getElementById('retry-queue-btn').addEventListener('click', function(){
  const btn = this;
  btn.disabled = true; btn.textContent = 'Retrying…';
  setTimeout(()=>{
    const failed = messages.find(m=>m.status==='failed');
    if(failed){ failed.status='new'; failed.confidence=85; renderDashboardTable(); }
    document.getElementById('proc-completed').textContent = (parseInt(document.getElementById('proc-completed').textContent,10)+1);
    const failedCountEl = document.getElementById('proc-failed');
    failedCountEl.textContent = Math.max(0, parseInt(failedCountEl.textContent,10)-1);
    document.getElementById('proc-lastrun').textContent = 'just now';
    btn.disabled = false; btn.textContent = 'Retry failed items';
    showToast('1 failed item retried successfully','success');
  }, 900);
});

/* ================= TEST CONNECTION ================= */
document.getElementById('dash-test-connection').addEventListener('click', function(){ testConnection('dash-last-checked', this); });

/* ================= INIT ================= */
renderDashboardTable();
checkEmptyState();
