<?php
require_once __DIR__ . '/../backend/session.php';
require_once __DIR__ . '/../backend/layout.php';
requireLogin();
$b = BASE_URL;
adminHead('Upload Music', 'upload');
?>

<div style="max-width:600px">

  <!-- Metadata fallback -->
  <div class="admin-card" style="margin-bottom:20px">
    <div class="admin-card-head">Fallback Metadata</div>
    <div style="padding:16px 20px 20px;display:grid;grid-template-columns:1fr 1fr;gap:14px">
      <div class="form-group" style="margin:0">
        <label>Artist (if no ID3 tag)</label>
        <input type="text" id="up-artist" placeholder="Unknown Artist" maxlength="100">
      </div>
      <div class="form-group" style="margin:0">
        <label>Album (if no ID3 tag)</label>
        <input type="text" id="up-album" placeholder="Unknown Album" maxlength="100">
      </div>
    </div>
    <div style="padding:0 20px 16px;font-size:13px;color:var(--tf)">
      ID3 tags in the files are read automatically and take priority over the values above.
    </div>
  </div>

  <!-- Drop zone -->
  <div id="drop-zone" style="border:2px dashed var(--border-m);border-radius:var(--rl);padding:48px 24px;text-align:center;cursor:pointer;transition:border-color .15s,background .15s;background:var(--bg-el);margin-bottom:16px">
    <div style="margin-bottom:14px;color:var(--tf)">
      <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
    </div>
    <div style="font-size:16px;font-weight:700;margin-bottom:6px">Drop audio files here</div>
    <div style="font-size:13px;color:var(--tf);margin-bottom:18px">MP3, FLAC, OGG, WAV, M4A, AAC · Max 100 MB per file</div>
    <label class="btn btn-secondary" style="width:auto;cursor:pointer;display:inline-flex">
      Browse files
      <input type="file" id="file-input" multiple accept=".mp3,.flac,.ogg,.wav,.m4a,.aac,.opus" style="display:none">
    </label>
  </div>

  <!-- File list -->
  <div id="file-list"></div>

  <!-- Upload button -->
  <button class="btn btn-primary" id="upload-btn" disabled style="margin-top:4px;opacity:.4">
    Upload
  </button>

  <!-- Results -->
  <div id="upload-results" style="margin-top:20px"></div>

</div>

<style>
#drop-zone.over{border-color:var(--accent);background:rgba(30,215,96,.05)}
.f-item{display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--r);margin-bottom:6px;font-size:13.5px}
.f-name{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:500}
.f-size{color:var(--tf);flex-shrink:0;font-size:12px}
.f-tag{flex-shrink:0;font-size:11px;font-weight:700;padding:2px 8px;border-radius:20px;background:rgba(30,215,96,.12);color:var(--accent)}
.f-err{background:rgba(255,82,82,.12);color:#ff8080}
.prog{height:3px;background:var(--bg-hover);border-radius:2px;margin-top:12px;overflow:hidden}
.prog-fill{height:100%;background:var(--accent);border-radius:2px;width:0%;transition:width .3s}
</style>

<script>
const dz=document.getElementById('drop-zone'),fi=document.getElementById('file-input'),fl=document.getElementById('file-list'),ub=document.getElementById('upload-btn');
let files=[];
const fmt=b=>b<1048576?(b/1024).toFixed(0)+'KB':(b/1048576).toFixed(1)+'MB';

function render(){
  fl.innerHTML=files.map((f,i)=>`
    <div class="f-item" id="fi-${i}">
      <div class="f-name">${f.name}</div>
      <div class="f-size">${fmt(f.size)}</div>
    </div>`).join('');
  ub.disabled=!files.length;
  ub.style.opacity=files.length?'1':'.4';
}

function addFiles(list){
  Array.from(list).forEach(f=>{
    const ext=f.name.split('.').pop().toLowerCase();
    if(['mp3','flac','ogg','wav','m4a','aac','opus'].includes(ext)&&!files.find(x=>x.name===f.name&&x.size===f.size))
      files.push(f);
  });
  render();
}

dz.addEventListener('dragover',e=>{e.preventDefault();dz.classList.add('over')});
dz.addEventListener('dragleave',()=>dz.classList.remove('over'));
dz.addEventListener('drop',e=>{e.preventDefault();dz.classList.remove('over');addFiles(e.dataTransfer.files)});
dz.addEventListener('click',e=>{if(!e.target.closest('label'))fi.click()});
fi.addEventListener('change',()=>addFiles(fi.files));

ub.addEventListener('click',async()=>{
  if(!files.length)return;
  ub.disabled=true; ub.textContent='Uploading…';
  const res=document.getElementById('upload-results');
  res.innerHTML='<div class="prog"><div class="prog-fill" id="pb"></div></div>';

  const fd=new FormData();
  files.forEach(f=>fd.append('files[]',f));
  fd.append('artist',document.getElementById('up-artist').value.trim()||'Unknown Artist');
  fd.append('album', document.getElementById('up-album').value.trim()||'Unknown Album');

  const xhr=new XMLHttpRequest();
  xhr.open('POST',window.CUMU_BASE+'/backend/upload_api.php');

  xhr.upload.onprogress=e=>{
    if(e.lengthComputable) document.getElementById('pb').style.width=(e.loaded/e.total*100)+'%';
  };

  xhr.onload=()=>{
    document.getElementById('pb').style.width='100%';
    try{
      const d=JSON.parse(xhr.responseText);
      let html='';
      if(d.ok){
        d.data.uploaded.forEach(u=>{
          html+=`<div class="f-item"><div class="f-name"><strong>${u.title}</strong></div><div class="f-size">${u.artist}</div><div class="f-tag">Uploaded</div></div>`;
        });
        d.data.errors.forEach(e=>{
          html+=`<div class="f-item"><div class="f-name" style="color:#ff8080">${e}</div></div>`;
        });
        if(d.data.uploaded.length>0){files=[];render();}
      }else{
        html=`<div class="alert alert-error">${d.error}</div>`;
      }
      res.innerHTML='<div style="margin-top:12px">'+html+'</div>';
    }catch(e){
      res.innerHTML='<div class="alert alert-error">Parse error</div>';
    }
    ub.disabled=false; ub.textContent='Upload';
  };
  xhr.onerror=()=>{res.innerHTML='<div class="alert alert-error">Network error.</div>';ub.disabled=false;ub.textContent='Upload';};
  xhr.send(fd);
});
</script>

<?php adminFoot(); ?>
