<?php
/**
 * Orden de Domiciliación SEPA
 * Sistema Tictac Comunicación
 */

require_once '../config.php';

$pageTitle = 'Nuevo SEPA';
$showBackButton = true;

// Obtener clientes del CRM
$clientes = array();
$mysqli = conexionBBDD();
if ($mysqli) {
    $res = $mysqli->query("SELECT id, company_name, phone, address, city, zip, country, vat_number FROM crm_clients WHERE deleted = 0 ORDER BY company_name ASC");
    if ($res) { while ($row = $res->fetch_assoc()) { $clientes[] = $row; } $res->free(); }
}

$clientesJson = json_encode($clientes, JSON_UNESCAPED_UNICODE);

$additionalStyles = '
<style>
    .editor-container { background:white; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,0.05); padding:40px; margin:30px 0; }
    .form-section { margin-bottom:35px; }
    .form-section h3 { color:' . BRAND_COLOR . '; margin-bottom:20px; padding-bottom:10px; border-bottom:2px solid ' . BRAND_COLOR . '; }
    .form-row { display:grid; grid-template-columns:repeat(auto-fit,minmax(250px,1fr)); gap:20px; margin-bottom:20px; }
    .form-group { display:flex; flex-direction:column; }
    .form-group label { font-weight:600; margin-bottom:8px; color:#333; font-size:14px; }
    .form-group input, .form-group select {
        padding:12px; border:2px solid #e0e0e0; border-radius:8px; font-size:14px; transition:all .3s;
    }
    .form-group input:focus, .form-group select:focus { outline:none; border-color:' . BRAND_COLOR . '; }
    .form-group input[readonly] { background:#f5f5f5; color:#666; }
    .acreedor-box { background:#fff0f7; border:2px solid ' . BRAND_COLOR . '; border-radius:10px; padding:20px; margin-bottom:30px; }
    .acreedor-box h4 { color:' . BRAND_COLOR . '; margin:0 0 15px 0; font-size:14px; font-weight:700; letter-spacing:0.5px; }
    .acreedor-row { display:flex; gap:8px; align-items:baseline; margin-bottom:8px; font-size:13px; }
    .acreedor-row .label { font-weight:700; color:#333; min-width:220px; }
    .acreedor-row .value { color:#555; }
    /* searchable select */
    .ss-wrapper { position:relative; width:100%; }
    .ss-main { display:flex; align-items:center; border:2px solid #e0e0e0; border-radius:8px; padding:10px 12px; cursor:pointer; background:white; min-height:44px; transition:border-color .3s; }
    .ss-main.ss-open { border-color:' . BRAND_COLOR . '; }
    .ss-selected { flex:1; color:#333; font-size:14px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .ss-selected.ss-ph { color:#999; }
    .ss-arrow { width:0; height:0; border-left:5px solid transparent; border-right:5px solid transparent; border-top:5px solid #999; margin-left:8px; transition:transform .2s; }
    .ss-main.ss-open .ss-arrow { transform:rotate(180deg); }
    .ss-dropdown { display:none; position:absolute; top:100%; left:0; right:0; background:white; border:2px solid ' . BRAND_COLOR . '; border-top:none; border-radius:0 0 8px 8px; z-index:1000; max-height:280px; box-shadow:0 4px 12px rgba(0,0,0,.15); }
    .ss-dropdown.ss-open { display:block; }
    .ss-search { padding:10px; border-bottom:1px solid #eee; background:#fafafa; }
    .ss-search input { width:100%; padding:8px 10px; border:1px solid #ddd; border-radius:5px; font-size:13px; box-sizing:border-box; }
    .ss-options { max-height:220px; overflow-y:auto; }
    .ss-option { padding:10px 12px; cursor:pointer; font-size:13px; border-bottom:1px solid #f5f5f5; transition:background .15s; }
    .ss-option:hover, .ss-option.ss-hi { background:#fff0f7; color:' . BRAND_COLOR . '; }
    .ss-option .ss-sub { display:block; font-size:11px; color:#888; margin-top:2px; }
    .ss-none { padding:12px; text-align:center; color:#999; font-style:italic; }
    .actions-bar { display:flex; gap:15px; justify-content:flex-end; margin-top:40px; padding-top:20px; border-top:2px solid #e0e0e0; }
    .btn { padding:15px 30px; border-radius:50px; font-weight:600; font-size:16px; border:none; cursor:pointer; transition:all .3s; text-decoration:none; display:inline-block; }
    .btn-secondary { background:#6c757d; color:white; } .btn-secondary:hover { background:#5a6268; }
    .btn-success { background:#28a745; color:white; } .btn-success:hover { background:#218838; transform:translateY(-2px); }
</style>
';

include '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>🏦 Orden de Domiciliación SEPA</h1>
        <p>Genera el documento SEPA de adeudo directo para el cliente</p>
    </div>

    <form id="sepaForm" class="editor-container">

        <!-- DATOS DEL ACREEDOR (fijos, informativos) -->
        <div class="form-section">
            <h3>🏢 Información del Acreedor (Tictac)</h3>
            <div class="acreedor-box">
                <h4>INFORMACIÓN CUMPLIMENTADA POR EL ACREEDOR — ya aparece rellena en el PDF</h4>
                <div class="acreedor-row"><span class="label">IDENTIFICADOR DEL ACREEDOR CIF:</span><span class="value">B09912478</span></div>
                <div class="acreedor-row"><span class="label">NOMBRE DEL ACREEDOR:</span><span class="value">TICTAC COMUNICACIÓN DIGITAL SL</span></div>
                <div class="acreedor-row"><span class="label">DIRECCIÓN:</span><span class="value">C/ Escultor Ramon Barba, 1 - Bloque F - 1-2</span></div>
                <div class="acreedor-row"><span class="label">CÓDIGO POSTAL - POBLACIÓN - PROVINCIA:</span><span class="value">14012 - Córdoba</span></div>
                <div class="acreedor-row"><span class="label">PAÍS:</span><span class="value">España</span></div>
            </div>
        </div>

        <!-- SOLO ELEGIR CLIENTE PARA EL ENVÍO -->
        <div class="form-section">
            <h3>👤 Seleccionar Cliente</h3>
            <p style="color:#666;font-size:14px;margin-bottom:20px;">
                El PDF se genera con los campos del deudor <strong>en blanco</strong> para que los rellene el cliente.
                Selecciona el cliente solo si quieres obtener su email para enviárselo.
            </p>

            <div class="form-row">
                <div class="form-group" style="grid-column:1/-1;">
                    <label>Cliente (opcional)</label>
                    <div class="ss-wrapper" id="clienteWrapper">
                        <div class="ss-main" id="clienteMain">
                            <span class="ss-selected ss-ph" id="clienteText">-- Buscar cliente del CRM --</span>
                            <div class="ss-arrow"></div>
                        </div>
                        <div class="ss-dropdown" id="clienteDropdown">
                            <div class="ss-search"><input type="text" id="clienteSearch" placeholder="Buscar cliente..." autocomplete="off"></div>
                            <div class="ss-options" id="clienteOptions"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-row" id="emailRow" style="display:none;">
                <div class="form-group" style="grid-column:1/-1;">
                    <label>Email del cliente (para envío)</label>
                    <input type="text" name="cliente_email" id="clienteEmail" placeholder="email@empresa.com">
                </div>
            </div>

            <!-- Campos ocultos — solo para identificación mínima -->
            <input type="hidden" name="deudor_cif"    id="deudorCif">
            <input type="hidden" name="deudor_nombre" id="deudorNombre">
        </div>

        <div class="actions-bar">
            <a href="index.php" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-success">📄 Generar y Enviar PDF SEPA</button>
        </div>
    </form>
</div>

<?php
$additionalScripts = '
<script>
const clientesData = ' . $clientesJson . ';

// Auto-seleccion si viene cliente_id por URL
(function(){
    const params = new URLSearchParams(window.location.search);
    const preId = params.get("cliente_id");
    if (!preId) return;
    const c = clientesData.find(x => String(x.id) === String(preId));
    if (!c) return;
    document.getElementById("clienteText").textContent = c.company_name || "";
    document.getElementById("clienteText").classList.remove("ss-ph");
    document.getElementById("deudorCif").value    = c.vat_number || "";
    document.getElementById("deudorNombre").value = c.company_name || "";
    fetch("../presupuestos/get_contacto.php?client_id=" + c.id)
        .then(r=>r.json())
        .then(contactos=>{
            if(contactos && contactos.length > 0){
                const cp = contactos.find(x=>x.is_primary_contact==="1")||contactos[0];
                if(cp.email){ document.getElementById("clienteEmail").value=cp.email; document.getElementById("emailRow").style.display=""; }
            }
        }).catch(()=>{});
})();

function esc(t){const d=document.createElement("div");d.textContent=t;return d.innerHTML;}

(function(){
    const main   = document.getElementById("clienteMain");
    const drop   = document.getElementById("clienteDropdown");
    const search = document.getElementById("clienteSearch");
    const optsEl = document.getElementById("clienteOptions");
    const txtEl  = document.getElementById("clienteText");
    let open = false, filtered = [], hi = -1;

    function render(q) {
        q = (q||"").toLowerCase();
        filtered = clientesData.filter(c => (c.company_name||"").toLowerCase().includes(q) || (c.city||"").toLowerCase().includes(q));
        if (!filtered.length) { optsEl.innerHTML=\'<div class="ss-none">Sin resultados</div>\'; return; }
        optsEl.innerHTML = filtered.map((c,i) => `<div class="ss-option" data-i="${i}"><strong>${esc(c.company_name||"")}</strong><span class="ss-sub">${esc((c.city||"")+(c.vat_number?" · "+c.vat_number:""))}</span></div>`).join("");
        optsEl.querySelectorAll(".ss-option").forEach((el,i)=>el.addEventListener("click",()=>pick(i)));
        hi=-1;
    }
    function pick(i) {
        const c = filtered[i]; if(!c) return;
        txtEl.textContent = c.company_name||""; txtEl.classList.remove("ss-ph");
        close();
        document.getElementById("deudorCif").value    = c.vat_number||"";
        document.getElementById("deudorNombre").value = c.company_name||"";
        // Obtener email del cliente
        fetch("../presupuestos/get_contacto.php?client_id="+c.id)
            .then(r=>r.json())
            .then(contactos=>{
                if(contactos && contactos.length > 0){
                    const cp = contactos.find(x=>x.is_primary_contact==="1")||contactos[0];
                    if(cp.email){ document.getElementById("clienteEmail").value=cp.email; document.getElementById("emailRow").style.display=""; }
                }
            }).catch(()=>{});
    }
    function openDD() { if(open) return; open=true; main.classList.add("ss-open"); drop.classList.add("ss-open"); search.value=""; render(""); search.focus(); }
    function close() { open=false; main.classList.remove("ss-open"); drop.classList.remove("ss-open"); }
    main.addEventListener("click", e=>{e.stopPropagation(); open?close():openDD();});
    search.addEventListener("input", e=>render(e.target.value));
    search.addEventListener("keydown", e=>{
        const opts=optsEl.querySelectorAll(".ss-option");
        if(e.key==="ArrowDown"){e.preventDefault();hi=Math.min(hi+1,opts.length-1);opts.forEach((o,i)=>o.classList.toggle("ss-hi",i===hi));if(opts[hi])opts[hi].scrollIntoView({block:"nearest"});}
        else if(e.key==="ArrowUp"){e.preventDefault();hi=Math.max(hi-1,0);opts.forEach((o,i)=>o.classList.toggle("ss-hi",i===hi));}
        else if(e.key==="Enter"&&hi>=0){e.preventDefault();pick(hi);}
        else if(e.key==="Escape") close();
    });
    document.addEventListener("click",e=>{ if(!document.getElementById("clienteWrapper").contains(e.target)) close(); });
})();

document.getElementById("sepaForm").addEventListener("submit", function(e){
    e.preventDefault();
    const fd = new FormData(this);
    const email = document.getElementById("clienteEmail").value.trim();
    const nombre = document.getElementById("deudorNombre").value || "cliente";
    const btn = e.submitter;
    if (btn) { btn.disabled = true; btn.textContent = "Generando..."; }

    // 1. Siempre descargar el PDF
    const fdPdf = new FormData(this);
    fdPdf.append("action", "sepa_pdf");
    fetch("api.php", {method:"POST", body:fdPdf})
        .then(r => { if (!r.ok) throw new Error("Error generando PDF"); return r.blob(); })
        .then(blob => {
            const url = URL.createObjectURL(blob);
            const a = document.createElement("a");
            a.href = url;
            a.download = "SEPA_" + nombre.replace(/[^a-zA-Z0-9]/g,"_") + ".pdf";
            a.click();
            URL.revokeObjectURL(url);

            // 2. Si hay email, enviar también por correo
            if (email) {
                const fdEmail = new FormData(document.getElementById("sepaForm"));
                fdEmail.append("action", "sepa_email");
                return fetch("api.php", {method:"POST", body:fdEmail})
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            alert("✅ PDF descargado y enviado por email a: " + email);
                        } else {
                            alert("📄 PDF descargado. Error al enviar email: " + (data.message||""));
                        }
                    });
            } else {
                alert("📄 PDF generado y descargado correctamente.");
            }
        })
        .catch(err => alert("Error: " + err.message))
        .finally(() => { if(btn){ btn.disabled=false; btn.innerHTML="📄 Generar y Enviar PDF SEPA"; } });
});
</script>
';
include '../includes/footer.php';
?>