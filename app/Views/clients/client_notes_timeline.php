<?php
/**
 * Vista: Línea de tiempo de notas del cliente
 */
$timeline_client_id = $client_info->id;
$can_edit_notes = ($login_user->user_type === 'staff');
?>

<!-- Quill CSS -->
<link rel="stylesheet" href="https://cdn.quilljs.com/1.3.7/quill.snow.css">

<div class="card tt-timeline-card" id="tt-client-timeline" style="margin-bottom:20px;">
    <div class="card-header d-flex align-items-center justify-content-between" style="background:#fff;border-bottom:2px solid #f0f0f0;padding:14px 20px;">
        <div class="d-flex align-items-center gap-2">
            <div style="width:32px;height:32px;background:linear-gradient(135deg,#d72173,#ff6b9d);border-radius:8px;display:flex;align-items:center;justify-content:center;">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
            </div>
            <span class="fw-bold" style="font-size:15px;">Onboarding &amp; Notas</span>
            <span id="tt-notes-count" style="background:#f5f5f5;color:#888;font-size:11px;padding:3px 8px;border-radius:20px;">...</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <select id="tt-notes-filter" class="form-select form-select-sm" style="width:auto;font-size:12px;border-color:#e0e0e0;">
                <option value="all">Todas</option>
                <option value="pinned">Fijadas</option>
            </select>
            <?php if ($can_edit_notes): ?>
            <button onclick="ttOpenNewNote()" class="btn btn-sm" style="background:#d72173;color:#fff;border:none;padding:6px 14px;border-radius:6px;font-size:12px;font-weight:600;">
                + Nueva nota
            </button>
            <button onclick="ttToggleFullscreen()" class="btn btn-sm btn-default" style="font-size:12px;" title="Pantalla completa">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
            </button>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body" style="padding:0;">
        <div id="tt-timeline-content" style="padding:24px 24px 8px;">
            <p style="color:#ccc;text-align:center;padding:30px;">Cargando...</p>
        </div>
    </div>
</div>

<!-- Modal nueva/editar nota -->
<div id="tt-note-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:99999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:14px;max-width:600px;width:92%;padding:28px;box-shadow:0 20px 60px rgba(0,0,0,0.2);position:relative;max-height:90vh;overflow-y:auto;">
        <button onclick="ttCloseNoteModal()" style="position:absolute;top:14px;right:16px;background:none;border:none;font-size:24px;cursor:pointer;color:#aaa;line-height:1;">×</button>
        <h3 id="tt-modal-title" style="margin:0 0 20px;font-size:17px;font-weight:700;">Nueva nota</h3>
        <input type="hidden" id="tt-note-id">

        <div style="margin-bottom:14px;">
            <label style="font-size:12px;font-weight:600;color:#666;display:block;margin-bottom:5px;">Título</label>
            <input type="text" id="tt-note-title" class="form-control" placeholder="Título de la nota">
        </div>

        <div style="margin-bottom:14px;">
            <label style="font-size:12px;font-weight:600;color:#666;display:block;margin-bottom:5px;">Descripción</label>
            <div id="tt-quill-editor" style="min-height:140px;border-radius:6px;font-size:13px;"></div>
            <input type="hidden" id="tt-note-desc-hidden">
        </div>

        <div style="margin-bottom:20px;">
            <label style="font-size:12px;font-weight:600;color:#666;display:block;margin-bottom:8px;">Color</label>
            <div style="display:flex;gap:8px;flex-wrap:wrap;" id="tt-color-picker">
                <?php foreach (array(''=>'#f0f0f0','#d72173'=>'#d72173','#ff9800'=>'#ff9800','#4caf50'=>'#4caf50','#2196f3'=>'#2196f3','#9c27b0'=>'#9c27b0','#f44336'=>'#f44336','#607d8b'=>'#607d8b') as $val => $bg): ?>
                <div class="tt-color-dot" data-color="<?php echo $val; ?>"
                    onclick="ttSelectColor('<?php echo $val; ?>')"
                    style="width:26px;height:26px;border-radius:50%;background:<?php echo $bg; ?>;cursor:pointer;border:2px solid transparent;transition:all 0.15s;"></div>
                <?php endforeach; ?>
            </div>
            <input type="hidden" id="tt-note-color" value="">
        </div>

        <div style="display:flex;gap:10px;justify-content:flex-end;">
            <button onclick="ttCloseNoteModal()" class="btn btn-default btn-sm">Cancelar</button>
            <button onclick="ttSaveNote()" class="btn btn-sm" style="background:#d72173;color:#fff;border:none;">Guardar nota</button>
        </div>
    </div>
</div>

<!-- Modal pantalla completa -->
<div id="tt-fullscreen-modal" style="display:none;position:fixed;inset:0;background:#f8f9fa;z-index:99998;overflow-y:auto;padding:30px;">
    <div style="max-width:900px;margin:0 auto;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <h2 style="margin:0;font-size:20px;font-weight:700;color:#1a1a1a;">Onboarding &amp; Notas — <?php echo htmlspecialchars($client_info->company_name ?? ''); ?></h2>
            <button onclick="ttToggleFullscreen()" class="btn btn-default btn-sm">✕ Cerrar</button>
        </div>
        <div id="tt-fullscreen-content"></div>
    </div>
</div>

<style>
.tt-timeline-line { position:relative; padding-left:32px; }
.tt-timeline-line::before {
    content:''; position:absolute; left:10px; top:0; bottom:0;
    width:2px; background:linear-gradient(to bottom,#d72173 0%,#f0f0f0 100%);
}
.tt-note-item { position:relative; margin-bottom:16px; animation:ttFadeIn 0.3s ease forwards; opacity:0; }
@keyframes ttFadeIn { to{opacity:1;transform:translateY(0)} from{opacity:0;transform:translateY(8px)} }
.tt-note-dot {
    position:absolute; left:-25px; top:14px;
    width:12px; height:12px; border-radius:50%;
    background:#d72173; border:2px solid #fff; box-shadow:0 0 0 2px #e0e0e0;
}
.tt-note-dot.pinned { box-shadow:0 0 0 3px rgba(215,33,115,0.3); animation:ttPulse 2s infinite; }
@keyframes ttPulse {
    0%,100%{box-shadow:0 0 0 3px rgba(215,33,115,0.25)}
    50%{box-shadow:0 0 0 6px rgba(215,33,115,0.1)}
}
.tt-note-card {
    background:#fff; border:1px solid #efefef; border-radius:10px;
    padding:14px 16px; border-left:3px solid transparent;
    transition:box-shadow 0.2s,transform 0.2s;
}
.tt-note-card:hover { box-shadow:0 4px 16px rgba(0,0,0,0.08); transform:translateY(-1px); }
.tt-note-card.pinned { border-left-color:#d72173; background:#fff9fc; }
.tt-note-actions { display:none; gap:4px; margin-left:auto; }
.tt-note-card:hover .tt-note-actions { display:flex; }
.tt-act-btn { background:none; border:none; cursor:pointer; padding:3px 7px; border-radius:4px; font-size:11px; color:#aaa; transition:all 0.15s; white-space:nowrap; }
.tt-act-btn:hover { background:#f5f5f5; color:#333; }
.tt-act-btn.pin-active { color:#d72173; }
.tt-pinned-label {
    font-size:10px; font-weight:700; letter-spacing:1px; color:#d72173;
    text-transform:uppercase; margin-bottom:10px;
    display:flex; align-items:center; gap:6px;
}
.tt-pinned-label::after { content:''; flex:1; height:1px; background:#ffe0ef; }
.tt-color-dot.selected { border-color:#333 !important; transform:scale(1.25); }
/* Quill render en las tarjetas */
.tt-note-desc p { margin:0 0 4px; }
.tt-note-desc ul, .tt-note-desc ol { margin:4px 0; padding-left:20px; }
</style>

<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
(function() {
    var CLIENT_ID  = <?php echo intval($timeline_client_id); ?>;
    var BASE_URI   = '<?php echo get_uri(""); ?>';
    var CAN_EDIT   = <?php echo $can_edit_notes ? 'true' : 'false'; ?>;
    var allNotes   = [];
    var filterMode = 'all';
    var isFullscreen = false;
    var ttQuill    = null;
    var selectedColor = '';

    // ── Quill ─────────────────────────────────────────
    function initQuill() {
        if (ttQuill) return;
        ttQuill = new Quill('#tt-quill-editor', {
            theme: 'snow',
            modules: { toolbar: [['bold','italic','underline'],[{'list':'ordered'},{'list':'bullet'}],['clean']] }
        });
    }

    // ── Cargar notas ──────────────────────────────────
    function loadNotes() {
        $.ajax({
            url: BASE_URI + 'clients/get_timeline_notes/' + CLIENT_ID,
            dataType: 'json',
            success: function(resp) {
                if (resp && resp.success) {
                    allNotes = resp.notes;
                    renderNotes();
                }
            },
            error: function() { console.error('Error cargando notas'); }
        });
    }

    function renderNotes() {
        var notes   = filterMode === 'pinned' ? allNotes.filter(function(n){return n.pinned;}) : allNotes;
        var pinned  = notes.filter(function(n){return n.pinned;});
        var regular = notes.filter(function(n){return !n.pinned;});
        document.getElementById('tt-notes-count').textContent = allNotes.length + ' nota' + (allNotes.length!==1?'s':'');

        var html = '<div class="tt-timeline-line">';
        if (pinned.length) {
            html += '<div class="tt-pinned-label">📌 Fijadas</div>';
            pinned.forEach(function(n,i){ html += buildCard(n,i); });
            if (regular.length) html += '<div class="tt-pinned-label" style="margin-top:20px;">📋 Todas</div>';
        }
        regular.forEach(function(n,i){ html += buildCard(n,i+pinned.length); });
        if (!notes.length) html += '<div style="text-align:center;padding:40px;color:#ccc;font-size:13px;">No hay notas aún</div>';
        html += '</div>';

        document.getElementById('tt-timeline-content').innerHTML = html;
        if (isFullscreen) document.getElementById('tt-fullscreen-content').innerHTML = html;
    }

    function buildCard(n, idx) {
        var color     = n.color || '';
        var dotColor  = color || '#d72173';
        var delay     = (idx * 40) + 'ms';
        var colorBar  = color ? '<div style="height:3px;border-radius:3px;background:' + color + ';margin-bottom:10px;"></div>' : '';
        var desc      = n.description ? '<div class="tt-note-desc" style="font-size:13px;color:#555;margin-top:4px;">' + n.description + '</div>' : '';
        var pinLabel  = n.pinned ? '📌 Desfijar' : '📌 Fijar';

        var actions = CAN_EDIT ? (
            '<div class="tt-note-actions">' +
            '<button class="tt-act-btn' + (n.pinned?' pin-active':'') + '" onclick="ttTogglePin(' + n.id + ')">' + pinLabel + '</button>' +
            '<button class="tt-act-btn" onclick="ttEditNote(' + n.id + ')">✏️ Editar</button>' +
            '<button class="tt-act-btn" onclick="ttDeleteNote(' + n.id + ')" style="color:#e53935;">🗑️</button>' +
            '</div>'
        ) : '';

        return '<div class="tt-note-item" style="animation-delay:' + delay + '">' +
            '<div class="tt-note-dot' + (n.pinned?' pinned':'') + '" style="background:' + dotColor + '"></div>' +
            '<div class="tt-note-card' + (n.pinned?' pinned':'') + '">' +
            colorBar +
            '<div style="display:flex;align-items:flex-start;">' +
            '<div style="flex:1;">' +
            (n.title ? '<div style="font-weight:700;font-size:14px;color:#1a1a1a;">' + esc(n.title) + '</div>' : '') +
            desc +
            '</div></div>' +
            '<div style="font-size:11px;color:#aaa;margin-top:10px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">' +
            '🕐 ' + esc(n.date) + ' &nbsp;·&nbsp; 👤 ' + esc(n.author) +
            actions +
            '</div></div></div>';
    }

    function esc(s) {
        return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    // ── Filtro ────────────────────────────────────────
    document.getElementById('tt-notes-filter').addEventListener('change', function(){
        filterMode = this.value; renderNotes();
    });

    // ── Colores ───────────────────────────────────────
    window.ttSelectColor = function(val) {
        selectedColor = val;
        document.getElementById('tt-note-color').value = val;
        document.querySelectorAll('.tt-color-dot').forEach(function(d){
            d.classList.toggle('selected', d.getAttribute('data-color') === val);
            d.style.borderColor = d.getAttribute('data-color') === val ? '#333' : 'transparent';
        });
    };

    // ── Modal ─────────────────────────────────────────
    window.ttOpenNewNote = function() {
        initQuill();
        document.getElementById('tt-note-id').value = '';
        document.getElementById('tt-note-title').value = '';
        ttQuill.root.innerHTML = '';
        document.getElementById('tt-modal-title').textContent = 'Nueva nota';
        ttSelectColor('');
        document.getElementById('tt-note-modal').style.display = 'flex';
    };

    window.ttEditNote = function(id) {
        var n = allNotes.find(function(x){return x.id==id;});
        if (!n) return;
        initQuill();
        document.getElementById('tt-note-id').value = n.id;
        document.getElementById('tt-note-title').value = n.title || '';
        ttQuill.root.innerHTML = n.description || '';
        document.getElementById('tt-modal-title').textContent = 'Editar nota';
        ttSelectColor(n.color || '');
        document.getElementById('tt-note-modal').style.display = 'flex';
    };

    window.ttCloseNoteModal = function() {
        document.getElementById('tt-note-modal').style.display = 'none';
    };

    window.ttSaveNote = function() {
        initQuill();
        var id    = document.getElementById('tt-note-id').value;
        var title = document.getElementById('tt-note-title').value.trim();
        var desc  = ttQuill.root.innerHTML;
        if (desc === '<p><br></p>') desc = '';
        var color = document.getElementById('tt-note-color').value;

        if (!title && !desc) { alert('Escribe al menos un título o descripción'); return; }

        $.ajax({
            url: BASE_URI + 'clients/save_timeline_note',
            method: 'POST',
            data: { id: id, client_id: CLIENT_ID, title: title, description: desc, color: color },
            dataType: 'json',
            success: function(resp) {
                if (resp && resp.success) {
                    ttCloseNoteModal();
                    loadNotes();
                } else { alert('Error guardando la nota'); }
            }
        });
    };

    // ── Pin ───────────────────────────────────────────
    window.ttTogglePin = function(id) {
        $.ajax({
            url: BASE_URI + 'clients/toggle_note_pin/' + id,
            method: 'POST',
            dataType: 'json',
            success: function(resp) {
                if (resp && resp.success) {
                    allNotes.forEach(function(n){ if(n.id==id) n.pinned = resp.pinned; });
                    renderNotes();
                }
            }
        });
    };

    // ── Eliminar ─────────────────────────────────────
    window.ttDeleteNote = function(id) {
        if (!confirm('¿Eliminar esta nota?')) return;
        $.ajax({
            url: BASE_URI + 'clients/delete_timeline_note/' + id,
            method: 'POST',
            dataType: 'json',
            success: function(resp) {
                if (resp && resp.success) {
                    allNotes = allNotes.filter(function(n){return n.id!=id;});
                    renderNotes();
                }
            }
        });
    };

    // ── Pantalla completa ─────────────────────────────
    window.ttToggleFullscreen = function() {
        isFullscreen = !isFullscreen;
        var modal = document.getElementById('tt-fullscreen-modal');
        modal.style.display = isFullscreen ? 'block' : 'none';
        if (isFullscreen) {
            document.getElementById('tt-fullscreen-content').innerHTML =
                document.getElementById('tt-timeline-content').innerHTML;
        }
    };

    loadNotes();
})();
</script>