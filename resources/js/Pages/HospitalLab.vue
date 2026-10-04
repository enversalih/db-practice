<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import cytoscape from 'cytoscape';
import { Activity, BookOpen, CheckCircle2, ChevronLeft, ChevronRight, CircleDollarSign, Database, FlaskConical, GraduationCap, LayoutDashboard, Network, Search, Stethoscope, Table2, Users, X } from 'lucide-vue-next';

const props = defineProps({ overview: Object, tables: Array, relations: Array, exercises: Array });
const tab = ref('overview');
const search = ref('');
const graphEl = ref(null);
const activeExercise = ref(props.exercises[0]);
const sql = ref('SELECT id, first_name, last_name\nFROM patients\nORDER BY id\nLIMIT 10');
const result = ref(null);
const evaluating = ref(false);
const modal = ref(null);
const modalLoading = ref(false);
const modalSearch = ref('');
let cy = null;

const filteredTables = computed(() => props.tables.filter(t => t.name.toLocaleLowerCase('tr').includes(search.value.toLocaleLowerCase('tr'))));
const totalTransactions = computed(() => (props.overview.counts.appointments || 0) + (props.overview.counts.visits || 0) + (props.overview.counts.payments || 0));
const fmt = n => new Intl.NumberFormat('tr-TR').format(n || 0);
const bytes = n => n < 1024 ? `${n} B` : n < 1048576 ? `${(n/1024).toFixed(1)} KB` : `${(n/1048576).toFixed(1)} MB`;
const display = value => value === null ? 'NULL' : typeof value === 'object' ? JSON.stringify(value) : String(value);

async function changeTab(value) {
    tab.value = value;
    if (value === 'schema') { await nextTick(); drawGraph(); }
}
function drawGraph() {
    if (!graphEl.value || cy) return;
    const connected = new Set(props.relations.flatMap(r => [r.source, r.target]));
    const nodes = props.tables.filter(t => connected.has(t.name)).map(t => ({ data:{ id:t.name, label:t.name } }));
    const edges = props.relations.map((r,i) => ({ data:{ id:`e${i}`, source:r.source, target:r.target, label:r.source_column } }));
    cy = cytoscape({ container:graphEl.value, elements:[...nodes,...edges], style:[
        { selector:'node', style:{ 'background-color':'#102f38','border-color':'#24c9c1','border-width':1.5,label:'data(label)',color:'#d7e9eb','font-size':10,'text-valign':'center','text-halign':'center',shape:'round-rectangle',width:125,height:34 } },
        { selector:'edge', style:{ width:1,'line-color':'#28505a','target-arrow-color':'#3ba29e','target-arrow-shape':'triangle','curve-style':'bezier',opacity:.72 } }
    ], layout:{ name:'cose', animate:false, nodeRepulsion:900000, idealEdgeLength:100, gravity:.18, padding:35 } });
}
async function openTable(name, page=1) {
    modalLoading.value = true;
    if (!modal.value || modal.value.name !== name) modal.value = { name, columns:[], rows:{ data:[] } };
    const params = new URLSearchParams({ page, per_page:15, search:modalSearch.value });
    const response = await fetch(`/api/tables/${encodeURIComponent(name)}?${params}`);
    modal.value = { name, ...await response.json() };
    modalLoading.value = false;
}
function closeModal(){ modal.value=null; modalSearch.value=''; }
function selectExercise(exercise) { activeExercise.value=exercise; result.value=null; sql.value=''; }
async function evaluate() {
    evaluating.value=true; result.value=null;
    try {
        const response=await fetch('/api/training/evaluate',{ method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content}, body:JSON.stringify({exercise_id:activeExercise.value.id,sql:sql.value}) });
        result.value=await response.json();
    } catch { result.value={correct:false,message:'Sunucuya ulaşılamadı. Lütfen tekrar dene.'}; }
    evaluating.value=false;
}
function keydown(e){ if(e.key==='Escape'&&modal.value) closeModal(); if((e.metaKey||e.ctrlKey)&&e.key==='Enter'&&tab.value==='training') evaluate(); }
onMounted(()=>window.addEventListener('keydown',keydown));
onBeforeUnmount(()=>{window.removeEventListener('keydown',keydown);cy?.destroy()});
</script>

<template>
<Head title="Relasyon Laboratuvarı" />
<div class="shell">
  <aside class="sidebar">
    <div class="brand"><div class="brand-mark"><Database :size="22"/></div><div><strong>Relasyon Lab</strong><span>HBYS veri laboratuvarı</span></div></div>
    <nav class="nav" aria-label="Ana navigasyon">
      <button :class="{active:tab==='overview'}" @click="changeTab('overview')"><LayoutDashboard :size="18"/><span>Genel Bakış</span></button>
      <button :class="{active:tab==='tables'}" @click="changeTab('tables')"><Table2 :size="18"/><span>Tablolar</span></button>
      <button :class="{active:tab==='schema'}" @click="changeTab('schema')"><Network :size="18"/><span>ER Diyagramı</span></button>
      <button :class="{active:tab==='training'}" @click="changeTab('training')"><GraduationCap :size="18"/><span>SQL Eğitimi</span></button>
    </nav>
    <div class="db-status"><div class="status-line"><span class="dot"></span> PostgreSQL bağlı</div><small>{{ overview.database }}<br>{{ overview.table_count }} eğitim tablosu</small></div>
  </aside>

  <main class="main">
    <header class="topbar">
      <div><p class="eyebrow">{{ tab==='training'?'UYGULAMALI EĞİTİM':tab==='schema'?'VERİ MODELİ':tab==='tables'?'VERİ KATALOĞU':'HASTANE OPERASYONLARI' }}</p><h1>{{ tab==='training'?'SQL çalışma alanı':tab==='schema'?'İlişki haritası':tab==='tables'?'Tabloları keşfet':'Veri laboratuvarı' }}</h1><p>{{ tab==='training'?'Sorgunu yaz, gerçek veri üzerinde doğrula ve ipuçlarıyla ilerle.':tab==='schema'?'Tablolar arasındaki foreign key ilişkilerini etkileşimli incele.':tab==='tables'?'Şemadaki tabloları ve sayfalanmış örnek kayıtları incele.':'Gerçekçi ve ilişkisel sağlık verileriyle PostgreSQL öğren.' }}</p></div>
      <div class="profile">Veri profili · <b>{{ overview.profile }}</b></div>
    </header>

    <template v-if="tab==='overview'">
      <section class="stats">
        <div class="stat"><div class="stat-head"><span>Hasta</span><Users :size="17"/></div><strong>{{ fmt(overview.counts.patients) }}</strong><small>kayıtlı kişi</small></div>
        <div class="stat"><div class="stat-head"><span>Doktor</span><Stethoscope :size="17"/></div><strong>{{ fmt(overview.counts.doctors) }}</strong><small>aktif uzman</small></div>
        <div class="stat"><div class="stat-head"><span>Randevu</span><Activity :size="17"/></div><strong>{{ fmt(overview.counts.appointments) }}</strong><small>zamanlanmış işlem</small></div>
        <div class="stat"><div class="stat-head"><span>İşlemsel veri</span><CircleDollarSign :size="17"/></div><strong>{{ fmt(totalTransactions) }}</strong><small>ziyaret ve ödeme dahil</small></div>
      </section>
      <section class="grid-2">
        <div class="panel"><div class="panel-head"><h2>Veri alanları</h2><span>{{ overview.table_count }} tablo</span></div><div class="panel-body"><table class="activity"><thead><tr><th>Alan</th><th>Ana tablolar</th><th>Durum</th></tr></thead><tbody>
          <tr><td>Hasta yönetimi</td><td>patients, patient_addresses, allergies</td><td><span class="badge">Hazır</span></td></tr>
          <tr><td>Klinik süreç</td><td>appointments, visits, diagnoses</td><td><span class="badge">Hazır</span></td></tr>
          <tr><td>Laboratuvar</td><td>lab_orders, lab_samples, lab_results</td><td><span class="badge">Hazır</span></td></tr>
          <tr><td>Finans</td><td>invoices, payments, insurance_claims</td><td><span class="badge">Hazır</span></td></tr>
          <tr><td>Operasyon</td><td>inventory, staff, audit_logs</td><td><span class="badge">Hazır</span></td></tr>
        </tbody></table></div></div>
        <div class="panel"><div class="callout"><div><div class="callout-icon"><BookOpen :size="21"/></div><h3>İlk sorgunu çalıştır</h3><p>SELECT ile başla; JOIN, gruplama, alt sorgu ve pencere fonksiyonlarına kadar adım adım ilerle.</p></div><button class="primary" @click="changeTab('training')">Eğitime geç</button></div></div>
      </section>
    </template>

    <template v-else-if="tab==='tables'">
      <div class="toolbar"><div style="position:relative;max-width:380px;width:100%"><Search :size="16" style="position:absolute;left:12px;top:12px;color:#69838d"/><input v-model="search" class="search" style="padding-left:38px" placeholder="Tablo ara…"></div><span style="color:#6d8792;font-size:13px;align-self:center">{{ filteredTables.length }} tablo</span></div>
      <div class="table-grid"><button v-for="table in filteredTables" :key="table.name" class="table-card" @click="openTable(table.name)"><strong>{{ table.name }}</strong><span>≈ {{ fmt(table.estimated_rows) }} satır · {{ bytes(table.bytes) }}</span></button></div>
    </template>

    <template v-else-if="tab==='schema'">
      <div class="schema-layout"><div class="panel"><div class="panel-head"><h2>Şema tabloları</h2><span>{{ tables.length }}</span></div><div class="schema-list"><button v-for="table in tables" :key="table.name" @click="openTable(table.name)"><span>{{ table.name }}</span><span>{{ fmt(table.estimated_rows) }}</span></button></div></div><div class="panel"><div ref="graphEl" class="graph" aria-label="Etkileşimli ER diyagramı"></div></div></div>
    </template>

    <template v-else>
      <div class="training-layout">
        <div class="panel"><div class="panel-head"><h2>Alıştırmalar</h2><span>{{ exercises.length }} adım</span></div><div class="exercise-list"><button v-for="exercise in exercises" :key="exercise.id" class="exercise" :class="{active:exercise.id===activeExercise.id}" @click="selectExercise(exercise)"><span class="exercise-number">{{ exercise.id }}</span><span><b>{{ exercise.title }}</b><small>{{ exercise.level }} · {{ exercise.concept }}</small></span></button></div></div>
        <div class="panel lesson"><span class="level">{{ activeExercise.level }} · Alıştırma {{ activeExercise.id }}</span><h2>{{ activeExercise.title }}</h2><p class="question">{{ activeExercise.question }}</p><span class="concept">{{ activeExercise.concept }}</span><textarea v-model="sql" class="editor" spellcheck="false" aria-label="SQL sorgusu" placeholder="SELECT ..."></textarea><div class="actions"><button class="primary" :disabled="evaluating||!sql.trim()" @click="evaluate">{{ evaluating?'Çalıştırılıyor…':'Sorguyu doğrula' }}</button><span style="color:#627c86;font-size:12px">⌘ / Ctrl + Enter</span></div>
          <div v-if="result" class="result" :class="result.correct?'ok':'error'"><div style="display:flex;gap:8px;align-items:center"><CheckCircle2 v-if="result.correct" :size="17"/><FlaskConical v-else :size="17"/><strong>{{ result.message }}</strong></div><div v-if="result.hint" class="hint">İpucu: {{ result.hint }}</div><div v-if="result.preview?.length" class="preview-table"><table><thead><tr><th v-for="(_,key) in result.preview[0]" :key="key">{{ key }}</th></tr></thead><tbody><tr v-for="(row,i) in result.preview" :key="i"><td v-for="(value,key) in row" :key="key">{{ display(value) }}</td></tr></tbody></table></div></div>
        </div>
      </div>
    </template>
  </main>

  <div v-if="modal" class="modal-backdrop" @click.self="closeModal"><div class="modal" role="dialog" aria-modal="true"><div class="modal-head"><div class="modal-title"><Table2 :size="18"/><h2>{{ modal.name }}</h2></div><div style="display:flex;gap:10px;align-items:center"><input v-model="modalSearch" class="search" style="width:230px;padding:7px 10px" placeholder="Kayıtlarda ara" @keyup.enter="openTable(modal.name,1)"><button class="icon-btn" aria-label="Kapat" @click="closeModal"><X :size="20"/></button></div></div><div class="data-wrap"><div v-if="modalLoading" class="empty">Veriler yükleniyor…</div><table v-else class="data-table"><thead><tr><th v-for="column in modal.columns" :key="column">{{ column }}</th></tr></thead><tbody><tr v-for="(row,i) in modal.rows.data" :key="i"><td v-for="column in modal.columns" :key="column" :title="display(row[column])">{{ display(row[column]) }}</td></tr></tbody></table></div><div class="modal-foot"><span>Toplam {{ fmt(modal.rows.total) }} kayıt · Sayfa {{ modal.rows.current_page }}/{{ modal.rows.last_page }}</span><div class="pagination"><button :disabled="!modal.rows.prev_page_url||modalLoading" @click="openTable(modal.name,modal.rows.current_page-1)"><ChevronLeft :size="15"/></button><button :disabled="!modal.rows.next_page_url||modalLoading" @click="openTable(modal.name,modal.rows.current_page+1)"><ChevronRight :size="15"/></button></div></div></div></div>
</div>
</template>
