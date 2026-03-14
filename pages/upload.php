<?php
require_once __DIR__ . '/../backend/session.php';
require_once __DIR__ . '/../backend/layout.php';
requireLogin();
$b=BASE_URL;
layoutHead('Upload Music');
?>
<div class="app-wrap">
<?php layoutSidebar('upload');?>
<div class="main-content">
  <header class="page-header"><h1 class="page-title">Upload Music</h1></header>
  <main class="page-body">

    <div style="max-width:560px">
      <!-- Metadata -->
      <div style="margin-bottom:20px;display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <div class="form-group" style="margin-bottom:0">
          <label>Artist name</label>
          <input type="text" id="up-artist" placeholder="e.g. Kraftwerk" maxlength="100">
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label>Album name</label>
          <input type="text" id="up-album" placeholder="e.g. Autobahn" maxlength="100">
        </div>
      </div>

      <!-- Drop zone -->
      <div id="drop-zone" style="border:2px dashed var(--border);border-radius:var(--radius-lg);padding:48px 24px;text-align:center;cursor:pointer;transition:border-color .15s,background .15s;background:var(--bg-subtle);margin-bottom:20px">
        <div id="dz-inner">
          <div style="margin-bottom:12px;color:var(--text-faint)"><?=icon('upload')?></div>
          <div style="font-size:15px;font-weight:600;color:var(--text-muted);margin-bottom:6px">Drop audio files here</div>
          <div style="font-size:13px;color:var(--text-faint);margin-bottom:16px">MP3, FLAC, OGG, WAV, M4A, AAC · Max 100 MB each</div>
          <label class="btn btn-secondary" style="width:auto;cursor:pointer">
            Browse files
            <input type="file" id="file-input" multiple accept=".mp3,.flac,.ogg,.wav,.m4a,.aac,.opus" style="display:none">
          </label>
        </div>
      </div>

      <!-- File list -->
      <div id="file-list" style="margin-bottom:16px"></div>

      <!-- Upload button -->
      <button class="btn btn-primary" id="upload-btn" disabled style="opacity:.5">Upload</button>

      <!-- Progress / results -->
      <div id="upload-results" style="margin-top:20px"></div>
    </div>

  </main>
</div>
<?php layoutPlayerBar();?>
</div>
<style>
#drop-zone.over{border-color:var(--accent);background:var(--accent-soft);}
.file-item{display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--bg-subtle);border:1px solid var(--border);border-radius:var(--radius);margin-bottom:6px;font-size:13.5px}
.file-item-name{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:500}
.file-item-size{color:var(--text-faint);flex-shrink:0}
.file-item-status{width:20px;height:20px;flex-shrink:0;display:flex;align-items:center;justify-content:center}
.upload-ok{color:#166534}.upload-err{color:#991B1B}
.prog-bar{height:3px;background:var(--bg-muted);border-radius:2px;margin-top:16px;overflow:hidden}
.prog-fill{height:100%;background:var(--accent);border-radius:2px;width:0%;transition:width .3s}
</style>
<script>
const dz=document.getElementById('drop-zone');
const fi=document.getElementById('file-input');
const fl=document.getElementById('file-list');
const ub=document.getElementById('upload-btn');
let files=[];

function fmtSize(b){if(b<1024)return b+'B';if(b<1048576)return(b/1024).toFixed(1)+'KB';return(b/1048576).toFixed(1)+'MB';}

function renderList(){
  fl.innerHTML='';
  files.forEach((f,i)=>{
    fl.innerHTML+=`<div class="file-item" id="fi-${i}">
      <div class="file-item-status" id="fs-${i}"></div>
      <div class="file-item-name">${f.name}</div>
      <div class="file-item-size">${fmtSize(f.size)}</div>
    </div>`;
  });
  ub.disabled=files.length===0;
  ub.style.opacity=files.length?'1':'0.5';
}

dz.addEventListener('dragover',e=>{e.preventDefault();dz.classList.add('over');});
dz.addEventListener('dragleave',()=>dz.classList.remove('over'));
dz.addEventListener('drop',e=>{e.preventDefault();dz.classList.remove('over');addFiles(e.dataTransfer.files);});
dz.addEventListener('click',()=>fi.click());
fi.addEventListener('change',()=>addFiles(fi.files));

function addFiles(flist){
  Array.from(flist).forEach(f=>{if(!files.find(x=>x.name===f.name&&x.size===f.size))files.push(f);});
  renderList();
}

ub.addEventListener('click',async()=>{
  if(!files.length)return;
  ub.disabled=true;ub.textContent='Uploading...';
  const res=document.getElementById('upload-results');
  res.innerHTML='<div class="prog-bar"><div class="prog-fill" id="pb"></div></div>';
  const pb=document.getElementById('pb');

  const fd=new FormData();
  files.forEach(f=>fd.append('files[]',f));
  fd.append('artist',document.getElementById('up-artist').value.trim()||'Unknown Artist');
  fd.append('album', document.getElementById('up-album').value.trim()||'Unknown Album');

  try {
    const xhr=new XMLHttpRequest();
    xhr.open('POST',window.CUMU_BASE+'/backend/upload_api.php');
    xhr.upload.onprogress=e=>{if(e.lengthComputable)pb.style.width=(e.loaded/e.total*100)+'%';};
    xhr.onload=()=>{
      pb.style.width='100%';
      const d=JSON.parse(xhr.responseText);
      let html='';
      if(d.ok){
        d.data.uploaded.forEach(u=>{html+=`<div class="file-item"><div class="file-item-status upload-ok"><?=icon('check')?></div><div class="file-item-name">${u.name}</div><div class="file-item-size" style="color:#166534">Uploaded</div></div>`;});
        d.data.errors.forEach(e=>{html+=`<div class="file-item"><div class="file-item-status upload-err">✕</div><div class="file-item-name">${e}</div></div>`;});
      } else {
        html=`<div class="alert alert-error">${d.error}</div>`;
      }
      res.innerHTML='<div style="margin-top:12px">'+html+'</div>';
      if(d.ok&&d.data.uploaded.length>0){files=[];renderList();}
      ub.disabled=false;ub.textContent='Upload';
    };
    xhr.onerror=()=>{res.innerHTML='<div class="alert alert-error">Network error.</div>';ub.disabled=false;ub.textContent='Upload';};
    xhr.send(fd);
  } catch(e) {
    res.innerHTML='<div class="alert alert-error">'+e.message+'</div>';
    ub.disabled=false;ub.textContent='Upload';
  }
});
</script>
</body></html>
