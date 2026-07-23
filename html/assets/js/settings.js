/* =====================================================================
   CF7 AI Inbox — Settings page script (AI Provider / General / Prompts /
   Usage & Billing / Notifications / Import & Migration — six subtabs
   sharing one page, switched via JS since they're all "Settings").
   Requires common.js to be loaded first (shared helpers).
   ===================================================================== */

const SETTINGS_TABS = ['ai-settings','general-settings','prompts','usage','notifications','flamingo'];

function showSettingsTab(key){
  if(SETTINGS_TABS.indexOf(key)===-1) key = 'ai-settings';
  document.querySelectorAll('.cf7-ai-inbox-screen').forEach(s=>s.classList.remove('cf7-ai-inbox-is-active'));
  const el = document.getElementById('screen-'+key);
  if(el) el.classList.add('cf7-ai-inbox-is-active');
  document.querySelectorAll('[data-subnav]').forEach(a=>{
    a.classList.toggle('cf7-ai-inbox-is-active', a.dataset.subnav === key);
  });
  document.getElementById('main').scrollTo({top:0, behavior:'instant'});
  window.scrollTo({top:0, behavior:'instant'});
  if(window.history && history.replaceState){
    const url = key==='ai-settings' ? 'settings.html' : ('settings.html?tab='+key);
    history.replaceState(null, '', url);
  }
}

document.addEventListener('click', function(e){
  const subnavEl = e.target.closest('[data-subnav]');
  if(subnavEl){ showSettingsTab(subnavEl.dataset.subnav); return; }

  const providerOpt = e.target.closest('.cf7-ai-inbox-provider__option');
  if(providerOpt){
    document.querySelectorAll('.cf7-ai-inbox-provider__option').forEach(o=>{ o.classList.remove('cf7-ai-inbox-is-selected'); o.querySelector('.cf7-ai-inbox-provider__radio').classList.remove('cf7-ai-inbox-is-checked'); });
    providerOpt.classList.add('cf7-ai-inbox-is-selected');
    providerOpt.querySelector('.cf7-ai-inbox-provider__radio').classList.add('cf7-ai-inbox-is-checked');
    return;
  }

  const switchEl = e.target.closest('.cf7-ai-inbox-switch');
  if(switchEl){
    switchEl.classList.toggle('cf7-ai-inbox-is-on');
    if(switchEl.dataset.formToggle){
      showToast('Form settings updated','success');
    }
    return;
  }
});

/* ================= AI PROVIDER TAB ================= */
document.getElementById('settings-test-connection').addEventListener('click', function(){ testConnection(null, this); });
document.getElementById('settings-save-provider').addEventListener('click', function(){ showToast('Provider settings saved','success'); });

/* ================= PROMPTS TAB ================= */
document.getElementById('prompts-save-btn').addEventListener('click', function(){ showToast('Prompts saved','success'); });

/* ================= NOTIFICATIONS TAB ================= */
document.getElementById('notifications-save-btn').addEventListener('click', function(){ showToast('Notification settings saved','success'); });

/* ================= IMPORT & MIGRATION (FLAMINGO) TAB ================= */
function goFlamingoStep(n){
  for(let i=1;i<=4;i++){
    const panel = document.getElementById('flamingo-panel-'+i);
    if(panel) panel.style.display = (i===n) ? '' : 'none';
  }
  document.querySelectorAll('.cf7-ai-inbox-wizard__step').forEach(el=>{
    const step = parseInt(el.dataset.wizardStep, 10);
    el.classList.toggle('cf7-ai-inbox-is-active', step===n);
    el.classList.toggle('cf7-ai-inbox-is-done', step<n);
  });
  document.querySelectorAll('.cf7-ai-inbox-wizard__line').forEach((el, idx)=>{
    el.classList.toggle('cf7-ai-inbox-is-done', (idx+1) < n);
  });
  document.getElementById('main').scrollTo({top:0, behavior:'smooth'});
}

function resetFlamingoWizard(){
  document.getElementById('flamingo-file-input').value = '';
  document.getElementById('flamingo-file-name').textContent = 'No file chosen';
  document.getElementById('flamingo-detected-info').style.display = 'none';
  document.getElementById('flamingo-next-1').disabled = true;
  document.getElementById('flamingo-progress-wrap').style.display = 'none';
  document.getElementById('flamingo-progress-fill').style.width = '0%';
  document.getElementById('flamingo-progress-pct').textContent = '0%';
  const startBtn = document.getElementById('flamingo-start-import-btn');
  startBtn.disabled = false;
  startBtn.textContent = 'Start Import';
  goFlamingoStep(1);
}

document.getElementById('flamingo-file-input').addEventListener('change', function(){
  const f = this.files[0];
  if(!f) return;
  document.getElementById('flamingo-file-name').textContent = f.name;
  document.getElementById('flamingo-detected-info').style.display = 'none';
  document.getElementById('flamingo-next-1').disabled = true;
  showToast('Parsing file…');
  setTimeout(()=>{
    document.getElementById('flamingo-detected-info').style.display = 'flex';
    document.getElementById('flamingo-next-1').disabled = false;
    showToast('File parsed successfully','success');
  }, 700);
});

document.getElementById('flamingo-guide-link').addEventListener('click', function(e){
  e.preventDefault();
  showToast('Downloading Flamingo Export Guide…');
});

document.getElementById('flamingo-next-1').addEventListener('click', ()=> goFlamingoStep(2));
document.getElementById('flamingo-back-2').addEventListener('click', ()=> goFlamingoStep(1));
document.getElementById('flamingo-next-2').addEventListener('click', function(){
  const messagesOn = document.getElementById('flamingo-toggle-messages').classList.contains('cf7-ai-inbox-is-on');
  const attachmentsOn = document.getElementById('flamingo-toggle-attachments').classList.contains('cf7-ai-inbox-is-on');
  const aiOn = document.getElementById('flamingo-toggle-ai').classList.contains('cf7-ai-inbox-is-on');
  document.getElementById('flamingo-summary-messages').textContent = messagesOn ? '1,204' : '0';
  document.getElementById('flamingo-summary-attachments').textContent = attachmentsOn ? '318' : '0';
  document.getElementById('flamingo-summary-cost').textContent = aiOn ? '≈ $18.60' : '$0.00 (AI analysis off)';
  goFlamingoStep(3);
});
document.getElementById('flamingo-back-3').addEventListener('click', ()=> goFlamingoStep(2));

document.getElementById('flamingo-start-import-btn').addEventListener('click', function(){
  document.getElementById('import-modal-overlay').style.display = 'flex';
});

document.getElementById('modal-confirm-import').addEventListener('click', function(){
  document.getElementById('import-modal-overlay').style.display = 'none';
  const btn = document.getElementById('flamingo-start-import-btn');
  btn.disabled = true; btn.textContent = 'Importing…';
  document.getElementById('flamingo-progress-wrap').style.display = 'block';
  showToast('Import started…');

  let pct = 0;
  const fill = document.getElementById('flamingo-progress-fill');
  const pctLabel = document.getElementById('flamingo-progress-pct');
  const interval = setInterval(()=>{
    pct = Math.min(100, pct + Math.round(8 + Math.random()*14));
    fill.style.width = pct+'%';
    pctLabel.textContent = pct+'%';
    if(pct>=100){
      clearInterval(interval);
      document.getElementById('flamingo-progress-label').textContent = 'Import complete';
      const msgCount = document.getElementById('flamingo-summary-messages').textContent;
      const attachments = document.getElementById('flamingo-summary-attachments').textContent;
      document.getElementById('flamingo-complete-summary').textContent = msgCount+' messages and '+attachments+' attachments were imported successfully.';
      showToast(msgCount+' messages imported from Flamingo','success');
      setTimeout(()=> goFlamingoStep(4), 500);
    }
  }, 220);
});

document.getElementById('flamingo-restart-btn').addEventListener('click', resetFlamingoWizard);

/* ================= INIT ================= */
showSettingsTab(getQueryParam('tab') || 'ai-settings');
