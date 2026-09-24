/* ============================================================
   CHURCH MANAGEMENT SYSTEM — APP.JS
   ============================================================ */

/* ============================================================
   Auto-fill "Day Born" from DOB
   ============================================================ */
document.addEventListener('change', e => {
  if (e.target && e.target.id === 'dob') {
    const v = e.target.value;
    const target = document.getElementById('dayBornPreview');
    if (!v || !target) return;
    const days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    target.value = days[new Date(v).getDay()];
  }
});

/* ============================================================
   Confirm delete links
   ============================================================ */
document.addEventListener('click', e => {
  const el = e.target.closest('[data-confirm]');
  if (el && !confirm(el.dataset.confirm)) e.preventDefault();
});

/* ============================================================
   Live member search (contributions.php)
   ============================================================ */
let memberSearchTimer = null;

function debounce(fn, ms = 250) {
  clearTimeout(memberSearchTimer);
  memberSearchTimer = setTimeout(fn, ms);
}

async function fetchMembers(q) {
  const url = 'member_search.php?q=' + encodeURIComponent(q);
  const res = await fetch(url, { credentials: 'same-origin' });
  if (!res.ok) return [];
  return res.json();
}

function renderResults(container, list, onPick) {
  if (!list.length) {
    container.innerHTML = '<div class="result-item empty">No members found</div>';
  } else {
    container.innerHTML = list.map(m => `
      <div class="result-item" data-id="${m.id}"
           data-name="${escapeHtml(m.name)}"
           data-phone="${escapeHtml(m.phone)}"
           data-day="${escapeHtml(m.day)}">
        <div class="avatar" style="width:34px;height:34px;font-size:14px;">
          ${escapeHtml((m.name[0] || '?').toUpperCase())}
        </div>
        <div>
          <div class="name">${escapeHtml(m.name)}</div>
          <div class="meta">${escapeHtml(m.phone || 'no phone')}${m.day ? ' · ' + escapeHtml(m.day) : ''}</div>
        </div>
      </div>
    `).join('');
  }
  container.classList.remove('hidden');
  container.querySelectorAll('.result-item[data-id]').forEach(el => {
    el.addEventListener('click', () => onPick({
      id:    el.dataset.id,
      name:  el.dataset.name,
      phone: el.dataset.phone,
      day:   el.dataset.day,
    }));
  });
}

function escapeHtml(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => (
    { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]
  ));
}

/* ---- Contribution form member picker ---- */
document.addEventListener('DOMContentLoaded', () => {
  const searchInput = document.getElementById('memberSearch');
  const resultsBox  = document.getElementById('memberResults');
  const hiddenId    = document.getElementById('memberId');
  const card        = document.getElementById('selectedMember');
  const selAvatar   = document.getElementById('selAvatar');
  const selName     = document.getElementById('selName');
  const selMeta     = document.getElementById('selMeta');
  const form        = document.getElementById('contribForm');

  if (searchInput && resultsBox && hiddenId) {
    searchInput.addEventListener('input', () => {
      const q = searchInput.value.trim();
      debounce(async () => {
        const list = await fetchMembers(q);
        renderResults(resultsBox, list, pickMember);
      });
    });

    searchInput.addEventListener('focus', async () => {
      if (searchInput.value.trim() === '' && !hiddenId.value) {
        const list = await fetchMembers('');
        renderResults(resultsBox, list, pickMember);
      }
    });

    document.addEventListener('click', e => {
      if (!e.target.closest('#memberSearch') && !e.target.closest('#memberResults')) {
        resultsBox.classList.add('hidden');
      }
    });

    function pickMember(m) {
      hiddenId.value = m.id;
      selAvatar.textContent = (m.name[0] || '?').toUpperCase();
      selName.textContent = m.name;
      selMeta.textContent = (m.phone || 'no phone') + (m.day ? ' · Born on ' + m.day : '');
      card.classList.remove('hidden');
      searchInput.value = '';
      resultsBox.classList.add('hidden');
      searchInput.classList.add('hidden');
    }
  }

  if (form) {
    form.addEventListener('submit', e => {
      if (!hiddenId.value) {
        e.preventDefault();
        alert('Please search and select a member first.');
        searchInput?.focus();
      }
    });
  }

  /* ---- Filter member picker ---- */
  const fInput = document.getElementById('filterMember');
  const fId    = document.getElementById('filterMemberId');
  const fBox   = document.getElementById('filterMemberResults');

  if (fInput && fId && fBox) {
    fInput.addEventListener('input', () => {
      fId.value = '';
      const q = fInput.value.trim();
      debounce(async () => {
        const list = await fetchMembers(q);
        renderResults(fBox, list, m => {
          fInput.value = m.name;
          fId.value = m.id;
          fBox.classList.add('hidden');
        });
      });
    });
    document.addEventListener('click', e => {
      if (!e.target.closest('#filterMember') && !e.target.closest('#filterMemberResults')) {
        fBox.classList.add('hidden');
      }
    });
  }
});

/* ---- Clear selected member ---- */
function clearMember() {
  const hiddenId = document.getElementById('memberId');
  const searchInput = document.getElementById('memberSearch');
  document.getElementById('selectedMember')?.classList.add('hidden');
  hiddenId.value = '';
  searchInput.classList.remove('hidden');
  searchInput.focus();
}

/* ============================================================
   PHOTO ZOOM — tap/click any member avatar to enlarge
   ============================================================ */
(function () {
  const overlay = document.createElement('div');
  overlay.id = 'photoZoomOverlay';
  overlay.style.cssText = `
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.85);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    cursor: zoom-out;
    padding: 20px;
  `;

  const img = document.createElement('img');
  img.style.cssText = `
    max-width: 90vw;
    max-height: 90vh;
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.5);
    border: 4px solid #fff;
    background: #fff;
  `;
  overlay.appendChild(img);

  const caption = document.createElement('div');
  caption.style.cssText = `
    position: fixed;
    bottom: 24px;
    left: 50%;
    transform: translateX(-50%);
    color: #fff;
    font-weight: 600;
    font-size: 15px;
    text-align: center;
    text-shadow: 0 2px 8px rgba(0,0,0,0.6);
    pointer-events: none;
  `;
  overlay.appendChild(caption);

  document.body.appendChild(overlay);

  function openZoom(src, name) {
    img.src = src;
    caption.textContent = name || '';
    overlay.style.display = 'flex';
    document.body.style.overflow = 'hidden';
  }

  function closeZoom() {
    overlay.style.display = 'none';
    document.body.style.overflow = '';
    img.src = '';
    caption.textContent = '';
  }

  overlay.addEventListener('click', closeZoom);
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeZoom();
  });

  document.addEventListener('click', e => {
    const target = e.target;
    if (target.tagName !== 'IMG') return;
    const src = target.getAttribute('src') || '';
    if (!src.includes('uploads/members/')) return;
    e.preventDefault();
    e.stopPropagation();
    const name = target.getAttribute('alt') || '';
    openZoom(src, name);
  });
})();

/* ============================================================
   FUNERAL CONTRIBUTION — extra fields toggle
   Shows beneficiary, relationship when category is Funeral /
   Welfare / Special Appeal.
   ============================================================ */
(function () {
  const FUNERAL_TRIGGERS = ['Funeral Contribution', 'Welfare Contribution', 'Special Appeal'];

  function toggleFuneralFields() {
    const select = document.querySelector('select[name="category"]');
    if (!select) return;
    const val = select.value;

    const block = document.getElementById('funeralFields');
    if (!block) return;

    const isFuneral = FUNERAL_TRIGGERS.includes(val);
    block.style.display = isFuneral ? 'block' : 'none';

    const beneficiaryInput = document.getElementById('beneficiaryName');
    const relationshipSel  = document.getElementById('relationshipSel');
    if (beneficiaryInput) beneficiaryInput.required = isFuneral;
    if (relationshipSel)  relationshipSel.required  = isFuneral;
  }

  document.addEventListener('change', e => {
    if (e.target && e.target.name === 'category') toggleFuneralFields();
  });
  document.addEventListener('DOMContentLoaded', toggleFuneralFields);
  setTimeout(toggleFuneralFields, 200);
})();

/* ============================================================
   Photo preview (members.php / member_edit.php)
   ============================================================ */
document.addEventListener('change', e => {
  if (e.target && e.target.id === 'photoInput') {
    const file = e.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = ev => {
      const img = document.getElementById('photoPreview');
      const fb  = document.getElementById('photoPreviewFallback');
      if (img) { img.src = ev.target.result; img.style.display = 'block'; }
      if (fb) fb.style.display = 'none';
    };
    reader.readAsDataURL(file);
  }
});